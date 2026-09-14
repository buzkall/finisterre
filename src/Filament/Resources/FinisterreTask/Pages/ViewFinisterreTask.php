<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages;

use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\Concerns\InteractsWithTaskPage;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\AttachmentsDisk;
use Arzcode\Finisterre\Support\EditorFiles;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
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
     * Promote an image pasted into the description or a comment to the card image.
     *
     * The card image has to be an attachment — its thumbnail, its position and the
     * route serving it on a private disk all hang off the media row — so the image
     * is copied into the task's attachments, where it can be starred and unstarred
     * like any other. The copy remembers which file it came from, so picking the same
     * image again reuses it instead of attaching it twice.
     */
    public function setCoverFromEditorImage(string $file): void
    {
        abort_unless(auth()->user()?->can('update', $this->record) ?? false, 403);

        // The name comes off the page: it has to be a file at the disk's root that
        // this task's own description or comments load.
        abort_if($file === '' || str_contains($file, '/') || str_contains($file, '\\') || str_contains($file, '..'), 404);

        // Read fresh: a comment saved since the page loaded claims its images through
        // the query builder, which the record held here knows nothing about.
        $task = $this->record->fresh();

        abort_unless($task instanceof FinisterreTask, 404);

        $loaded = collect([$task->description])
            ->merge($task->comments()->visibleTo(auth()->id())->pluck('comment'))
            ->contains(fn(?string $html): bool => in_array($file, EditorFiles::imagesOnDisk($html), true));

        abort_unless($loaded, 404);

        // Loading a file from a private disk grants nothing on its own (see EditorFiles).
        abort_unless(AttachmentsDisk::isPublic() || EditorFiles::belongsTo($task, $file), 404);

        $media = $task->getMedia('tasks')
            ->first(fn(Media $media): bool => $media->getCustomProperty(FinisterreTask::COVER_SOURCE_PROPERTY) === $file)
            ?? $this->copyEditorImage($task, $file);

        $task->update(['cover_media_id' => $media->getKey()]);

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

    /**
     * The pasted file stays where it is: the description or comment still loads it.
     * Only an image can be a card image, so anything else that got copied is removed
     * again rather than left behind as an attachment nobody added.
     */
    protected function copyEditorImage(FinisterreTask $task, string $file): Media
    {
        $disk = AttachmentsDisk::name();

        abort_unless(rescue(fn(): bool => Storage::disk($disk)->exists($file), false, report: false), 404);

        $media = $task->addMediaFromDisk($file, $disk)
            ->preservingOriginal()
            ->withCustomProperties([FinisterreTask::COVER_SOURCE_PROPERTY => $file])
            ->toMediaCollection('tasks', $disk);

        if (! str_starts_with((string)$media->mime_type, 'image/')) {
            $media->delete();

            abort(404);
        }

        return $media;
    }
}
