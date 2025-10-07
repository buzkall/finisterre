<?php

namespace Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource\Pages;

use Buzkall\Finisterre\Filament\Pages\TasksKanbanBoard;
use Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource;
use Buzkall\Finisterre\FinisterrePlugin;
use Buzkall\Finisterre\Models\FinisterreTask;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * @property FinisterreTask $record
 */
class EditFinisterreTask extends EditRecord
{
    protected static string $resource = FinisterreTaskResource::class;
    protected static string $view = 'finisterre::tasks.edit';

    // Note the space! We use a blank heading to avoid the default "Edit" text
    // but if we set it to null, the heading will not be displayed at all,
    // hiding breadcrumbs and header actions.
    protected ?string $heading = ' ';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Remove task change indicator when user views the task
        $this->record->taskChanges()->where('user_id', auth()->id())->delete();
    }

    protected function getHeaderActions(): array
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

            DeleteAction::make()
                ->modalHeading(__('finisterre::finisterre.delete'))
                ->failureRedirectUrl(fn() => FinisterrePlugin::get()->canViewAllTasks() ? TasksKanbanBoard::getUrl() : FinisterreTaskResource::getUrl())
                ->successRedirectUrl(fn() => FinisterrePlugin::get()->canViewAllTasks() ? TasksKanbanBoard::getUrl() : FinisterreTaskResource::getUrl()),
        ];
    }

    public function getBreadcrumbs(): array
    {
        if (FinisterrePlugin::get()->canViewAllTasks()) {
            return [
                TasksKanbanBoard::getUrl() => __('finisterre::finisterre.tasks'),
                null                       => $this->getRecord()?->title ?? __('finisterre::finisterre.edit_task')
            ];
        }

        return parent::getBreadcrumbs();
    }

    protected function getViewData(): array
    {
        return [
            'record' => $this->record,
        ];
    }
}
