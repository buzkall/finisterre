<?php

use Arzcode\Finisterre\Filament\Livewire\FinisterreSubtasksComponent;
use Arzcode\Finisterre\Models\FinisterreSubtask;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Policies\FinisterreTaskPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema as DbSchema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Workbench\App\Models\User;

class DenyEverythingPolicy
{
    public function update(): bool
    {
        return false;
    }
}

beforeEach(function() {
    config([
        'finisterre.active'                  => false,
        'finisterre.table_name'              => 'finisterre_tasks',
        'finisterre.subtasks.table_name'     => 'finisterre_subtasks',
        'finisterre.task_changes_table_name' => 'finisterre_task_changes',
    ]);

    Gate::policy(FinisterreTask::class, FinisterreTaskPolicy::class);

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

    $this->actingAs(User::factory()->create());
});

/**
 * Drive the component directly rather than through Livewire::test(): rendering
 * it needs a booted Filament panel, which this bare Testbench app cannot give.
 * The wire:model plumbing is framework behaviour; what matters here is that
 * each hook writes to the database straight away.
 */
function subtasksComponent(FinisterreTask $task): FinisterreSubtasksComponent
{
    $component = new FinisterreSubtasksComponent;
    $component->record = $task;
    $component->mount();

    return $component;
}

it('adds a subtask straight to the database', function() {
    $task = FinisterreTask::factory()->create();

    $component = subtasksComponent($task);
    $component->newTitle = '  Buy the domain  ';
    $component->add();

    $subtask = $task->subtasks()->sole();

    expect($subtask->title)->toBe('Buy the domain')
        ->and($subtask->completed)->toBeFalse()
        ->and($subtask->order_column)->toBe(10)
        ->and($component->newTitle)->toBe('')
        ->and($component->subtasks)->toHaveCount(1);
});

it('appends each new subtask after the last one', function() {
    $task = FinisterreTask::factory()->create();
    $task->subtasks()->create(['title' => 'First', 'order_column' => 10]);

    $component = subtasksComponent($task);
    $component->newTitle = 'Second';
    $component->add();

    expect($task->subtasks()->pluck('order_column')->all())->toBe([10, 20]);
});

it('ignores a blank or over-long new subtask', function() {
    $task = FinisterreTask::factory()->create();

    $component = subtasksComponent($task);

    $component->newTitle = '   ';
    $component->add();

    $component->newTitle = str_repeat('a', 256);
    $component->add();

    expect($task->subtasks()->count())->toBe(0);
});

it('persists a tick immediately', function() {
    $task = FinisterreTask::factory()->create();
    $subtask = $task->subtasks()->create(['title' => 'Tick me', 'order_column' => 10]);

    subtasksComponent($task)->updatedSubtasks(true, FinisterreSubtasksComponent::rowKey($subtask->id) . '.completed');

    expect($subtask->refresh()->completed)->toBeTrue();
});

it('persists a rename immediately', function() {
    $task = FinisterreTask::factory()->create();
    $subtask = $task->subtasks()->create(['title' => 'Old', 'order_column' => 10]);

    subtasksComponent($task)->updatedSubtasks('  New  ', FinisterreSubtasksComponent::rowKey($subtask->id) . '.title');

    expect($subtask->refresh()->title)->toBe('New');
});

it('restores the stored title when it is emptied', function() {
    $task = FinisterreTask::factory()->create();
    $subtask = $task->subtasks()->create(['title' => 'Keep me', 'order_column' => 10]);

    $component = subtasksComponent($task);
    $component->updatedSubtasks('', FinisterreSubtasksComponent::rowKey($subtask->id) . '.title');

    expect($subtask->refresh()->title)->toBe('Keep me')
        ->and($component->subtasks[FinisterreSubtasksComponent::rowKey($subtask->id)]['title'])->toBe('Keep me');
});

it('deletes a subtask immediately', function() {
    $task = FinisterreTask::factory()->create();
    $task->subtasks()->create(['title' => 'Remove me', 'order_column' => 10]);

    $subtask = $task->subtasks()->sole();

    subtasksComponent($task)->delete(FinisterreSubtasksComponent::rowKey($subtask->id));

    expect($task->subtasks()->count())->toBe(0);
});

it('renumbers on reorder', function() {
    $task = FinisterreTask::factory()->create();
    $first = $task->subtasks()->create(['title' => 'First', 'order_column' => 10]);
    $second = $task->subtasks()->create(['title' => 'Second', 'order_column' => 20]);

    subtasksComponent($task)->reorder([$second->id, $first->id]);

    expect($task->subtasks()->pluck('title')->all())->toBe(['Second', 'First'])
        ->and($task->subtasks()->pluck('order_column')->all())->toBe([10, 20]);
});

it('never touches a subtask belonging to another task', function() {
    $task = FinisterreTask::factory()->create();
    $other = FinisterreTask::factory()->create();
    $foreign = $other->subtasks()->create(['title' => 'Not yours', 'order_column' => 10]);

    // Rows are addressed through this component's own keyed array, so a
    // foreign id is unreachable through delete()...
    subtasksComponent($task)->delete(FinisterreSubtasksComponent::rowKey($foreign->id));

    // ...and reorder() filters the ids down to the ones this task owns.
    subtasksComponent($task)->reorder([$foreign->id]);

    expect(FinisterreSubtask::whereKey($foreign->id)->exists())->toBeTrue()
        ->and($foreign->refresh()->order_column)->toBe(10);
});

