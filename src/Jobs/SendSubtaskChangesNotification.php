<?php

namespace Arzcode\Finisterre\Jobs;

use Arzcode\Finisterre\Enums\SubtaskChangeActionEnum;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Notifications\SubtaskChangesNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Reports how a task's checklist changed over the notification window.
 *
 * The window is the unique lock: the first edit dispatches this job with the
 * checklist as it stood beforehand, and every dispatch made before it runs is
 * dropped — payload included — so the surviving snapshot is the one the window
 * started from. Here it is diffed against the live table.
 *
 * Nothing is accumulated in between, which is what makes the digest report net
 * effect rather than keystrokes: a subtask added and deleted inside the window,
 * or a tick that was undone, simply is not in the diff.
 *
 * ShouldBeUniqueUntilProcessing rather than plain ShouldBeUnique, so an edit
 * landing while this runs opens the next window instead of being dropped.
 */
class SendSubtaskChangesNotification implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * The task id rather than the model: the task may be deleted during the
     * window (which would fail the job on ModelNotFoundException), and the
     * assignee may change, in which case the digest belongs to the new one.
     *
     * @param  array<int, array{title: string, completed: bool}>  $before  checklist at the start of the window
     */
    public function __construct(public int $taskId, public array $before = []) {}

    public function uniqueId(): string
    {
        return (string)$this->taskId;
    }

    /**
     * Safety net for a worker that dies mid-job, not the window itself — it has
     * to outlast the delay, or the lock expires while the job is still queued
     * and a second one joins it.
     */
    public function uniqueFor(): int
    {
        return (int)config('finisterre.subtasks.notification_delay_minutes', 5) * 60 + 1800;
    }

    public function handle(): void
    {
        $task = FinisterreTask::withoutGlobalScopes()->find($this->taskId);
        $assignee = $task?->assignee;

        if (! $assignee) {
            return;
        }

        $entries = $this->diff($this->checklist());

        if ($entries === []) {
            return;
        }

        $this->notify($task, $assignee, $entries);
    }

    /**
     * One line per subtask that is not where the window left it.
     *
     * @param  array<int, array{title: string, completed: bool}>  $after
     * @return list<string>
     */
    protected function diff(array $after): array
    {
        $entries = [];

        // Existing subtasks first, in checklist order, then whatever is new.
        foreach ($this->before as $id => $was) {
            if (! isset($after[$id])) {
                $entries[] = SubtaskChangeActionEnum::Deleted->line(['title' => $was['title']]);

                continue;
            }

            $is = $after[$id];

            if ($was['title'] !== $is['title']) {
                $entries[] = SubtaskChangeActionEnum::Renamed->line([
                    'original_title' => $was['title'],
                    'title'          => $is['title'],
                ]);
            }

            if ($was['completed'] !== $is['completed']) {
                $entries[] = ($is['completed']
                    ? SubtaskChangeActionEnum::Completed
                    : SubtaskChangeActionEnum::Uncompleted)->line(['title' => $is['title']]);
            }
        }

        foreach (array_diff_key($after, $this->before) as $is) {
            $entries[] = SubtaskChangeActionEnum::Added->line([
                'title' => $is['completed']
                    ? __('finisterre::finisterre.subtask_changes.done', ['title' => $is['title']])
                    : $is['title'],
            ]);
        }

        return $entries;
    }

    /**
     * @return array<int, array{title: string, completed: bool}>
     */
    protected function checklist(): array
    {
        return DB::table(config('finisterre.subtasks.table_name', 'finisterre_subtasks'))
            ->where('task_id', $this->taskId)
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
     * @param  list<string>  $entries
     */
    protected function notify(FinisterreTask $task, Model $assignee, array $entries): void
    {
        $assignee->notify(new SubtaskChangesNotification($task, $entries)); // @phpstan-ignore-line method.notFound

        Notification::make()
            ->title(__('finisterre::finisterre.subtask_changes.subject', ['title' => $task->title]))
            ->body(implode(' · ', $entries))
            ->actions([
                Action::make('view')
                    ->label(__('finisterre::finisterre.subtask_changes.cta'))
                    ->button()
                    ->url(route(
                        'filament.' . config('finisterre.panel_slug') . '.resources.finisterre-tasks.edit',
                        $task
                    )),
            ])
            ->sendToDatabase($assignee);

        $task->taskChanges()->firstOrCreate(['user_id' => $assignee->getKey()]);
    }
}
