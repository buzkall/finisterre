<?php

namespace Buzkall\Finisterre\Filament\Pages;

use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Models\FinisterreTask;
use Illuminate\Contracts\Support\Htmlable;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;

class TasksKanbanBoard extends KanbanBoard
{
    protected static string $model = FinisterreTask::class;
    protected static string $statusEnum = TaskStatusEnum::class;

    public function getTitle(): string|Htmlable
    {
        return __('finisterre::finisterre.tasks');
    }
}
