<?php

namespace Buzkall\Finisterre\Observers;

use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Models\FinisterreTask;
use Buzkall\Finisterre\Notifications\TaskNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class FinisterreTaskObserver
{
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
                            ->url(route('filament.' . config('finisterre.panel_slug') . '.resources.finisterre-tasks.edit', $task)),
                    ])->sendToDatabase($assignee);
            }
        });
    }
}
