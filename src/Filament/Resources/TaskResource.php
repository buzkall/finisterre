<?php

namespace Buzkall\Finisterre\Filament\Resources;

use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStateEnum;
use Buzkall\Finisterre\Filament\Resources\TaskResource\Pages;
use Buzkall\Finisterre\Models\Task;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getModelLabel(): string
    {
        return __('finisterre::finisterre.task');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label(__('finisterre::finisterre.title'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                RichEditor::make('description')
                    ->label(__('finisterre::finisterre.description'))
                    ->columnSpanFull(),

                Select::make('state')
                    ->label(__('finisterre::finisterre.state'))
                    ->hiddenOn('create')
                    ->options(TaskStateEnum::class)
                    ->required(),

                Select::make('priority')
                    ->label(__('finisterre::finisterre.priority'))
                    ->options(TaskPriorityEnum::class)
                    ->required(),

                DatePicker::make('due_at')
                    ->label(__('finisterre::finisterre.due_at')),

                DatePicker::make('completed_at')
                    ->label(__('finisterre::finisterre.completed_at'))
                    ->hiddenOn('create')
                    ->disabled(),

                /*SpatieMediaLibraryFileUpload::make('attachments')
                    ->label(__('finisterre::finisterre.attachments'))
                    ->multiple()
                    ->collection('tasks')*/

                /*TextInput::make('created_by_user_id')
                    ->label(__('finisterre::finisterre.created_by_user_id'))
                    ->numeric(),

                TextInput::make('assigned_to_user_id')
                    ->label(__('finisterre::finisterre.assigned_to_user_id'))
                    ->numeric(),*/
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('finisterre::finisterre.title'))
                    ->searchable(),

                TextColumn::make('state')
                    ->label(__('finisterre::finisterre.state'))
                    ->searchable(),

                TextColumn::make('priority')
                    ->label(__('finisterre::finisterre.priority'))
                    ->searchable(),

                TextColumn::make('due_at')
                    ->label(__('finisterre::finisterre.due_at'))
                    ->dateTime('d/m/y H:i')
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label(__('finisterre::finisterre.completed_at'))
                    ->dateTime('d/m/y H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('finisterre::finisterre.updated_at'))
                    ->dateTime('d/m/y H:i')
                    ->sortable(),
/*
                TextColumn::make('creator.name')
                    ->label(__('finisterre::finisterre.creator_name'))
                    ->sortable(),*/

                TextColumn::make('assignee.name')
                    ->label(__('finisterre::finisterre.assignee_name'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label(__('finisterre::finisterre.state'))
                    ->options(TaskStateEnum::class),

                SelectFilter::make('priority')
                    ->label(__('finisterre::finisterre.priority'))
                    ->options(TaskPriorityEnum::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'edit'   => Pages\EditTask::route('/{record}/edit'),
        ];
    }
}
