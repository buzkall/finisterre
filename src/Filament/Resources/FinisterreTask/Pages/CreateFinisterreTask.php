<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages;

use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinisterreTask extends CreateRecord
{
    protected static string $resource = FinisterreTaskResource::class;
    protected static bool $canCreateAnother = false;
}
