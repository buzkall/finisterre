<?php

namespace Buzkall\Finisterre\Filament\Resources\FinisterreTaskReporterResource\Pages;

use Buzkall\Finisterre\Filament\Resources\FinisterreTaskReporterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinisterreTaskReporters extends ListRecords
{
    protected static string $resource = FinisterreTaskReporterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
