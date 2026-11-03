<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Notifications\SMSChannel;
use Arzcode\Finisterre\Notifications\TaskNotification;
use Arzcode\Finisterre\Notifications\TaskSmsNotification;
use Arzcode\Finisterre\Support\PendingTaskNotifications;
use Arzcode\Finisterre\Support\Typed;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Http;
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

function enableSms(): void
{
    config([
        'finisterre.sms_notification.enabled'           => true,
        'finisterre.sms_notification.notify_to'         => '600600600',
        'finisterre.sms_notification.notify_priorities' => [TaskPriorityEnum::Urgent]
    ]);
}

it('texts the configured number when a task is raised to a priority that texts', function() {
    enableSms();
    Http::fake();

    $this->task->update(['priority' => TaskPriorityEnum::Urgent]);

    runDeferred();

    Notification::assertNotSentTo($this->assignee, TaskSmsNotification::class);
    Notification::assertSentOnDemandTimes(TaskSmsNotification::class, 1);
    Notification::assertSentOnDemand(TaskSmsNotification::class, function(TaskSmsNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        $notification->toSms($notifiable);

        return $notification->from === TaskPriorityEnum::Low
            && $notifiable->routeNotificationFor(SMSChannel::class) === '600600600';
    });
    Http::assertSent(fn(Request $request): bool => str_contains(Typed::string($request['text']), TaskPriorityEnum::Low->getLabel())
        && str_contains(Typed::string($request['text']), $this->task->title));
});

it('texts about a raised task whoever raised it and whether it has an assignee or not', function(bool $byAssignee, bool $assigned) {
    enableSms();

    if ($byAssignee) {
        $this->actingAs($this->assignee);
    }

    if (! $assigned) {
        $this->task->update(['assignee_id' => null]);
        runDeferred();
        Notification::fake();
    }

    $this->task->update(['priority' => TaskPriorityEnum::Urgent]);

    runDeferred();

    Notification::assertSentOnDemandTimes(TaskSmsNotification::class, 1);
})->with([
    'raised by the assignee' => [true, true],
    'without assignee'       => [false, false]
]);

it('sends no text when the priority does not reach one that texts', function(TaskPriorityEnum $from, TaskPriorityEnum $to, bool $enabled) {
    enableSms();
    config([
        'finisterre.sms_notification.enabled'           => $enabled,
        'finisterre.sms_notification.notify_priorities' => [TaskPriorityEnum::High, TaskPriorityEnum::Urgent]
    ]);
    $this->task->update(['priority' => $from]);
    runDeferred();
    Notification::fake();

    $this->task->update(['priority' => $to]);

    runDeferred();

    Notification::assertSentTimes(TaskSmsNotification::class, 0);
})->with([
    'lowered from urgent'              => [TaskPriorityEnum::Urgent, TaskPriorityEnum::Low, true],
    'between two priorities that text' => [TaskPriorityEnum::High, TaskPriorityEnum::Urgent, true],
    'raised to one that does not text' => [TaskPriorityEnum::Low, TaskPriorityEnum::Medium, true],
    'raised to urgent with sms off'    => [TaskPriorityEnum::Low, TaskPriorityEnum::Urgent, false]
]);

it('sends no text when the priority ends the request where it started', function() {
    enableSms();
    $this->task->update(['priority' => TaskPriorityEnum::Urgent]);
    runDeferred();
    Notification::fake();

    $this->task->update(['priority' => TaskPriorityEnum::Low]);
    $this->task->update(['priority' => TaskPriorityEnum::Urgent]);

    runDeferred();

    Notification::assertSentTo($this->assignee, TaskNotification::class);
    Notification::assertSentTimes(TaskSmsNotification::class, 0);
});

it('sends the text message with the notification of a new task, not with other changes', function() {
    enableSms();

    $new = FinisterreTask::factory()->create([
        'creator_id'  => auth()->id(),
        'assignee_id' => $this->assignee->id,
        'status'      => TaskStatusEnum::Open,
        'priority'    => TaskPriorityEnum::Urgent
    ]);
    $new->update(['title' => 'Renamed right away']);

    runDeferred();

    Notification::assertSentToTimes($this->assignee, TaskSmsNotification::class, 1);
    Notification::assertSentTo($this->assignee, TaskSmsNotification::class, fn(TaskSmsNotification $notification): bool => $notification->from === null);
    Notification::assertSentTo($this->assignee, TaskNotification::class, fn(TaskNotification $notification): bool => $notification->task->is($new) && $notification->taskChanges === []);

    Notification::fake();
    $new->update(['title' => 'Renamed later']);

    runDeferred();

    Notification::assertSentTo($this->assignee, TaskNotification::class);
    Notification::assertSentTimes(TaskSmsNotification::class, 0);
});

it('does not queue the text for a new task with a priority that does not text', function() {
    enableSms();

    FinisterreTask::factory()->create([
        'creator_id'  => auth()->id(),
        'assignee_id' => $this->assignee->id,
        'status'      => TaskStatusEnum::Open,
        'priority'    => TaskPriorityEnum::Low
    ]);

    runDeferred();

    Notification::assertSentTo($this->assignee, TaskNotification::class);
    Notification::assertSentTimes(TaskSmsNotification::class, 0);
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
