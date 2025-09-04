<?php

namespace Buzkall\Finisterre\Filament\Resources\FinisterreTaskReporterResource\Pages;

use Buzkall\Finisterre\Filament\Resources\FinisterreTaskReporterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFinisterreTaskReporter extends EditRecord
{
    protected static string $resource = FinisterreTaskReporterResource::class;
    protected static string $view = 'finisterre::tasks.edit';

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
