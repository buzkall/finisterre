<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas;

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The create form is laid out like the task page it leads to: the title as
 * heading, a row of coloured selectors for priority, assignee and tags
 * where the page shows its badges, then the description and attachments.
 *
 * On edit only the long-form fields (title, description, attachments) remain:
 * the strip's values are changed from the task page, right next to the badges
 * that show them, and the due date and subtasks live there too.
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
                ->autofocus()
                ->columnSpanFull(),

            // The badge strip of the task page, as selectors: one row, one
            // column per field. Hidden whole on edit, so none of its fields
            // needs to hide itself.
            Group::make([
                ToggleButtons::make('priority')
                    ->label(__('finisterre::finisterre.priority'))
                    ->options(TaskPriorityEnum::class)
                    ->inline()
                    ->default(fn() => $userIsReporterOnly ? TaskPriorityEnum::Urgent : TaskPriorityEnum::Low)
                    ->required()
                    ->helperText(fn() => $userIsReporterOnly ? __('finisterre::finisterre.priority_help') : ''),

                Select::make('assignee_id')
                    ->label(__('finisterre::finisterre.assignee_id'))
                    ->prefixIcon(Heroicon::OutlinedUser)
                    ->required()
                    ->relationship(
                        'assignee',
                        FinisterrePlugin::get()->getAuthUser()?->getUserNameColumn(),
                        fn($query) => $query->assignableUsers()
                    )
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->getUserDisplayName())
                    ->searchable((array)config('finisterre.authenticatable_attribute', 'name'))
                    ->preload()
                    ->default(config('finisterre.fallback_notifiable_id'))
                    // Reporters could never assign their own issues.
                    ->hidden($userIsReporterOnly),

                TagsSelect::make()
                    ->prefixIcon(Heroicon::OutlinedTag)
                    ->placeholder(__('finisterre::finisterre.no_tags'))
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(function(FinisterreTask $record, $state): void {
                        $record->tags()->sync($state ?? []);
                    }),
            ])
                // Two columns for a reporter, whose row has no assignee in it.
                ->columns($userIsReporterOnly ? 2 : 3)
                ->columnSpanFull()
                ->hiddenOn('edit'),

            // Same pairing as the task page: the description with its attachments.
            Section::make()
                ->compact()
                ->schema([
                    RichEditor::make('description')
                        ->label(__('finisterre::finisterre.description'))
                        ->fileAttachmentsDisk(config('finisterre.attachments_disk') ?? 'public')
                        // The editor ships a 3rem body, barely one line. Its content
                        // area is flex-1 inside this wrapper, so growing the wrapper
                        // is what gives the field room to write in; an inline style
                        // keeps it working in host apps that never compile our CSS.
                        ->extraInputAttributes(['style' => 'min-height: 12rem'])
                        ->columnSpanFull(),

                    SpatieMediaLibraryFileUpload::make('attachments')
                        ->label(__('finisterre::finisterre.attachments'))
                        ->multiple()
                        ->disk(config('finisterre.attachments_disk') ?? 'public')
                        ->collection('tasks')
                        ->openable()
                        ->downloadable()
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ]);
    }
}
