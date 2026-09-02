<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\Concerns;

use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

/**
 * Behaviour shared by the task view and edit pages.
 *
 * @property FinisterreTask $record
 */
trait InteractsWithTaskPage
{
    /**
     * Opening the task counts as having seen its latest changes, so the blue
     * "changed" dot on the board card goes away for this user.
     */
    protected function clearTaskChangeIndicator(): void
    {
        $this->record->taskChanges()->where('user_id', auth()->id())->delete();
    }

    /**
     * @return array<Action>
     */
    protected function getArchiveActions(): array
    {
        return [
            Action::make('archive')
                ->label(__('finisterre::finisterre.archive'))
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('finisterre::finisterre.archive_heading'))
                ->action(fn() => $this->record->update(['archived' => true]))
                ->visible(fn() => FinisterrePlugin::get()->getAuthUser()?->canArchiveTasks() ?? false)
                ->hidden(fn() => $this->record->archived),

            Action::make('unarchive')
                ->label(__('finisterre::finisterre.unarchive'))
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('finisterre::finisterre.unarchive_heading'))
                ->action(fn() => $this->record->update(['archived' => false]))
                ->visible(fn() => FinisterrePlugin::get()->getAuthUser()?->canArchiveTasks() && $this->record->archived),
        ];
    }

    protected function getDeleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalHeading(__('finisterre::finisterre.delete'))
            ->failureRedirectUrl(fn() => $this->getKanbanBoardUrl())
            ->successRedirectUrl(fn() => $this->getKanbanBoardUrl());
    }

    public function getBreadcrumbs(): array
    {
        if (FinisterrePlugin::get()->canViewAllTasks()) {
            return [
                $this->getKanbanBoardUrl() => __('finisterre::finisterre.tasks'),
                ''                         => $this->record->title ?: $this->getBreadcrumbFallback(),
            ];
        }

        return parent::getBreadcrumbs();
    }

    protected function getBreadcrumbFallback(): string
    {
        return __('finisterre::finisterre.view_task');
    }

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

    protected function getViewData(): array
    {
        return [
            'record' => $this->record,
        ];
    }
}
