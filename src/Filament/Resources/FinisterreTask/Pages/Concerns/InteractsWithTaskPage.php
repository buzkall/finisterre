<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\Concerns;

use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;

/**
 * Behaviour shared by the task view and edit pages.
 *
 * @property FinisterreTask $record
 */
trait InteractsWithTaskPage
{
    use HasKanbanBoardUrl;

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
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('finisterre::finisterre.archive_heading'))
                ->action(fn() => $this->record->update(['archived' => true]))
                ->visible(fn() => $this->canArchive())
                ->hidden(fn() => $this->record->archived),

            Action::make('unarchive')
                ->label(__('finisterre::finisterre.unarchive'))
                ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('finisterre::finisterre.unarchive_heading'))
                ->action(fn() => $this->record->update(['archived' => false]))
                ->visible(fn() => $this->canArchive() && $this->record->archived),
        ];
    }

    /**
     * Archiving changes the task, so a follower who may only comment does not get it.
     */
    protected function canArchive(): bool
    {
        return (FinisterrePlugin::get()->getAuthUser()?->canArchiveTasks() ?? false)
            && (auth()->user()?->can('update', $this->record) ?? false);
    }

    protected function getDeleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->icon(Heroicon::OutlinedTrash)
            ->modalHeading(__('finisterre::finisterre.delete'))
            ->failureRedirectUrl(fn() => $this->getKanbanBoardUrl())
            ->successRedirectUrl(fn() => $this->getKanbanBoardUrl());
    }

    /**
     * No breadcrumbs: they would only repeat the navigation's link to the tasks
     * and the title the page shows right below.
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getViewData(): array
    {
        return [
            'record' => $this->record,
        ];
    }
}
