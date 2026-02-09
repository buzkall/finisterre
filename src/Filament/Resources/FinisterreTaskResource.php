<?php

namespace Buzkall\Finisterre\Filament\Resources;

use BackedEnum;
use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Filament\Forms\Components\SubtasksField;
use Buzkall\Finisterre\Filament\Resources\FinisterreTask\Pages;
use Buzkall\Finisterre\FinisterrePlugin;
use Buzkall\Finisterre\Models\FinisterreTask;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Illuminate\Support\HtmlString;

class FinisterreTaskResource extends Resource
{
    protected static ?string $model = FinisterreTask::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;
    protected static bool $hasTitleCaseModelLabel = false;

    public static function shouldRegisterNavigation(): bool
    {
        return FinisterrePlugin::get()->canViewOnlyTheirTasks();
    }

    public static function getModelLabel(): string
    {
        return __('finisterre::finisterre.task_report');
    }

    public static function getPluralLabel(): ?string
    {
        return __('finisterre::finisterre.task_reports');
    }

    public static function form(Schema $schema): Schema
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
                ->fileAttachmentsVisibility('private')
                ->columnSpanFull(),

            Group::make([
                Select::make('status')
                    ->label(__('finisterre::finisterre.status'))
                    ->hiddenOn('create')
                    ->options(TaskStatusEnum::options())
                    ->default(TaskStatusEnum::Open)
                    ->required(),

                Select::make('priority')
                    ->label(__('finisterre::finisterre.priority'))
                    ->options(TaskPriorityEnum::class)
                    ->default(fn() => $userIsReporterOnly ? TaskPriorityEnum::Urgent : TaskPriorityEnum::Low)
                    ->required()
                    ->helperText(fn() => $userIsReporterOnly ? __('finisterre::finisterre.priority_help') : ''),

                DatePicker::make('due_at')
                    ->label(__('finisterre::finisterre.due_at'))
                    ->hidden(fn() => $userIsReporterOnly),

                DatePicker::make('completed_at')
                    ->label(__('finisterre::finisterre.completed_at'))
                    ->hiddenOn('create')
                    ->disabled(),

                SpatieMediaLibraryFileUpload::make('attachments')
                    ->label(__('finisterre::finisterre.attachments'))
                    ->multiple()
                    ->disk(config('finisterre.attachments_disk') ?? 'public')
                    ->collection('tasks')
                    ->openable()
                    ->downloadable(),

                Select::make('assignee_id')
                    ->label(__('finisterre::finisterre.assignee_id'))
                    ->required()
                    ->relationship(
                        'assignee',
                        FinisterrePlugin::get()->getAuthUser()?->getUserNameColumn(),
                        fn($query) => $query->assignableUsers()
                    )
                    ->hidden(fn($operation) => $userIsReporterOnly && $operation == 'create')
                    ->disabled(fn() => $userIsReporterOnly)
                    ->default(config('finisterre.fallback_notifiable_id')),

                SpatieTagsInput::make('tags')
                    ->label(__('finisterre::finisterre.tags'))
                    ->type('tasks'),

                SubtasksField::make('subtasks')
                    ->label(__('finisterre::finisterre.subtasks.label'))
                    ->columnSpanFull()
                    ->hidden(fn() => $userIsReporterOnly),

                TextEntry::make('dates')
                    ->hiddenLabel()
                    ->hiddenOn('create')
                    ->hintIcon('heroicon-o-clock')
                    ->hint(fn($record) => new HtmlString(
                        __('finisterre::finisterre.created_by') . ': ' .
                        '&nbsp;&nbsp;&nbsp;&nbsp;' . // fake alignment
                        $record?->creatorName() .
                        '<br />' .
                        __('finisterre::finisterre.created_at') . ': ' .
                        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . // fake alignment
                        $record?->created_at->format('d/m/y H:i:s') .
                        '<br />' .
                        __('finisterre::finisterre.updated_at') . ': ' . $record?->updated_at->format('d/m/y H:i:s')
                    ))->columnSpanFull(),
            ])->columns(3)->columnSpanFull()
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('finisterre::finisterre.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('finisterre::finisterre.description'))
                    ->html()
                    ->limit(50),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('finisterre::finisterre.status'))
                    ->badge(),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('finisterre::finisterre.priority'))
                    ->badge(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label(__('finisterre::finisterre.completed_at'))
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFinisterreTasks::route('/'),
            'create' => Pages\CreateFinisterreTask::route('/create'),
            'edit'   => Pages\EditFinisterreTask::route('/{record}/edit'),
        ];
    }
}
