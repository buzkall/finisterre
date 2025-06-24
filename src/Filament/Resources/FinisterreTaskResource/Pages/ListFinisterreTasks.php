<?php

namespace Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource\Pages;

use Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinisterreTasks extends ListRecords
{
    protected static string $resource = FinisterreTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
