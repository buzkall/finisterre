<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Notifications\TaskNotification;
use Arzcode\Finisterre\Notifications\TaskSmsNotification;
use Arzcode\Finisterre\Support\Concerns\ScopedPerRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Who hears about each task saved during the request, and what changed, until the one
 * notification each of them gets is sent after the response. Every notification about
 * a new or changed task goes through here.
 *
 * Several saves of a task in a request (the form, then the rest of the page's work)
 * would otherwise queue one email each, every one listing the changes of the last save
 * only, since the changes are read when the deferred callback runs. Here each save adds
 * its changes, read at that moment, and the callback takes them all at once.
 *
 * Bound as scoped, so an Octane worker or queue job starts every request without the
 * previous one's leftovers.
 */
class PendingTaskNotifications
{
    use ScopedPerRequest;

    /** @var array<string, array{task: FinisterreTask, changes: array<string, mixed>, isNew: bool, assignee: bool, recipients: list<int>}> */
    protected array $entries = [];

    /**
     * Remember a save of the task, for its assignee. A task created in this request keeps
     * being a new one for the notification, however many times it was saved after.
     *
     * @param  array<string, mixed>  $changes
     */
    public function add(FinisterreTask $task, array $changes): void
    {
        $this->queue($task, $changes, assignee: true);
    }

    /**
     * People besides the assignee who hear about the task (the followers picked while
     * creating it).
     *
     * @param  array<mixed>  $userIds
     */
    public function addRecipients(FinisterreTask $task, array $userIds): void
    {
        $this->queue($task, recipients: $userIds);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<mixed>  $recipients
     */
    protected function queue(FinisterreTask $task, array $changes = [], bool $assignee = false, array $recipients = []): void
    {
        $key = Typed::string($task->getKey());
        $previous = $this->entries[$key] ?? null;

        $this->entries[$key] = [
            'task'       => $task,
            'changes'    => [...($previous['changes'] ?? []), ...$changes],
            'isNew'      => ($previous['isNew'] ?? false) || ($task->wasRecentlyCreated && $changes === []),
            'assignee'   => ($previous['assignee'] ?? false) || $assignee,
            'recipients' => TaskFollowers::normalize([...($previous['recipients'] ?? []), ...$recipients]),
        ];

        // Named, so one runs per task, with whatever was added by then.
        defer(fn() => $this->send($key), 'finisterre-task-notification-' . $key);
    }

    /**
     * The assignee hears about the task, and so does anybody added to it, except the
     * one who made the change and, for the people added, its creator: they know. Nobody
     * hears about a task moved to done.
     */
    protected function send(string $key): void
    {
        $entry = $this->entries[$key] ?? null;

        unset($this->entries[$key]);

        if ($entry === null || $entry['task']->status === TaskStatusEnum::Done) {
            return;
        }

        $task = $entry['task'];
        $changes = $entry['isNew'] ? [] : $entry['changes'];
        $me = UserModel::authId();
        $assignee = $task->assignee;

        if ($entry['assignee'] && $assignee instanceof Model && $assignee->getKey() !== $me) {
            $this->notify($task, $assignee, $changes);

            // It texts one fixed number, so it goes once, with the assignee's notification.
            if ($entry['isNew']) {
                UserModel::notify($assignee, new TaskSmsNotification($task));
            }
        }

        $others = array_values(array_diff($entry['recipients'], [$me, $task->creator_id, $task->assignee_id]));

        foreach (TaskFollowers::usersById($others) as $user) {
            $task->taskChanges()->firstOrCreate(['user_id' => $user->getKey()]);
            $this->notify($task, $user, $changes);
        }
    }

    /**
     * Tell somebody about a task by email and in the panel's notifications.
     *
     * @param  array<string, mixed>  $changes
     */
    protected function notify(FinisterreTask $task, Model $user, array $changes): void
    {
        UserModel::notify($user, new TaskNotification($task, $changes));

        Notification::make()
            ->title(__(
                'finisterre::finisterre.notification.subject',
                ['priority' => $task->priority->getLabel(), 'title' => $task->title]
            ))
            ->body($changes === [] ?
                __('finisterre::finisterre.notification.greeting_new', ['title' => $task->title]) :
                __('finisterre::finisterre.notification.greeting_changes', ['title' => $task->title]))
            ->actions([
                Action::make('view')
                    ->label(__('finisterre::finisterre.comment_notification.cta'))
                    ->button()
                    ->url(route('filament.' . Typed::string(config('finisterre.panel_slug')) . '.resources.finisterre-tasks.view', $task)),
            ])->sendToDatabase($user);
    }
}