it('refuses every mutation when the user cannot update the task', function() {
    $task = FinisterreTask::factory()->create();
    $subtask = $task->subtasks()->create(['title' => 'Locked', 'order_column' => 10]);

    Gate::policy(FinisterreTask::class, DenyEverythingPolicy::class);

    $component = subtasksComponent($task);
    $component->newTitle = 'Nope';

    expect(fn() => $component->add())->toThrow(HttpException::class)
        ->and(fn() => $component->delete(FinisterreSubtasksComponent::rowKey($subtask->id)))->toThrow(HttpException::class)
        ->and(fn() => $component->updatedSubtasks(true, FinisterreSubtasksComponent::rowKey($subtask->id) . '.completed'))->toThrow(HttpException::class)
        ->and(fn() => $component->reorder([$subtask->id]))->toThrow(HttpException::class);

    expect($task->subtasks()->count())->toBe(1)
        ->and($subtask->refresh()->completed)->toBeFalse();
});

it('keeps the remaining rows intact when one in the middle is deleted', function() {
    $task = FinisterreTask::factory()->create();
    $first = $task->subtasks()->create(['title' => 'First', 'order_column' => 10]);
    $middle = $task->subtasks()->create(['title' => 'Middle', 'order_column' => 20]);
    $last = $task->subtasks()->create(['title' => 'Last', 'completed' => true, 'order_column' => 30]);

    $component = subtasksComponent($task);
    $component->delete(FinisterreSubtasksComponent::rowKey($middle->id));

    // No blank leftover row, and every survivor keeps its own title and state.
    expect($component->subtasks)->toHaveCount(2)
        ->and(array_keys($component->subtasks))->toBe([
            FinisterreSubtasksComponent::rowKey($first->id),
            FinisterreSubtasksComponent::rowKey($last->id),
        ])
        ->and($component->subtasks[FinisterreSubtasksComponent::rowKey($first->id)]['title'])->toBe('First')
        ->and($component->subtasks[FinisterreSubtasksComponent::rowKey($last->id)]['title'])->toBe('Last')
        ->and($component->subtasks[FinisterreSubtasksComponent::rowKey($last->id)]['completed'])->toBeTrue();

    // The surviving rows are still addressable by the same keys afterwards.
    $component->updatedSubtasks('Renamed', FinisterreSubtasksComponent::rowKey($last->id) . '.title');

    expect($last->refresh()->title)->toBe('Renamed')
        ->and($first->refresh()->title)->toBe('First');
});

it('keys rows so the browser cannot re-sort them numerically', function() {
    $task = FinisterreTask::factory()->create();
    $second = $task->subtasks()->create(['title' => 'Shown second', 'order_column' => 20]);
    $first = $task->subtasks()->create(['title' => 'Shown first', 'order_column' => 10]);

    $keys = array_keys(subtasksComponent($task)->subtasks);

    // Display order is by order_column, not by id — and the keys are
    // non-numeric so JSON serialisation cannot reorder them.
    expect($keys)->toBe([
        FinisterreSubtasksComponent::rowKey($first->id),
        FinisterreSubtasksComponent::rowKey($second->id),
    ])->and($keys[0])->not->toBeNumeric();
});

it('reports the done and total counts for the section header badge', function() {
    $task = FinisterreTask::factory()->create();
    $task->subtasks()->create(['title' => 'Done', 'completed' => true, 'order_column' => 10]);
    $pending = $task->subtasks()->create(['title' => 'Pending', 'order_column' => 20]);

    $component = subtasksComponent($task);

    expect($component->counts())->toBe(['done' => 1, 'total' => 2]);

    $component->updatedSubtasks(true, FinisterreSubtasksComponent::rowKey($pending->id) . '.completed');

    expect($component->counts())->toBe(['done' => 2, 'total' => 2]);
});

it('reports zero counts when the task has no subtasks', function() {
    expect(subtasksComponent(FinisterreTask::factory()->create())->counts())
        ->toBe(['done' => 0, 'total' => 0]);
});

it('refreshes the task timestamp on reorder, like every other edit', function() {
    $task = FinisterreTask::factory()->create();
    $first = $task->subtasks()->create(['title' => 'First', 'order_column' => 10]);
    $second = $task->subtasks()->create(['title' => 'Second', 'order_column' => 20]);

    $task->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
    $before = $task->fresh()->updated_at;

    subtasksComponent($task->fresh())->reorder([$second->id, $first->id]);

    expect($task->fresh()->updated_at->greaterThan($before))->toBeTrue();
});

it('does not touch the task when a reorder resolves to nothing', function() {
    $task = FinisterreTask::factory()->create();
    $other = FinisterreTask::factory()->create();
    $foreign = $other->subtasks()->create(['title' => 'Not yours', 'order_column' => 10]);

    $task->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
    $before = $task->fresh()->updated_at;

    subtasksComponent($task->fresh())->reorder([$foreign->id]);

    expect($task->fresh()->updated_at->equalTo($before))->toBeTrue();
});
