<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\Models\FinisterreTask;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Relaticle\Flowforge\Board;

uses(RefreshDatabase::class);

beforeEach(function() {
    Notification::fake();

    // Tables the task card relations touch when the observer builds a notification.
    Schema::create('tags', function(Blueprint $table) {
        $table->id();
        $table->json('name');
        $table->json('slug');
        $table->string('type')->nullable();
        $table->integer('order_column')->nullable();
        $table->timestamps();
    });

    Schema::create('taggables', function(Blueprint $table) {
        $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
        $table->morphs('taggable');
    });
});

/** Minimal board so calculateAndUpdatePosition can run outside a Filament panel. */
function kanbanBoardPage(): TasksKanbanBoard
{
    return new class extends TasksKanbanBoard
    {
        public function board(Board $board): Board
        {
            return $board
                ->query(fn() => FinisterreTask::query())
                ->recordTitleAttribute('title')
                ->columnIdentifier('status')
                ->positionIdentifier('order_column');
        }
    };
}

function moveCard(FinisterreTask $card, string $column, ?string $after, ?string $before): void
{
    $page = kanbanBoardPage();

    (fn() => $this->calculateAndUpdatePosition($card, $column, $after, $before))->call($page);
}

function makeTask(TaskStatusEnum $status, int $order, string $updatedAt): FinisterreTask
{
    $task = FinisterreTask::create([
        'title'        => 'Task ' . $order,
        'status'       => $status,
        'priority'     => TaskPriorityEnum::Medium,
        'order_column' => $order,
    ]);

    FinisterreTask::withoutTimestamps(fn() => $task->forceFill(['updated_at' => $updatedAt])->save());

    return $task->refresh();
}

it('does not touch updated_at of the other tasks when a task is moved into their column', function() {
    $first = makeTask(TaskStatusEnum::Doing, 10, '2020-01-01 00:00:00');
    $second = makeTask(TaskStatusEnum::Doing, 20, '2020-01-02 00:00:00');
    $moved = makeTask(TaskStatusEnum::Open, 10, '2020-01-03 00:00:00');

    moveCard($moved, TaskStatusEnum::Doing->value, null, (string)$first->getKey());

    // The renumber pushed both siblings down, without marking them as edited.
    expect($first->refresh()->order_column)->toBe(20)
        ->and($first->updated_at->toDateTimeString())->toBe('2020-01-01 00:00:00')
        ->and($second->refresh()->order_column)->toBe(30)
        ->and($second->updated_at->toDateTimeString())->toBe('2020-01-02 00:00:00');

    // The moved task did change column, so it counts as edited.
    $moved->refresh();
    expect($moved->status)->toBe(TaskStatusEnum::Doing)
        ->and($moved->order_column)->toBe(10)
        ->and($moved->updated_at->toDateTimeString())->not->toBe('2020-01-03 00:00:00');
});

it('does not touch updated_at when a task is only reordered inside its column', function() {
    $first = makeTask(TaskStatusEnum::Doing, 10, '2020-01-01 00:00:00');
    $second = makeTask(TaskStatusEnum::Doing, 20, '2020-01-02 00:00:00');

    moveCard($second, TaskStatusEnum::Doing->value, null, (string)$first->getKey());

    expect($second->refresh()->order_column)->toBe(10)
        ->and($second->updated_at->toDateTimeString())->toBe('2020-01-02 00:00:00')
        ->and($first->refresh()->order_column)->toBe(20)
        ->and($first->updated_at->toDateTimeString())->toBe('2020-01-01 00:00:00');
});

it('moves a task to the top of the done column when it is set to done outside the board', function() {
    $first = makeTask(TaskStatusEnum::Done, 10, '2020-01-01 00:00:00');
    $second = makeTask(TaskStatusEnum::Done, 20, '2020-01-02 00:00:00');
    $moved = makeTask(TaskStatusEnum::Doing, 30, '2020-01-03 00:00:00');

    $moved->update(['status' => TaskStatusEnum::Done]);

    // Slotted in above the first card; the rest of the column did not move.
    expect($moved->order_column)->toBe(9)
        ->and($moved->refresh()->order_column)->toBe(9)
        ->and($first->refresh()->order_column)->toBe(10)
        ->and($first->updated_at->toDateTimeString())->toBe('2020-01-01 00:00:00')
        ->and($second->refresh()->order_column)->toBe(20)
        ->and($second->updated_at->toDateTimeString())->toBe('2020-01-02 00:00:00');
});

it('is the first task of an empty done column', function() {
    $moved = makeTask(TaskStatusEnum::Doing, 30, '2020-01-01 00:00:00');

    $moved->update(['status' => TaskStatusEnum::Done]);

    expect($moved->refresh()->order_column)->toBe(10);
});

it('renumbers the done column when there is no room above its first task', function() {
    $first = makeTask(TaskStatusEnum::Done, 0, '2020-01-01 00:00:00');
    $second = makeTask(TaskStatusEnum::Done, 20, '2020-01-02 00:00:00');
    $moved = makeTask(TaskStatusEnum::Doing, 30, '2020-01-03 00:00:00');

    $moved->update(['status' => TaskStatusEnum::Done]);

    expect($moved->refresh()->order_column)->toBe(10)
        ->and($first->refresh()->order_column)->toBe(20)
        ->and($first->updated_at->toDateTimeString())->toBe('2020-01-01 00:00:00')
        ->and($second->refresh()->order_column)->toBe(30)
        ->and($second->updated_at->toDateTimeString())->toBe('2020-01-02 00:00:00');
});

it('renumbers the done column when its first task has no position at all', function() {
    $first = makeTask(TaskStatusEnum::Done, 20, '2020-01-01 00:00:00');
    FinisterreTask::withoutTimestamps(fn() => $first->forceFill(['order_column' => null])->save());
    $moved = makeTask(TaskStatusEnum::Doing, 30, '2020-01-02 00:00:00');

    $moved->update(['status' => TaskStatusEnum::Done]);

    expect($moved->refresh()->order_column)->toBe(10)
        ->and($first->refresh()->order_column)->toBe(20);
});

it('keeps the drop position when a task is dragged into the done column', function() {
    $first = makeTask(TaskStatusEnum::Done, 10, '2020-01-01 00:00:00');
    $second = makeTask(TaskStatusEnum::Done, 20, '2020-01-02 00:00:00');
    $moved = makeTask(TaskStatusEnum::Doing, 20, '2020-01-03 00:00:00');

    moveCard($moved, TaskStatusEnum::Done->value, (string)$first->getKey(), (string)$second->getKey());

    expect($moved->refresh()->order_column)->toBe(20)
        ->and($first->refresh()->order_column)->toBe(10)
        ->and($second->refresh()->order_column)->toBe(30);
});

it('leaves the position alone when a task is set to a status other than done', function() {
    $first = makeTask(TaskStatusEnum::Doing, 10, '2020-01-01 00:00:00');
    $moved = makeTask(TaskStatusEnum::Open, 30, '2020-01-02 00:00:00');

    $moved->update(['status' => TaskStatusEnum::Doing]);

    expect($moved->refresh()->order_column)->toBe(30)
        ->and($first->refresh()->order_column)->toBe(10);
});
