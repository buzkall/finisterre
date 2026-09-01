<?php

use Arzcode\Finisterre\Models\FinisterreSubtask;
use Arzcode\Finisterre\Models\FinisterreTask;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function() {
    // Enable foreign key constraints in SQLite so the cascade delete is exercised.
    // This must not run inside a transaction (i.e. no RefreshDatabase here), or
    // SQLite silently ignores the pragma. TestCase rebuilds the in-memory
    // database for every test anyway.
    Schema::getConnection()->statement('PRAGMA foreign_keys = ON');

    config([
        'finisterre.active'              => false,
        'finisterre.table_name'          => 'finisterre_tasks',
        'finisterre.subtasks.table_name' => 'finisterre_subtasks',
        // This suite covers the model itself; the digest has its own.
        'finisterre.subtasks.notify'            => false,
        'finisterre.task_changes_table_name'    => 'finisterre_task_changes',
        'finisterre.authenticatable_table_name' => 'users',
        'media-library.media_model'             => Media::class,
    ]);

    Schema::create('finisterre_subtasks', function(Blueprint $table) {
        $table->id();
        $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
        $table->string('title');
        $table->boolean('completed')->default(false);
        $table->unsignedInteger('order_column')->nullable();
        $table->timestamps();
    });

    // The task observer records a change row for the assignee on creation.
    Schema::create('finisterre_task_changes', function(Blueprint $table) {
        $table->id();
        $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
        $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
    });

    // Deleting a task runs media-library's cleanup.
    if (! Schema::hasTable('media')) {
        Schema::create('media', function(Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->timestamps();
        });
    }

    // FinisterreTask eager-loads tags.
    if (! Schema::hasTable('tags')) {
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
    }
});

it('returns subtasks ordered by order_column', function() {
    $task = FinisterreTask::factory()->create();

    $task->subtasks()->create(['title' => 'Third', 'order_column' => 30]);
    $task->subtasks()->create(['title' => 'First', 'order_column' => 10]);
    $task->subtasks()->create(['title' => 'Second', 'order_column' => 20]);

    expect($task->subtasks->pluck('title')->all())->toBe(['First', 'Second', 'Third']);
});

it('casts completed to a boolean', function() {
    $task = FinisterreTask::factory()->create();

    $subtask = $task->subtasks()->create(['title' => 'Ship it', 'completed' => 1]);

    expect($subtask->refresh()->completed)->toBeTrue()
        ->and($task->subtasks()->create(['title' => 'Later'])->refresh()->completed)->toBeFalse();
});

it('deletes its subtasks when the task is deleted', function() {
    $task = FinisterreTask::factory()->create();
    $task->subtasks()->create(['title' => 'Orphan me']);

    $task->delete();

    expect(FinisterreSubtask::count())->toBe(0);
});

it('touches the parent task when a subtask changes', function() {
    $task = FinisterreTask::factory()->create();
    $subtask = $task->subtasks()->create(['title' => 'Tick me']);

    $task->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
    $before = $task->fresh()->updated_at;

    $subtask->update(['completed' => true]);

    expect($task->fresh()->updated_at->greaterThan($before))->toBeTrue();
});

it('counts total and completed subtasks for the kanban card', function() {
    $task = FinisterreTask::factory()->create();
    $empty = FinisterreTask::factory()->create();

    $task->subtasks()->create(['title' => 'Done one', 'completed' => true]);
    $task->subtasks()->create(['title' => 'Done two', 'completed' => true]);
    $task->subtasks()->create(['title' => 'Pending', 'completed' => false]);

    $counts = FinisterreTask::query()
        ->withCount([
            'subtasks',
            'subtasks as completed_subtasks_count' => fn($q) => $q->where('completed', true),
        ])
        ->findMany([$task->id, $empty->id])
        ->keyBy('id');

    expect($counts[$task->id]->subtasks_count)->toBe(3)
        ->and($counts[$task->id]->completed_subtasks_count)->toBe(2)
        ->and($counts[$empty->id]->subtasks_count)->toBe(0)
        ->and($counts[$empty->id]->completed_subtasks_count)->toBe(0);
});
