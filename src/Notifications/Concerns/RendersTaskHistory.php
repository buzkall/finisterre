<?php

namespace Arzcode\Finisterre\Notifications\Concerns;

use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * Renders a task's history (its description and visible comments) for the
 * notification emails, newest first and cut to the configured number of entries.
 * Needs {@see EmbedsPrivateImages}, which every notification using it has.
 */
trait RendersTaskHistory
{
    /**
     * @param  bool  $withDescription  false when the email already shows the description
     * @param  FinisterreTaskComment|null  $comment  the comment the email is about: it is left
     *                                               out, and so is every comment newer than it,
     *                                               so a late email never shows the future
     */
    protected function taskHistoryHtml(FinisterreTask $task, bool $withDescription = true, ?FinisterreTaskComment $comment = null): ?HtmlString
    {
        $until = $comment instanceof FinisterreTaskComment ? ($comment->scheduled_for ?? $comment->created_at) : null;

        $entries = $task->loadMissing('comments.creator')->comments
            ->reject(fn(FinisterreTaskComment $entry) => $entry->isPending() || $entry->is($comment))
            ->map(fn(FinisterreTaskComment $entry) => [
                'author' => $this->commentAuthor($entry),
                'date'   => $entry->scheduled_for ?? $entry->created_at,
                'body'   => $entry->comment,
            ])
            ->filter(fn(array $entry) => $until === null || $entry['date'] <= $until)
            ->toBase();

        if ($withDescription && filled($task->description)) {
            $entries->push(['author' => $task->creatorName(), 'date' => $task->created_at, 'body' => $task->description]);
        }

        $limit = config('finisterre.mail.history_entries');

        $entries = $entries
            ->sortByDesc(fn(array $entry) => $entry['date'] ?? Carbon::createFromTimestamp(0))
            ->take(filled($limit) ? (int)$limit : $entries->count())
            ->map(fn(array $entry) => [...$entry, 'body' => $this->embedImages((string)$entry['body'])])
            ->values();

        if ($entries->isEmpty()) {
            return null;
        }

        return new HtmlString(view('finisterre::emails.task-history', ['entries' => $entries])->render());
    }

    protected function commentAuthor(FinisterreTaskComment $comment): string
    {
        /** @var Authenticatable|null $creator */
        $creator = $comment->creator;

        return $creator?->getUserDisplayName() ?? '';
    }
}
