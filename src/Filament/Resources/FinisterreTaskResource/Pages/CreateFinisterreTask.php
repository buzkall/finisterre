<?php

namespace Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource\Pages;

use Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinisterreTask extends CreateRecord
{
    protected static string $resource = FinisterreTaskResource::class;
    protected static bool $canCreateAnother = false;
}
