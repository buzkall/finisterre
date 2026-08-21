<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreEvent\Schemas;

use Arzcode\Finisterre\Enums\EventStatusEnum;
use Arzcode\Finisterre\Models\FinisterreEvent;
use Arzcode\Finisterre\Models\FinisterreEventAttendee;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Form
{
    public static function configure(Schema $schema): Schema
    {
        $authenticatable = config('finisterre.authenticatable');

        return $schema->components([
            TextInput::make('title')
                ->label(__('finisterre::finisterre.title'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            RichEditor::make('description')
                ->label(__('finisterre::finisterre.description'))
                ->fileAttachmentsDisk(config('finisterre.attachments_disk') ?? 'public')
                ->columnSpanFull(),

            Group::make([
                Select::make('status')
                    ->label(__('finisterre::finisterre.status'))
                    ->hiddenOn('create')
                    ->options(EventStatusEnum::class)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText(__('finisterre::finisterre.events.status_help'))
                    ->columnSpan(1),

                TextInput::make('duration_minutes')
                    ->label(__('finisterre::finisterre.events.duration_minutes'))
                    ->numeric()
                    ->minValue(5)
                    ->default(fn() => config('finisterre.events.default_duration_minutes', 60))
                    ->required()
                    ->columnSpan(1),

                DateTimePicker::make('scheduled_start_at')
                    ->label(__('finisterre::finisterre.events.scheduled_start_at'))
                    ->hiddenOn('create')
                    ->disabled()
                    ->dehydrated(false)
                    ->displayFormat('d/m/y H:i')
                    ->columnSpan(1),

                Toggle::make('requires_confirmation')
                    ->label(__('finisterre::finisterre.events.requires_confirmation'))
                    ->helperText(__('finisterre::finisterre.events.requires_confirmation_help'))
                    ->inline(false)
                    ->columnSpan(1),

                Toggle::make('open_registration')
                    ->label(__('finisterre::finisterre.events.open_registration'))
                    ->helperText(__('finisterre::finisterre.events.open_registration_help'))
                    ->inline(false)
                    ->columnSpan(1),

                TagsInput::make('reminder_offsets')
                    ->label(__('finisterre::finisterre.events.reminder_offsets'))
                    ->helperText(__('finisterre::finisterre.events.reminder_offsets_help'))
                    ->placeholder(implode(', ', config('finisterre.events.default_reminder_offsets', [])))
                    ->nestedRecursiveRules(['integer', 'min:1'])
                    ->columnSpan(1),

                TextInput::make('video_call_url')
                    ->label(__('finisterre::finisterre.events.video_call_url'))
                    ->url()
                    ->helperText(__('finisterre::finisterre.events.video_call_url_help'))
                    ->columnSpanFull(),

                TextEntry::make('public_url')
                    ->label(__('finisterre::finisterre.events.public_url'))
                    ->hiddenOn('create')
                    ->state(fn(?FinisterreEvent $record) => $record?->publicUrl())
                    ->copyable()
                    ->columnSpanFull(),
            ])->columns(3)->columnSpanFull(),

            Section::make(__('finisterre::finisterre.events.agenda'))
                ->schema([
                    RichEditor::make('public_agenda')
                        ->label(__('finisterre::finisterre.events.public_agenda'))
                        ->helperText(__('finisterre::finisterre.events.public_agenda_help'))
                        ->fileAttachmentsDisk(config('finisterre.attachments_disk') ?? 'public'),

                    RichEditor::make('private_agenda')
                        ->label(__('finisterre::finisterre.events.private_agenda'))
                        ->helperText(__('finisterre::finisterre.events.private_agenda_help'))
                        ->fileAttachmentsDisk(config('finisterre.attachments_disk') ?? 'public'),
                ])
                ->collapsible()
                ->columnSpanFull(),

            Section::make(__('finisterre::finisterre.events.schedule'))
                ->schema([
                    Repeater::make('windows')
                        ->label(__('finisterre::finisterre.events.windows'))
                        ->helperText(__('finisterre::finisterre.events.windows_help'))
                        ->relationship('windows')
                        ->schema([
                            DateTimePicker::make('starts_at')
                                ->label(__('finisterre::finisterre.events.window_starts_at'))
                                ->seconds(false)
                                ->required(),
                            DateTimePicker::make('ends_at')
                                ->label(__('finisterre::finisterre.events.window_ends_at'))
                                ->seconds(false)
                                ->required()
                                ->after('starts_at'),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel(__('finisterre::finisterre.events.add_window')),
                ])
                ->collapsible()
                ->columnSpanFull(),

            Section::make(__('finisterre::finisterre.events.attendees'))
                ->schema([
                    Select::make('attendee_user_ids')
                        ->label(__('finisterre::finisterre.events.user_attendees'))
                        ->multiple()
                        ->options(fn() => $authenticatable::query()->userIsActive()->get()
                            ->mapWithKeys(fn($user) => [$user->getKey() => $user->getUserDisplayName()]))
                        ->searchable()
                        ->preload()
                        ->dehydrated(false)
                        ->afterStateHydrated(function(Select $component, ?FinisterreEvent $record): void {
                            if ($record) {
                                $component->state($record->attendees->whereNotNull('user_id')->pluck('user_id')->all());
                            }
                        })
                        ->saveRelationshipsUsing(function(FinisterreEvent $record, $state): void {
                            $ids = collect($state ?? [])->map(fn($id) => (int)$id);

                            $record->attendees()->whereNotNull('user_id')->whereNotIn('user_id', $ids)->get()
                                ->each(fn(FinisterreEventAttendee $attendee) => $attendee->delete());

                            $existing = $record->attendees()->whereNotNull('user_id')->pluck('user_id');
                            $ids->diff($existing)
                                ->each(fn(int $id) => $record->attendees()->create(['user_id' => $id]));
                        }),

                    Repeater::make('guestAttendees')
                        ->label(__('finisterre::finisterre.events.guest_attendees'))
                        ->relationship(
                            'attendees',
                            fn($query) => $query->whereNull('user_id')
                        )
                        ->schema([
                            TextInput::make('guest_name')
                                ->label(__('finisterre::finisterre.events.guest_name'))
                                ->required(),
                            TextInput::make('guest_email')
                                ->label(__('finisterre::finisterre.events.guest_email'))
                                ->email()
                                ->required(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel(__('finisterre::finisterre.events.add_guest')),
                ])
                ->collapsible()
                ->columnSpanFull(),

            Section::make(__('finisterre::finisterre.events.event_tasks'))
                ->schema([
                    Repeater::make('eventTasks')
                        ->hiddenLabel()
                        ->relationship('eventTasks')
                        ->schema([
                            TextInput::make('title')
                                ->hiddenLabel()
                                ->required()
                                ->columnSpan(5),
                            Toggle::make('completed')
                                ->label(__('finisterre::finisterre.events.event_task_completed'))
                                ->inline(false)
                                ->columnSpan(1),
                        ])
                        ->columns(6)
                        ->defaultItems(0)
                        ->orderColumn('order_column')
                        ->mutateRelationshipDataBeforeCreateUsing(function(array $data): array {
                            $data['creator_id'] = auth()->id();

                            return $data;
                        })
                        ->addActionLabel(__('finisterre::finisterre.events.add_event_task')),
                ])
                ->hiddenOn('create')
                ->collapsible()
                ->columnSpanFull(),
        ]);
    }
}
