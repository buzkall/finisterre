<?php

namespace Arzcode\Finisterre\Observers;

use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Notifications\TaskNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class FinisterreTaskObserver
{
    /**
     * Positions the kanban board writes itself are authoritative: dropping a card
     * in the middle of the done column must stay where it was dropped, not jump
     * to the top. The board sets this while it renumbers a column.
     */
    protected static bool $repositioning = false;

    /**
     * Run a callback with the automatic "done goes first" repositioning disabled.
     */
    public static function withoutRepositioning(callable $callback): mixed
    {
        self::$repositioning = true;

        try {
            return $callback();
        } finally {
            self::$repositioning = false;
        }
    }

    public function creating(FinisterreTask $task): void
    {
        $task->status = $task->status ?? TaskStatusEnum::Open;
        $task->creator_id = $task->creator_id ?? auth()->id();
        if (is_null($task->assignee_id)) {
            $task->assignee_id = config('finisterre.fallback_notifiable_id');
        }
    }

    public function created(FinisterreTask $task): void
    {
        if ($task->assignee_id) {
            $task->taskChanges()->firstOrCreate(['user_id' => $task->assignee_id]);
        }
    }

    public function updating(FinisterreTask $task): void
    {
        if ($task->isDirty('status')) {
            if ($task->status === TaskStatusEnum::Done) {
                $task->completed_at = now();
            } elseif (! is_null($task->completed_at)) {
                $task->completed_at = null;
            }
        }
    }

    /**
     * A task marked as done from outside the board (the task page quick action,
     * the host application, …) keeps the position it had in its previous column,
     * which drops it in an arbitrary spot of the done column. Move it to the top
     * instead: the most recently finished task is the one worth seeing first.
     */
    public function updated(FinisterreTask $task): void
    {
        if (self::$repositioning || $task->status !== TaskStatusEnum::Done || ! $task->wasChanged('status')) {
            return;
        }

        $this->moveToTopOfColumn($task);
    }

    public function saved(FinisterreTask $task): void
    {
        // Skip notification when nothing meaningful changed. updated_at alone means the task
        // was touched by a comment (which has its own notification logic), and order_column
        // alone means a kanban reorder (drag within a column, plus the sibling renumbers it
        // triggers) — neither should notify the assignee.
        if (empty(array_diff_key($task->getDirty(), array_flip(['order_column', 'updated_at'])))) {
            return;
        }

        defer(function() use ($task) {
            $assignee = $task->assignee;

            // don't notify myself, and don't notify when task is moved to done
            if ($assignee && $assignee->getKey() !== auth()->id() && $task->status !== TaskStatusEnum::Done) {
                $taskChanges = $task->getChanges();
                $assignee->notify(new TaskNotification($task, $taskChanges)); // @phpstan-ignore-line method.notFound

                Notification::make()
                    ->title(__(
                        'finisterre::finisterre.notification.subject',
                        ['priority' => $task->priority->getLabel(), 'title' => $task->title]
                    ))
                    ->body(empty($taskChanges) ?
                        __('finisterre::finisterre.notification.greeting_new', ['title' => $task->title]) :
                        __('finisterre::finisterre.notification.greeting_changes', ['title' => $task->title]))
                    ->actions([
                        Action::make('view')
                            ->label(__('finisterre::finisterre.comment_notification.cta'))
                            ->button()
                            ->url(route('filament.' . config('finisterre.panel_slug') . '.resources.finisterre-tasks.view', $task)),
                    ])->sendToDatabase($assignee);
            }
        });
    }

    /**
     * Put the task first in its status column. The board renumbers a column 10, 20,
     * 30, … on every drag, so there is normally room above the first card: slotting
     * the task one below it is a single write, and nothing else in the column moves.
     * Only when there is no room left — the column starts at 0 or 1, or its first
     * card has no position at all — is the whole column renumbered, the way the
     * board does it. Either way siblings are written through the query builder, so
     * neither their updated_at nor their observers fire: a position-only change is
     * not a change to the task.
     */
    protected function moveToTopOfColumn(FinisterreTask $task): void
    {
        $keyName = $task->getKeyName();

        $position = DB::transaction(function() use ($task, $keyName) {
            $siblings = FinisterreTask::query()
                ->withoutGlobalScopes()
                ->where('status', $task->status)
                ->whereKeyNot($task->getKey())
                ->orderBy('order_column')
                ->orderBy($keyName)
                ->lockForUpdate();

            // Ordering by position lists the rows without one first, so this is the
            // card to beat whatever the column looks like. Its missing position casts
            // to 0, which falls into the renumber below.
            $first = (clone $siblings)->toBase()->first(['order_column']);
            $firstPosition = (int)($first->order_column ?? 0);

            if ($first === null || $firstPosition > 1) {
                $position = $first === null ? 10 : $firstPosition - 1;

                DB::table($task->getTable())
                    ->where($keyName, $task->getKey())
                    ->update(['order_column' => $position]);

                return $position;
            }

            $ids = (clone $siblings)->pluck($keyName)->prepend($task->getKey());

            foreach ($ids as $index => $id) {
                DB::table($task->getTable())
                    ->where($keyName, $id)
                    ->update(['order_column' => ($index + 1) * 10]);
            }

            return 10;
        });

        // Keep the in-memory model in sync without marking it dirty again.
        $task->setAttribute('order_column', $position)->syncOriginalAttribute('order_column');
    }
}
