<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\TaskFollowers;
use Workbench\App\Models\User;

/**
 * Makes the app look like a queue worker or scheduler: running in the console, and not
 * in the tests, which is what the model used to look at.
 */
function runAsConsoleOutsideTests(): void
{
    app()->detectEnvironment(fn(): string => 'production');
    FinisterreTask::clearBootedModels();

    expect(app()->runningInConsole())->toBeTrue()
        ->and(app()->runningUnitTests())->toBeFalse();
}

afterEach(function() {
    app()->detectEnvironment(fn(): string => 'testing');
    FinisterreTask::clearBootedModels();
});

it('prepares a task created from the console the way the panel does', function() {
    $fallback = User::factory()->create();
    config(['finisterre.fallback_notifiable_id' => $fallback->id]);

    runAsConsoleOutsideTests();

    $task = FinisterreTask::create(['title' => 'From a job', 'description' => '<p>x</p>', 'priority' => TaskPriorityEnum::Low, 'order_column' => 0]);

    expect($task->status)->toBe(TaskStatusEnum::Open)
        ->and($task->assignee_id)->toBe($fallback->id)
        ->and($task->taskChanges()->where('user_id', $fallback->id)->exists())->toBeTrue()
        ->and(TaskFollowers::ids($task))->toContain($fallback->id);

    $task->update(['status' => TaskStatusEnum::Done]);

    expect($task->fresh()->completed_at)->not->toBeNull();
});

it('does not hide tasks from a job when nobody is signed in', function() {
    FinisterrePlugin::get()->userCanViewOnlyTheirTasks(fn() => true);
    FinisterreTask::factory()->count(2)->create();

    runAsConsoleOutsideTests();

    expect(auth()->guest())->toBeTrue()
        ->and(FinisterreTask::count())->toBe(2);
});

it('still limits the tasks of a signed-in user', function() {
    FinisterrePlugin::get()->userCanViewOnlyTheirTasks(fn() => true);
    $other = User::factory()->create();
    FinisterreTask::factory()->count(2)->create(['creator_id' => $other->id, 'assignee_id' => $other->id]);

    $this->actingAs(User::factory()->create());
    runAsConsoleOutsideTests();

    expect(FinisterreTask::count())->toBe(0);
});
