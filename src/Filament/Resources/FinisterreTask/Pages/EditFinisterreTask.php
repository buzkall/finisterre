<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages;

use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\Concerns\InteractsWithTaskPage;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Resources\Pages\EditRecord;

/**
 * Full editing of the long-form fields (title, description, attachments).
 * Everything else is changed from the task page.
 *
 * @property FinisterreTask $record
 */
class EditFinisterreTask extends EditRecord
{
    use InteractsWithTaskPage;

    protected static string $resource = FinisterreTaskResource::class;
    protected string $view = 'finisterre::tasks.edit';

    // Note the space! We use a blank heading to avoid the default "Edit" text
    // but if we set it to null, the heading will not be displayed at all,
    // hiding the header actions.
    protected ?string $heading = ' ';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->clearTaskChangeIndicator();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getDeleteAction(),
        ];
    }

    /**
     * Filament stays on the edit page after saving while the user may still
     * edit; the task page is the landing page, so go back there instead.
     */
    protected function getRedirectUrl(): string
    {
        return FinisterreTaskResource::getUrl('view', ['record' => $this->record]);
    }
}
