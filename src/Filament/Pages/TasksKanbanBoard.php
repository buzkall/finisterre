<?php

namespace Buzkall\Finisterre\Filament\Pages;

use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Models\FinisterreTask;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Support\Htmlable;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;

class TasksKanbanBoard extends KanbanBoard
{
    protected static string $model = FinisterreTask::class;
    protected static string $statusEnum = TaskStatusEnum::class;
    protected string $editModalWidth = '3xl';
    protected string $editModalTitle = '';
    protected string $editModalSaveButtonLabel = 'Save';
    protected string $editModalCancelButtonLabel = 'Cancel';

    public function getTitle(): string|Htmlable
    {
        return __('finisterre::finisterre.tasks');
    }

    public static function getNavigationLabel(): string
    {
        return __('finisterre::finisterre.tasks');
    }

    protected function getEditModalSaveButtonLabel(): string
    {
        return __('finisterre::finisterre.save');
    }

    protected function getEditModalCancelButtonLabel(): string
    {
        return __('finisterre::finisterre.cancel');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->model(FinisterreTask::class)
                ->label(__('finisterre::finisterre.create_task'))
                ->createAnother(false)
                ->form(
                    $this->getEditModalFormSchema(null)
                )
        ];
    }

    protected function getEditModalFormSchema(?int $recordId): array
    {
        return [
            TextInput::make('title')
                ->label(__('finisterre::finisterre.title'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            RichEditor::make('description')
                ->label(__('finisterre::finisterre.description'))
                ->columnSpanFull(),

            Group::make([
                Select::make('status')
                    ->label(__('finisterre::finisterre.status'))
                    ->hiddenOn('create')
                    ->options(TaskStatusEnum::class)
                    ->default(TaskStatusEnum::Open)
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
            ])->columns()
        ];
    }
}
