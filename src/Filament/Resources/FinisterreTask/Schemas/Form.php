<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas;

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

/**
 * The create form carries every field. On edit only the long-form ones
 * (title, description, attachments) remain: status, priority, assignee,
 * tags and due date are changed from the task page, right next to their
 * values, and subtasks live there too.
 */
class Form
{
    public static function configure(Schema $schema): Schema
    {
        $userIsReporterOnly = FinisterrePlugin::get()->canViewOnlyTheirTasks();

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
                Select::make('priority')
                    ->label(__('finisterre::finisterre.priority'))
                    ->hiddenOn('edit')
                    ->options(TaskPriorityEnum::class)
                    ->default(fn() => $userIsReporterOnly ? TaskPriorityEnum::Urgent : TaskPriorityEnum::Low)
                    ->required()
                    ->helperText(fn() => $userIsReporterOnly ? __('finisterre::finisterre.priority_help') : '')
                    ->columnSpan(1),

                DatePicker::make('due_at')
                    ->label(__('finisterre::finisterre.due_at'))
                    // A later ->hidden() would overwrite ->hiddenOn(), so keep both conditions together.
                    ->hidden(fn(string $operation) => $operation === 'edit' || $userIsReporterOnly)
                    ->columnSpan(1),

                SpatieMediaLibraryFileUpload::make('attachments')
                    ->label(__('finisterre::finisterre.attachments'))
                    ->multiple()
                    ->disk(config('finisterre.attachments_disk') ?? 'public')
                    ->collection('tasks')
                    ->openable()
                    ->downloadable()
                    ->columnSpan(fn(string $operation) => $operation === 'edit' ? 'full' : 1),

                Select::make('assignee_id')
                    ->label(__('finisterre::finisterre.assignee_id'))
                    ->required()
                    ->relationship(
                        'assignee',
                        FinisterrePlugin::get()->getAuthUser()?->getUserNameColumn(),
                        fn($query) => $query->assignableUsers()
                    )
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->getUserDisplayName())
                    ->searchable((array)config('finisterre.authenticatable_attribute', 'name'))
                    ->preload()
                    ->hidden(fn(string $operation) => $operation === 'edit' || $userIsReporterOnly)
                    ->default(config('finisterre.fallback_notifiable_id'))
                    ->columnSpan(1),

                TagsSelect::make()
                    ->hiddenOn('edit')
                    ->afterStateHydrated(function(Select $component, ?FinisterreTask $record): void {
                        if ($record) {
                            $component->state($record->tags->pluck('id')->all());
                        }
                    })
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(function(FinisterreTask $record, $state): void {
                        $record->tags()->sync($state ?? []);
                    })
                    ->columnSpan(1),
            ])->columns(3)->columnSpanFull(),
        ]);
    }
}
