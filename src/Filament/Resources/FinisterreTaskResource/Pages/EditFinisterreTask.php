<?php

namespace Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource\Pages;

use Buzkall\Finisterre\Filament\Pages\TasksKanbanBoard;
use Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFinisterreTask extends EditRecord
{
    protected static string $resource = FinisterreTaskResource::class;
    protected static string $view = 'finisterre::tasks.edit';

    protected function getHeaderActions(): array
    {
        return [
             Actions\DeleteAction::make()
                 ->failureRedirectUrl(TasksKanbanBoard::getUrl())
                 ->successRedirectUrl(TasksKanbanBoard::getUrl())
        ];
    }

    public function getTitle(): string
    {
        // Because of unsetting the title here, we need to add the header in the view
        // in order to have breadcrumbs and header actions
        return '';
    }

    protected function getViewData(): array
    {
        return [
            'record' => $this->record,
        ];
    }
}
