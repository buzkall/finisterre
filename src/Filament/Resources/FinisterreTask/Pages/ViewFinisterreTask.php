<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages;

use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\Concerns\InteractsWithTaskPage;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
     * Promote one of the task's images to its card image, or take the card image
     * away entirely.
     *
     * Null is a first-class answer: a task is allowed to have no card image, and
     * once it has none nothing puts one back — the automatic pick only ever fires
     * on a task's very first image.
     */
    public function setCoverMedia(?int $mediaId): void
    {
        abort_unless(auth()->user()?->can('update', $this->record) ?? false, 403);

        // The id comes off the page, so it is checked against this task's own
        // attachments rather than trusted.
        if ($mediaId !== null) {
            $media = $this->record->getMedia('tasks')->firstWhere('id', $mediaId);

            abort_unless(
                $media !== null && str_starts_with((string)$media->mime_type, 'image/'),
                404
            );
        }

        $this->record->update(['cover_media_id' => $mediaId]);

        $this->refreshRecord();

        Notification::make()
            ->title(__('finisterre::finisterre.quick_update.saved'))
            ->success()
            ->send();
    }

    /**
     * Remember which part of the card image shows, after the user dragged the banner.
     *
     * The value is the vertical object-position percentage, stored on the cover
     * media itself. Clamped here because it comes from the browser.
     */
    public function setCoverPosition(float $position): void
    {
        abort_unless(auth()->user()?->can('update', $this->record) ?? false, 403);

        $media = $this->record->coverMedia;

        abort_unless($media instanceof Media, 404);

        $media
            ->setCustomProperty(FinisterreTask::COVER_POSITION_PROPERTY, round(max(0, min(100, $position)), 2))
            ->save();

        $this->refreshRecord();

        Notification::make()
            ->title(__('finisterre::finisterre.quick_update.saved'))
            ->success()
            ->send();
    }

    /**
     * Quick actions update the record in place; reload it so the badges and
     * cached relations (tags, assignee) reflect what was just saved.
     */
    public function refreshRecord(): void
    {
        $this->record = $this->record->fresh(['tags', 'assignee', 'subtasks', 'media', 'coverMedia']);
    }
}
