<?php

use Arzcode\Finisterre\Filament\Livewire\FinisterreSubtasksComponent;
use Arzcode\Finisterre\Jobs\SendSubtaskChangesNotification;
use Arzcode\Finisterre\Models\FinisterreSubtask;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Notifications\SubtaskChangesNotification;
use Arzcode\Finisterre\Notifications\TaskNotification;
use Arzcode\Finisterre\Observers\FinisterreTaskObserver;
use Arzcode\Finisterre\Policies\FinisterreTaskPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema as DbSchema;
use Workbench\App\Models\User;

beforeEach(function() {
    config([
        // Testbench defaults to the database cache, which has no table here.
        // The unique lock that makes the digest group needs a real lock store.
        'cache.default'                                  => 'array',
        'finisterre.active'                              => false,
        'finisterre.table_name'                          => 'finisterre_tasks',
        'finisterre.subtasks.table_name'                 => 'finisterre_subtasks',
        'finisterre.subtasks.notify'                     => true,
        'finisterre.subtasks.notification_delay_minutes' => 5,
        'finisterre.task_changes_table_name'             => 'finisterre_task_changes',
        'finisterre.authenticatable'                     => User::class,
        'finisterre.panel_slug'                          => 'admin',
    ]);

    Gate::policy(FinisterreTask::class, FinisterreTaskPolicy::class);

    // TestCase deliberately leaves FinisterreServiceProvider unregistered, so the
    // package's own loadTranslationsFrom() never runs and the digest lines would
    // come back as raw keys.
    app('translator')->addNamespace('finisterre', __DIR__ . '/../../resources/lang');

    Route::get('/__test/tasks/{task}', fn() => 'ok')
        ->name('filament.admin.resources.finisterre-tasks.edit');

    DbSchema::create('finisterre_subtasks', function(Blueprint $table) {
        $table->id();
        $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
        $table->string('title');
        $table->boolean('completed')->default(false);
        $table->unsignedInteger('order_column')->nullable();
        $table->timestamps();
    });

    DbSchema::create('finisterre_task_changes', function(Blueprint $table) {
        $table->id();
        $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
        $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
    });

    if (! DbSchema::hasTable('tags')) {
        DbSchema::create('tags', function(Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->string('type')->nullable();
            $table->integer('order_column')->nullable();
            $table->timestamps();
        });

        DbSchema::create('taggables', function(Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
        });
    }

    releaseDigestLocks();

    // Every test here either counts what the observer queued or runs the job by
    // hand, so nothing should ever reach a real queue connection.
    Queue::fake();

    // The factory picks an assignee at random from the users table, so the two
    // roles have to be pinned explicitly or actor and assignee are the same
    // person and every assertion below passes vacuously.
    $this->assignee = User::factory()->create(['name' => 'Assignee']);
    $this->editor = User::factory()->create(['name' => 'Editor']);

    $this->task = FinisterreTask::factory()->create(['assignee_id' => $this->assignee->id]);

    $this->actingAs($this->editor);
});

/**
 * Stand in for a worker picking the digest up, which is what ends a window.
 *
 * Cache::flush() is not enough: the array store keeps locks separately from
 * cached values, so the unique lock survives it.
 */
function releaseDigestLocks(): void
{
    Cache::getStore()->flushLocks();
}

function subtaskChecklist(FinisterreTask $task): FinisterreSubtasksComponent
{
    $component = new FinisterreSubtasksComponent;
    $component->record = $task;
    $component->mount();

    return $component;
}

function addSubtask(FinisterreSubtasksComponent $component, string $title): FinisterreSubtask
{
    $component->newTitle = $title;
    $component->add();

    return $component->record->subtasks()->orderByDesc('id')->first();
}

/**
 * The job the observer actually queued, snapshot and all. Running this rather
 * than a hand-built one is what keeps the observer and the job honest about the
 * shape they pass between them.
 */
function queuedDigest(): SendSubtaskChangesNotification
{
    $pushed = Queue::pushed(SendSubtaskChangesNotification::class);

    expect($pushed)->not->toBeEmpty('no digest was queued');

    return $pushed->first();
}

/** @return list<string> */
function digestEntries(FinisterreTask $task): array
{
    $entries = [];

    Notification::fake();
    queuedDigest()->handle();

    Notification::assertSentTo(
        $task->assignee,
        SubtaskChangesNotification::class,
        function(SubtaskChangesNotification $notification) use (&$entries) {
            $entries = $notification->entries;

            return true;
        }
    );

    return $entries;
}

// ---------------------------------------------------------------- suppression

it('opens no window when the assignee edits their own checklist', function() {
    $this->actingAs($this->assignee);

    addSubtask(subtaskChecklist($this->task), 'Mine to do');

    Queue::assertNothingPushed();
});

it('opens no window when subtask notifications are disabled', function() {
    config(['finisterre.subtasks.notify' => false]);

    addSubtask(subtaskChecklist($this->task), 'Silent');

    Queue::assertNothingPushed();
});

