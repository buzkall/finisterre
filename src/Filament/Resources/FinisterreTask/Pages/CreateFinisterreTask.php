<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages;

use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\Concerns\HasKanbanBoardUrl;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Support\PanelLabel;
use Filament\Resources\Pages\CreateRecord;

class CreateFinisterreTask extends CreateRecord
{
    use HasKanbanBoardUrl;

    protected static string $resource = FinisterreTaskResource::class;
    protected static bool $canCreateAnother = false;

    /**
     * The form is opened from the board, so "Tasks" has to lead back to it and
     * not to the resource's table, which is where Filament's own breadcrumb for
     * a create page points.
     */
    public function getBreadcrumbs(): array
    {
        if (FinisterrePlugin::get()->canViewAllTasks()) {
            return [
                $this->getKanbanBoardUrl() => PanelLabel::plural(),
                ''                         => __('finisterre::finisterre.create_task'),
            ];
        }

        return parent::getBreadcrumbs();
    }
}
