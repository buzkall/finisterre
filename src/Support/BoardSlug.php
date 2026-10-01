<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

/**
 * Whether a path is free for the task board.
 *
 * A board whose path another route already serves never gets a route of its
 * own, and is then left out of the navigation. That goes for the host panel's
 * routes and for Finisterre's other pages alike: the task resource lives at
 * `finisterre-tasks` and the settings page at `finisterre-settings`.
 */
class BoardSlug
{
    /**
     * The path the slug collides on in a panel that uses Finisterre, or null
     * when it is free in all of them.
     */
    public static function takenPath(string $slug): ?string
    {
        foreach (Filament::getPanels() as $panel) {
            if ($panel->hasPlugin('finisterre') && self::isTaken($panel->getPath(), $slug)) {
                return trim($panel->getPath() . '/' . trim($slug, '/'), '/');
            }
        }

        return null;
    }

    /**
     * Whether a route other than the board's own already serves /{panelPath}/{slug}
     * (the path itself or any of its sub-paths). The board's route is skipped so
     * keeping the current slug, or re-installing, doesn't flag the board itself.
     */
    public static function isTaken(string $panelPath, string $slug): bool
    {
        $target = trim($panelPath . '/' . trim($slug, '/'), '/');

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with(ltrim($route->getActionName(), '\\'), TasksKanbanBoard::class)) {
                continue;
            }

            $uri = trim($route->uri(), '/');

            if ($uri === $target || str_starts_with($uri, $target . '/')) {
                return true;
            }
        }

        return false;
    }
}