it('opens no window when the task has nobody assigned', function() {
    $this->task->forceFill(['assignee_id' => null])->save();

    addSubtask(subtaskChecklist($this->task), 'Orphan');

    Queue::assertNothingPushed();
});

it('stays silent on a reorder', function() {
    $component = subtaskChecklist($this->task);
    $first = addSubtask($component, 'First');
    $second = addSubtask($component, 'Second');

    // Both resets matter: without the fresh fake the setup's own dispatch would
    // be counted, and without releasing the lock a reorder that DID try to
    // notify would be swallowed by it and pass for the wrong reason.
    releaseDigestLocks();
    Queue::fake();

    $component->reorder([$second->id, $first->id]);

    Queue::assertNothingPushed();
});

// ------------------------------------------------------------------- grouping

it('queues one digest however many subtasks are touched', function() {
    $component = subtaskChecklist($this->task);
    addSubtask($component, 'One');
    addSubtask($component, 'Two');
    addSubtask($component, 'Three');

    Queue::assertPushed(SendSubtaskChangesNotification::class, 1);
});

it('keeps the snapshot from the edit that opened the window', function() {
    $component = subtaskChecklist($this->task);
    $existing = addSubtask($component, 'Already there');

    releaseDigestLocks();
    Queue::fake();

    // This one opens the window, so its "before" is the checklist with only the
    // existing subtask in it.
    addSubtask($component, 'Second');
    addSubtask($component, 'Third');

    expect(queuedDigest()->before)->toBe([
        $existing->id => ['title' => 'Already there', 'completed' => false],
    ]);
});

it('opens a fresh window once the digest has been sent', function() {
    addSubtask(subtaskChecklist($this->task), 'First window');
    Queue::assertPushed(SendSubtaskChangesNotification::class, 1);

    releaseDigestLocks();

    addSubtask(subtaskChecklist($this->task), 'Second window');
    Queue::assertPushed(SendSubtaskChangesNotification::class, 2);
});

it('gives each task its own window', function() {
    $other = FinisterreTask::factory()->create(['assignee_id' => $this->assignee->id]);

    addSubtask(subtaskChecklist($this->task), 'Here');
    addSubtask(subtaskChecklist($other), 'There');

    Queue::assertPushed(SendSubtaskChangesNotification::class, 2);
});

it('delays the digest by the configured number of minutes', function() {
    addSubtask(subtaskChecklist($this->task), 'Later');

    expect(queuedDigest()->delay)->toBe(300);
});

it('honours a changed delay', function() {
    config(['finisterre.subtasks.notification_delay_minutes' => 15]);

    addSubtask(subtaskChecklist($this->task), 'Much later');

    expect(queuedDigest()->delay)->toBe(900);
});

// --------------------------------------------------------- what the digest says

it('reports every subtask added during the window', function() {
    $component = subtaskChecklist($this->task);
    addSubtask($component, 'One');
    addSubtask($component, 'Two');
    addSubtask($component, 'Three');

    $entries = digestEntries($this->task);

    Notification::assertSentTimes(SubtaskChangesNotification::class, 1);
    expect($entries)->toBe(['Added: One', 'Added: Two', 'Added: Three']);
});

it('reports a rename with both titles', function() {
    $component = subtaskChecklist($this->task);
    $subtask = addSubtask($component, 'Old name');

    releaseDigestLocks();
    Queue::fake();

    $component->updatedSubtasks('New name', FinisterreSubtasksComponent::rowKey($subtask->id) . '.title');

    expect(digestEntries($this->task))->toBe(['Renamed: Old name → New name']);
});

it('reports a tick and an untick', function() {
    $component = subtaskChecklist($this->task);
    $subtask = addSubtask($component, 'Ship it');
    $key = FinisterreSubtasksComponent::rowKey($subtask->id);

    releaseDigestLocks();
    Queue::fake();

    $component->updatedSubtasks(true, $key . '.completed');
    expect(digestEntries($this->task))->toBe(['Completed: Ship it']);

    releaseDigestLocks();
    Queue::fake();

    $component->updatedSubtasks(false, $key . '.completed');
    expect(digestEntries($this->task))->toBe(['Reopened: Ship it']);
});

it('reports a deletion by the title the subtask had', function() {
    $component = subtaskChecklist($this->task);
    $subtask = addSubtask($component, 'Scrap this');

    releaseDigestLocks();
    Queue::fake();

    $component->delete(FinisterreSubtasksComponent::rowKey($subtask->id));

    expect(digestEntries($this->task))->toBe(['Deleted: Scrap this'])
        ->and(FinisterreSubtask::find($subtask->id))->toBeNull();
});

it('marks a subtask added and ticked in the same window as done', function() {
    $component = subtaskChecklist($this->task);
    $subtask = addSubtask($component, 'Quick one');
    $component->updatedSubtasks(true, FinisterreSubtasksComponent::rowKey($subtask->id) . '.completed');

    expect(digestEntries($this->task))->toBe(['Added: Quick one (done)']);
});

