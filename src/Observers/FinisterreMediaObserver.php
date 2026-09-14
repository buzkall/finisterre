<?php

namespace Arzcode\Finisterre\Observers;

use Arzcode\Finisterre\Models\FinisterreTask;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Keeps a task's card image in step with its attachments.
 *
 * This watches the host application's own media model, so every handler bails out
 * unless the row belongs to a Finisterre task's attachments: nothing else in the
 * media library is any of our business.
 */
class FinisterreMediaObserver
{
    /**
     * The first image attached to a task becomes its card image.
     *
     * "First" is meant literally: the rule only fires while this is the sole image
     * in the collection. Once a task has images, a card image the user cleared stays
     * cleared, and uploading more attachments never quietly puts one back.
     *
     * The write is quiet because attaching a file is not an edit of the task — the
     * same reasoning the board applies to position-only writes — so it must not move
     * updated_at or notify the assignee.
     */
    public function created(Media $media): void
    {
        if (! $this->isTaskAttachment($media) || ! $this->isImage($media)) {
            return;
        }

        $task = $this->task($media);

        if ($task === null || $task->cover_media_id !== null) {
            return;
        }

        $isOnlyImage = $task->media()
            ->where('collection_name', 'tasks')
            ->where('mime_type', 'like', 'image/%')
            ->count() === 1;

        if (! $isOnlyImage) {
            return;
        }

        $task->updateQuietly(['cover_media_id' => $media->getKey()]);
    }

    /**
     * A deleted attachment cannot go on being the card image.
     *
     * Written through the query builder so no observer of the task fires: the user
     * removed a file, they did not edit the task.
     */
    public function deleted(Media $media): void
    {
        if (! $this->isTaskAttachment($media)) {
            return;
        }

        FinisterreTask::query()
            ->withoutGlobalScopes()
            ->whereKey($media->model_id)
            ->where('cover_media_id', $media->getKey())
            ->toBase()
            ->update(['cover_media_id' => null]);
    }

    /**
     * Media library stores the owner's morph class, which is the host's alias rather
     * than the class name when the application maps tasks in Relation::morphMap() —
     * so the comparison goes through getMorphClass(), not FinisterreTask::class.
     */
    protected function isTaskAttachment(Media $media): bool
    {
        return $media->model_type === (new FinisterreTask)->getMorphClass()
            && $media->collection_name === 'tasks';
    }

    protected function isImage(Media $media): bool
    {
        return str_starts_with((string)$media->mime_type, 'image/');
    }

    /**
     * Without the global scopes: a reporter-only panel hides everybody else's
     * tasks, and an attachment added on behalf of one still has to find it.
     */
    protected function task(Media $media): ?FinisterreTask
    {
        return FinisterreTask::query()
            ->withoutGlobalScopes()
            ->find($media->model_id);
    }
}
