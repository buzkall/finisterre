<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Notifications\TaskNotification;
use Arzcode\Finisterre\Notifications\TaskSmsNotification;
use Arzcode\Finisterre\Support\PendingTaskNotifications;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Notification;
use Workbench\App\Models\User;

beforeEach(function() {
    Notification::fake();

    $this->assignee = User::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->task = FinisterreTask::factory()->create([
        'creator_id'  => auth()->id(),
        'assignee_id' => $this->assignee->id,
        'status'      => TaskStatusEnum::Open,
        'priority'    => TaskPriorityEnum::Low,
    ]);

    // What the creation queued is not what these tests are about.
    app(DeferredCallbackCollection::class)->invoke();
    Notification::fake();
});

function runDeferred(): void
{
    app(DeferredCallbackCollection::class)->invoke();
}

it('sends one email for several saves in a request, listing the changes of all of them', function() {
    $this->task->update(['title' => 'Renamed']);
    $this->task->update(['priority' => TaskPriorityEnum::Urgent]);

    runDeferred();

    Notification::assertSentToTimes($this->assignee, TaskNotification::class, 1);
    Notification::assertSentTo($this->assignee, TaskNotification::class, fn(TaskNotification $notification): bool => ($notification->taskChanges['title'] ?? null) === 'Renamed'
        && ($notification->taskChanges['priority'] ?? null) === TaskPriorityEnum::Urgent->value);
});

it('lists the last value when the same field is saved twice', function() {
    $this->task->update(['title' => 'First']);
    $this->task->update(['title' => 'Second']);

    runDeferred();

    Notification::assertSentToTimes($this->assignee, TaskNotification::class, 1);
    Notification::assertSentTo($this->assignee, TaskNotification::class, fn(TaskNotification $notification): bool => $notification->taskChanges['title'] === 'Second');
});

it('sends one email per task', function() {
    $other = FinisterreTask::factory()->create(['creator_id' => auth()->id(), 'assignee_id' => $this->assignee->id, 'status' => TaskStatusEnum::Open]);
    app(DeferredCallbackCollection::class)->invoke();
    Notification::fake();

    $this->task->update(['title' => 'One']);
    $other->update(['title' => 'Two']);

    runDeferred();

    Notification::assertSentToTimes($this->assignee, TaskNotification::class, 2);
});

it('sends nothing when the task ends the request done', function() {
    $this->task->update(['title' => 'Renamed']);
    $this->task->update(['status' => TaskStatusEnum::Done]);

    runDeferred();

    Notification::assertNothingSent();
});

it('does not carry the changes of one request into the next', function() {
    $this->task->update(['title' => 'Renamed']);
    runDeferred();

    // A new request: the scoped bindings are forgotten.
    app()->forgetScopedInstances();
    Notification::fake();

    $this->task->update(['priority' => TaskPriorityEnum::Urgent]);
    runDeferred();

    Notification::assertSentTo($this->assignee, TaskNotification::class, fn(TaskNotification $notification): bool => array_key_exists('priority', $notification->taskChanges)
        && ! array_key_exists('title', $notification->taskChanges));
});

it('sends the text message with the notification of a new task only', function() {
    $new = FinisterreTask::factory()->create(['creator_id' => auth()->id(), 'assignee_id' => $this->assignee->id, 'status' => TaskStatusEnum::Open]);
    $new->update(['title' => 'Renamed right away']);

    runDeferred();

    Notification::assertSentToTimes($this->assignee, TaskSmsNotification::class, 1);
    Notification::assertSentTo($this->assignee, TaskNotification::class, fn(TaskNotification $notification): bool => $notification->task->is($new) && $notification->taskChanges === []);

    Notification::fake();
    $new->update(['title' => 'Renamed later']);

    runDeferred();

    Notification::assertSentTo($this->assignee, TaskNotification::class);
    Notification::assertNotSentTo($this->assignee, TaskSmsNotification::class);
});

it('tells the people added to a task along with its assignee, once each', function() {
    $follower = User::factory()->create();

    $this->task->update(['title' => 'Renamed']);
    PendingTaskNotifications::current()->addRecipients($this->task, [$follower->id, $this->assignee->id, auth()->id()]);

    runDeferred();

    Notification::assertSentToTimes($this->assignee, TaskNotification::class, 1);
    Notification::assertSentToTimes($follower, TaskNotification::class, 1);
    Notification::assertNotSentTo(auth()->user(), TaskNotification::class);
    Notification::assertNotSentTo($follower, TaskSmsNotification::class);
});
