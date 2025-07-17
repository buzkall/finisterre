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

    // in case the project has a spotlight integration, we don't want it to register the listPage
    public static function shouldRegisterSpotlight(): bool
    {
        return false;
    }
}
