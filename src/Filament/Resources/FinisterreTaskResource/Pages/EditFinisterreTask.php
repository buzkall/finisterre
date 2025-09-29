<?php

namespace Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource\Pages;

use Buzkall\Finisterre\Filament\Pages\TasksKanbanBoard;
use Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource;
use Buzkall\Finisterre\Models\FinisterreTask;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFinisterreTask extends EditRecord
{
    protected static string $resource = FinisterreTaskResource::class;
    protected static string $view = 'finisterre::tasks.edit';

    // Note the space! We use a blank heading to avoid the default "Edit" text
    // but if we set it to null, the heading will not be displayed at all,
    // hiding breadcrumbs and header actions.
    protected ?string $heading = ' ';

    protected function getHeaderActions(): array
    {
        if (FinisterreTask::userCanOnlyReport()) {
            return [];
        }

        return [
            Action::make('archive')
                ->label(__('finisterre::finisterre.archive'))
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('finisterre::finisterre.archive_heading'))
                ->action(fn() => $this->record->update(['archived' => true]))
                ->hidden(fn() => $this->record->archived),

            Action::make('unarchive')
                ->label(__('finisterre::finisterre.unarchive'))
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('finisterre::finisterre.unarchive_heading'))
                ->action(fn() => $this->record->update(['archived' => false]))
                ->visible(fn() => $this->record->archived),

            DeleteAction::make()
                ->modalHeading(__('finisterre::finisterre.delete'))
                ->failureRedirectUrl(TasksKanbanBoard::getUrl())
                ->successRedirectUrl(TasksKanbanBoard::getUrl()),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            TasksKanbanBoard::getUrl() => __('finisterre::finisterre.tasks'),
            null                       => $this->getRecord()?->title ?? __('finisterre::finisterre.edit_task')
        ];
    }

    protected function getViewData(): array
    {
        return [
            'record' => $this->record,
        ];
    }
}
