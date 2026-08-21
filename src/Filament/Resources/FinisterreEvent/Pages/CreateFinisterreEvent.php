<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreEvent\Pages;

use Arzcode\Finisterre\Filament\Resources\FinisterreEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinisterreEvent extends CreateRecord
{
    protected static string $resource = FinisterreEventResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
