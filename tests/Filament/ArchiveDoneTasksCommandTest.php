<?php

use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\FinisterreServiceProvider;
use Arzcode\Finisterre\Models\FinisterreTask;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Workbench\App\Models\User;

beforeEach(function() {
    Notification::fake();

    $this->actingAs(User::factory()->create());
    config()->set('finisterre.auto_archive_after_days', 60);
});

function doneTask(?string $untouchedFor, TaskStatusEnum $status = TaskStatusEnum::Done, bool $completed = true): FinisterreTask
{
    $at = $untouchedFor === null ? now() : now()->sub($untouchedFor);

    return FinisterreTask::factory()->create([
        'status'       => $status,
        'archived'     => false,
        'completed_at' => $completed ? $at : null,
        'updated_at'   => $at,
    ]);
}

it('archives the done tasks untouched for longer than the configured days', function() {
    $old = doneTask('61 days');
    $recent = doneTask('59 days');
    $open = doneTask('90 days', TaskStatusEnum::Open);

    $this->artisan('finisterre:archive-done-tasks')
        ->expectsOutput('Archived 1 task(s).')
        ->assertSuccessful();

    expect($old->refresh()->archived)->toBeTrue()
        ->and($recent->refresh()->archived)->toBeFalse()
        ->and($open->refresh()->archived)->toBeFalse();
});

it('archives an old done task that has no completion date', function() {
    $task = doneTask('90 days', completed: false);

    $this->artisan('finisterre:archive-done-tasks')->assertSuccessful();

    expect($task->refresh()->archived)->toBeTrue();
});

it('does not archive again a task that was unarchived', function() {
    $task = doneTask('90 days');
    $task->update(['archived' => true]);
    $task->update(['archived' => false]);

    $this->artisan('finisterre:archive-done-tasks')
        ->expectsOutput('Archived 0 task(s).')
        ->assertSuccessful();

    expect($task->refresh()->archived)->toBeFalse();
});

it('keeps a done task that got a new comment', function() {
    $task = doneTask('90 days');
    $task->comments()->create(['comment' => 'still relevant', 'creator_id' => auth()->id()]);

    $this->artisan('finisterre:archive-done-tasks')->assertSuccessful();

    expect($task->refresh()->archived)->toBeFalse();
});

it('follows the configured number of days', function() {
    config()->set('finisterre.auto_archive_after_days', 7);

    $task = doneTask('8 days');

    $this->artisan('finisterre:archive-done-tasks')->assertSuccessful();

    expect($task->refresh()->archived)->toBeTrue();
});

it('archives nothing when automatic archiving is turned off', function(?int $days) {
    config()->set('finisterre.auto_archive_after_days', $days);

    $task = doneTask('1 year');

    $this->artisan('finisterre:archive-done-tasks')
        ->expectsOutput('Automatic archiving is turned off.')
        ->assertSuccessful();

    expect($task->refresh()->archived)->toBeFalse();
})->with([
    'empty' => [null],
    'zero'  => [0],
]);

it('is off by default', function() {
    expect(require __DIR__ . '/../../config/finisterre.php')->toHaveKey('auto_archive_after_days', null);
});

it('schedules the command whatever the setting, which it reads when it runs', function() {
    config()->set('finisterre.auto_archive_after_days', null);

    $schedule = new Schedule;
    (fn() => $this->registerSchedule($schedule))->call(new FinisterreServiceProvider(app()));

    $commands = collect($schedule->events())->map(fn($event) => $event->command)->implode(' ');

    expect($commands)->toContain('finisterre:archive-done-tasks');
});
