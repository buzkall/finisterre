<?php

namespace Buzkall\Finisterre\Filament\Resources;

use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Filament\Forms\Components\SubtasksField;
use Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource\Pages;
use Buzkall\Finisterre\Models\FinisterreTask;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Rawilk\FilamentQuill\Filament\Forms\Components\QuillEditor;

class FinisterreTaskResource extends Resource
{
    protected static ?string $model = FinisterreTask::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    public static function shouldRegisterNavigation(): bool
    {
        return FinisterreTask::userCanOnlyReport();
    }

    public static function getModelLabel(): string
    {
        return __('finisterre::finisterre.task_report');
    }

    public static function form(Form $form): Form
    {
        $userIsReporterOnly = FinisterreTask::userCanOnlyReport();

        $subtaskArray = [];
        if (! $userIsReporterOnly) {
            $subtaskArray = [
                SubtasksField::make('subtasks')
                    ->label(__('finisterre::finisterre.subtasks.label'))
                    ->columnSpanFull()
            ];
        }

        return $form->schema([
            TextInput::make('title')
                ->label(__('finisterre::finisterre.title'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            QuillEditor::make('description')
                ->label(__('finisterre::finisterre.description'))
                ->hiddenLabel()
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
                        config('finisterre.authenticatable_attribute'),
                        fn($query) => $query
                            ->when(
                                config('finisterre.authenticatable_filter_column'),
                                fn($query) => $query->where(config('finisterre.authenticatable_filter_column'), config('finisterre.authenticatable_filter_value'))
                            )
                    )
                    ->hidden(fn($operation) => $userIsReporterOnly && $operation == 'create')
                    ->disabled(fn() => $userIsReporterOnly)
                    ->default(config('finisterre.fallback_notifiable_id')),

                SpatieTagsInput::make('tags')
                    ->label(__('finisterre::finisterre.tags'))
                    ->type('tasks'),

                ...$subtaskArray,

                Placeholder::make('dates')
                    ->hiddenLabel()
                    ->hiddenOn('create')
                    ->hintIcon('heroicon-o-clock')
                    ->hint(fn($record) => new HtmlString(
                        __('finisterre::finisterre.created_by') . ': ' .
                        '&nbsp;&nbsp;&nbsp;&nbsp;' . // fake alignment
                        $record?->creator->{config('finisterre.authenticatable_attribute')} .
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

    public static function table(Table $table): Table
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
                    ->label(__('finisterre::finisterre.priority')),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label(__('finisterre::finisterre.completed_at'))
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFinisterreTasks::route('/'), // Needed for the delete action
            'create' => Pages\CreateFinisterreTask::route('/create'),
            'edit'   => Pages\EditFinisterreTask::route('/{record}/edit'),
        ];
    }
}