it('reports an addition that was renamed as a single line', function() {
    $component = subtaskChecklist($this->task);
    $subtask = addSubtask($component, 'Draft');
    $component->updatedSubtasks('Final', FinisterreSubtasksComponent::rowKey($subtask->id) . '.title');

    expect(digestEntries($this->task))->toBe(['Added: Final']);
});

it('reports only the deletion for a subtask renamed then deleted', function() {
    $component = subtaskChecklist($this->task);
    $subtask = addSubtask($component, 'Original');

    releaseDigestLocks();
    Queue::fake();

    $key = FinisterreSubtasksComponent::rowKey($subtask->id);
    $component->updatedSubtasks('Renamed', $key . '.title');
    $component->delete($key);

    expect(digestEntries($this->task))->toBe(['Deleted: Original']);
});

it('reports a rename and a completion on the same subtask separately', function() {
    $component = subtaskChecklist($this->task);
    $subtask = addSubtask($component, 'Before');

    releaseDigestLocks();
    Queue::fake();

    $key = FinisterreSubtasksComponent::rowKey($subtask->id);
    $component->updatedSubtasks('After', $key . '.title');
    $component->updatedSubtasks(true, $key . '.completed');

    expect(digestEntries($this->task))->toBe([
        'Renamed: Before → After',
        'Completed: After',
    ]);
});

it('lists existing subtasks before new ones', function() {
    $component = subtaskChecklist($this->task);
    $existing = addSubtask($component, 'Alpha');

    releaseDigestLocks();
    Queue::fake();

    addSubtask($component, 'Beta');
    $component->updatedSubtasks('Alpha renamed', FinisterreSubtasksComponent::rowKey($existing->id) . '.title');

    expect(digestEntries($this->task))->toBe([
        'Renamed: Alpha → Alpha renamed',
        'Added: Beta',
    ]);
});

// ------------------------------------------------- changes that cancel out

it('says nothing about a subtask added and deleted inside the window', function() {
    $component = subtaskChecklist($this->task);
    $subtask = addSubtask($component, 'Never mind');
    $component->delete(FinisterreSubtasksComponent::rowKey($subtask->id));

    Notification::fake();
    queuedDigest()->handle();

    Notification::assertNothingSent();
});

it('says nothing about a tick that was undone', function() {
    $component = subtaskChecklist($this->task);
    $subtask = addSubtask($component, 'Flip flop');
    $key = FinisterreSubtasksComponent::rowKey($subtask->id);

    releaseDigestLocks();
    Queue::fake();

    $component->updatedSubtasks(true, $key . '.completed');
    $component->updatedSubtasks(false, $key . '.completed');

    Notification::fake();
    queuedDigest()->handle();

    Notification::assertNothingSent();
});

it('says nothing about a rename that was reverted', function() {
    $component = subtaskChecklist($this->task);
    $subtask = addSubtask($component, 'Stable');
    $key = FinisterreSubtasksComponent::rowKey($subtask->id);

    releaseDigestLocks();
    Queue::fake();

    $component->updatedSubtasks('Wobble', $key . '.title');
    $component->updatedSubtasks('Stable', $key . '.title');

    Notification::fake();
    queuedDigest()->handle();

    Notification::assertNothingSent();
});

// --------------------------------------------------------------------- the job

it('does nothing when the task no longer exists', function() {
    Notification::fake();

    (new SendSubtaskChangesNotification(999999))->handle();

    Notification::assertNothingSent();
});

it('does nothing when the checklist ends the window as it started', function() {
    addSubtask(subtaskChecklist($this->task), 'Added then removed');
    $job = queuedDigest();

    $this->task->subtasks()->delete();

    Notification::fake();
    $job->handle();

    Notification::assertNothingSent();
});

it('marks the task as changed for the assignee exactly once', function() {
    $component = subtaskChecklist($this->task);
    addSubtask($component, 'One');
    Notification::fake();
    queuedDigest()->handle();

    releaseDigestLocks();
    Queue::fake();

    addSubtask($component, 'Two');
    queuedDigest()->handle();

    expect($this->task->taskChanges()->where('user_id', $this->assignee->id)->count())->toBe(1);
});

it('sends to the current assignee, not the one at dispatch time', function() {
    addSubtask(subtaskChecklist($this->task), 'Reassigned work');
    $job = queuedDigest();

    $newAssignee = User::factory()->create(['name' => 'New assignee']);
    $this->task->forceFill(['assignee_id' => $newAssignee->id])->save();

    Notification::fake();
    $job->handle();

    Notification::assertSentTo($newAssignee, SubtaskChangesNotification::class);
    Notification::assertNotSentTo($this->assignee, SubtaskChangesNotification::class);
});

// ------------------------------------------------------------ no side effects

it('does not fire a task notification when a subtask changes', function() {
    FinisterreTask::observe(FinisterreTaskObserver::class);
    Notification::fake();

    addSubtask(subtaskChecklist($this->task), 'Just a subtask');

    Notification::assertNotSentTo($this->assignee, TaskNotification::class);
});
