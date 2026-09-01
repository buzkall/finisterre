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
