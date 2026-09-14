<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages;

use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFinisterreTasks extends ListRecords
{
    protected static string $resource = FinisterreTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    // in case the project has a Spotlight integration, we don't want it to register the listPage
    public static function shouldRegisterSpotlight(): bool
    {
        return false;
    }
}
