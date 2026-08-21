<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreEvent\Pages;

use Arzcode\Finisterre\Filament\Resources\FinisterreEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFinisterreEvents extends ListRecords
{
    protected static string $resource = FinisterreEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
