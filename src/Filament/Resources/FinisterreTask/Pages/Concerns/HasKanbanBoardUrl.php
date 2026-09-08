<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\Concerns;

use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\FinisterrePlugin;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

/**
 * Where "Tasks" points on the pages that hang off the board.
 *
 * The board is the landing page of the package, so every breadcrumb and every
 * redirect out of a task page goes back to it — never to the resource's own
 * table, which is only the fallback for users who may not see all tasks or for
 * a panel where the board route could not be registered.
 */
trait HasKanbanBoardUrl
{
    protected function getKanbanBoardUrl(): string
    {
        if (! FinisterrePlugin::get()->canViewAllTasks()) {
            return FinisterreTaskResource::getUrl();
        }

        try {
            $panel = Filament::getCurrentOrDefaultPanel();
            $routeName = 'filament.' . $panel->getId() . '.pages.' . TasksKanbanBoard::getSlug($panel);

            if (Route::has($routeName)) {
                return TasksKanbanBoard::getUrl();
            }
        } catch (\Throwable) {
            // Fall through to default
        }

        return FinisterreTaskResource::getUrl();
    }
}
