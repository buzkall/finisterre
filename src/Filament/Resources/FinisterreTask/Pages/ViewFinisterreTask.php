<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages;

use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\Concerns\InteractsWithTaskPage;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The task page: what a board card opens. Shows the task compactly, lets the
 * user change status, priority, assignee, tags and due date in place, manage
 * subtasks and comment.
 *
 * @property FinisterreTask $record
 */
class ViewFinisterreTask extends ViewRecord
{
    use InteractsWithTaskPage;

    protected static string $resource = FinisterreTaskResource::class;
    protected string $view = 'finisterre::tasks.view';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->clearTaskChangeIndicator();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->title;
    }

    public function getHeading(): string|Htmlable
    {
        return $this->record->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label(__('finisterre::finisterre.edit'))
                ->color('gray'),

            ...$this->getArchiveActions(),

            $this->getDeleteAction(),
        ];
    }

    /**
     * Quick actions update the record in place; reload it so the badges and
     * cached relations (tags, assignee) reflect what was just saved.
     */
    public function refreshRecord(): void
    {
        $this->record = $this->record->fresh(['tags', 'assignee', 'subtasks', 'media']);
    }
}
