<?php

namespace Arzcode\Finisterre\Observers;

use Arzcode\Finisterre\Jobs\SendSubtaskChangesNotification;
use Arzcode\Finisterre\Models\FinisterreSubtask;
use Illuminate\Support\Facades\DB;

/**
 * Opens a notification window when somebody other than the assignee edits a
 * task's checklist.
 *
 * Nothing is recorded and nothing is sent from here. The observer captures the
 * checklist as it stood *before* the edit and hands that to a delayed job; the
 * unique lock on that job keeps the first dispatch of a window and drops the
 * rest, so the surviving snapshot is always the state the window started from.
 * Five minutes later the job diffs it against the live table.
 */
class FinisterreSubtaskObserver
{
    public function created(FinisterreSubtask $subtask): void
    {
        // The row is already there, so the "before" state is everything but it.
        $this->openWindow($subtask, fn(array $checklist) => array_diff_key(
            $checklist,
            [$subtask->getKey() => null]
        ));
    }

    public function updated(FinisterreSubtask $subtask): void
    {
        // A reorder writes order_column and nothing else, and $touches bumps
        // updated_at on its own. Neither is worth an email. (The checklist's own
        // reorder() uses the query builder, so it never reaches here at all —
        // this guards host code that reorders through Eloquent.)
        if (empty(array_diff_key($subtask->getDirty(), array_flip(['order_column', 'updated_at'])))) {
            return;
        }

        if (! $subtask->wasChanged('title') && ! $subtask->wasChanged('completed')) {
            return;
        }

        // getOriginal() still holds the pre-save values here: Eloquent only
        // re-syncs them after the `updated` event has fired.
        $this->openWindow($subtask, fn(array $checklist) => array_replace($checklist, [
            $subtask->getKey() => [
                'title'     => (string)$subtask->getOriginal('title'),
                'completed' => (bool)$subtask->getOriginal('completed'),
            ],
        ]));
    }

    public function deleted(FinisterreSubtask $subtask): void
    {
        // The row is gone, so put it back into the "before" state.
        $this->openWindow($subtask, fn(array $checklist) => $checklist + [
            $subtask->getKey() => [
                'title'     => (string)$subtask->title,
                'completed' => (bool)$subtask->completed,
            ],
        ]);
    }

    /**
     * @param  callable(array<int, array{title: string, completed: bool}>): array<int, array{title: string, completed: bool}>  $rewind
     */
    protected function openWindow(FinisterreSubtask $subtask, callable $rewind): void
    {
        if (! config('finisterre.subtasks.notify', true)) {
            return;
        }

        $assigneeId = $this->assigneeIdFor($subtask->task_id);

        // Nobody to tell, or the assignee is the one doing the editing.
        if ($assigneeId === null || $assigneeId === auth()->id()) {
            return;
        }

        // Built on every edit, though only the one that opens the window is kept
        // — the unique lock discards the others, payload and all.
        SendSubtaskChangesNotification::dispatch(
            $subtask->task_id,
            $rewind($this->checklist($subtask->task_id))
        )
            ->delay((int)config('finisterre.subtasks.notification_delay_minutes', 5) * 60)
            ->afterCommit();
    }

    /**
     * The task's checklist as the table currently holds it.
     *
     * @return array<int, array{title: string, completed: bool}>
     */
    protected function checklist(int $taskId): array
    {
        return DB::table(config('finisterre.subtasks.table_name', 'finisterre_subtasks'))
            ->where('task_id', $taskId)
            ->orderBy('order_column')
            ->orderBy('id')
            ->get(['id', 'title', 'completed'])
            ->mapWithKeys(fn($row) => [(int)$row->id => [
                'title'     => (string)$row->title,
                'completed' => (bool)$row->completed,
            ]])
            ->all();
    }

    /**
     * Read straight from the table rather than through FinisterreTask: the model
     * carries a global scope keyed off auth()->id() (nobody, in a worker) and
     * eager-loads tags, neither of which one integer is worth.
     */
    protected function assigneeIdFor(int $taskId): ?int
    {
        $assigneeId = DB::table(config('finisterre.table_name'))
            ->where('id', $taskId)
            ->value('assignee_id');

        // Cast so the identity check against auth()->id() holds on drivers that
        // hand integers back as strings.
        return $assigneeId === null ? null : (int)$assigneeId;
    }
}
