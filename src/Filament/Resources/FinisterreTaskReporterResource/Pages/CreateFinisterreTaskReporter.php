<?php

namespace Buzkall\Finisterre\Filament\Resources\FinisterreTaskReporterResource\Pages;

use Buzkall\Finisterre\Filament\Resources\FinisterreTaskReporterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinisterreTaskReporter extends CreateRecord
{
    protected static string $resource = FinisterreTaskReporterResource::class;
    protected static bool $canCreateAnother = false;
}
