<?php

use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Models\FinisterreTask;
use Buzkall\Finisterre\Models\FinisterreTaskChange;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

beforeEach(function() {
    config([
        'finisterre.active'                     => false,
        'finisterre.table_name'                 => 'finisterre_tasks',
        'finisterre.task_changes_table_name'    => 'finisterre_task_changes',
        'finisterre.authenticatable'            => User::class,
        'finisterre.authenticatable_table_name' => 'users',
        'finisterre.fallback_notifiable_id'     => null,
        'media-library.media_model'             => Media::class,
    ]);

    if (! Schema::hasTable('finisterre_task_changes')) {
        Schema::create('finisterre_task_changes', function(Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    if (! Schema::hasColumn('finisterre_tasks', 'archived')) {
        Schema::table('finisterre_tasks', function(Blueprint $table) {
            $table->boolean('archived')->default(false);
        });
    }

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
});

it('uses Open as default status when none provided', function() {
    $user = User::factory()->create();

    $task = FinisterreTask::create([
        'title'       => 'No status',
        'description' => 'x',
        'priority'    => 'low',
        'creator_id'  => $user->id,
        'assignee_id' => $user->id,
    ]);

    expect($task->status)->toBe(TaskStatusEnum::Open);
});

it('falls back to configured assignee when none provided', function() {
    $fallback = User::factory()->create();
    config(['finisterre.fallback_notifiable_id' => $fallback->id]);

    $task = FinisterreTask::create([
        'title'    => 'Fallback',
        'priority' => 'low',
        'status'   => TaskStatusEnum::Open->value,
    ]);

    expect($task->assignee_id)->toBe($fallback->id);
});

it('sets completed_at when status transitions to Done', function() {
    $user = User::factory()->create();
    $task = FinisterreTask::factory()->create([
        'creator_id'   => $user->id,
        'assignee_id'  => $user->id,
        'status'       => TaskStatusEnum::Open,
        'completed_at' => null,
    ]);

    $task->status = TaskStatusEnum::Done;
    $task->save();

    expect($task->completed_at)->not->toBeNull();
});

it('clears completed_at when status moves away from Done', function() {
    $user = User::factory()->create();
    $task = FinisterreTask::factory()->create([
        'creator_id'   => $user->id,
        'assignee_id'  => $user->id,
        'status'       => TaskStatusEnum::Done,
        'completed_at' => now(),
    ]);

    $task->status = TaskStatusEnum::Open;
    $task->save();

    expect($task->completed_at)->toBeNull();
});

it('creates a task change for the assignee on creation', function() {
    $user = User::factory()->create();
    $task = FinisterreTask::factory()->create([
        'creator_id'  => $user->id,
        'assignee_id' => $user->id,
    ]);

    expect(FinisterreTaskChange::where('task_id', $task->id)->where('user_id', $user->id)->exists())
        ->toBeTrue();
});

it('exposes notArchived scope', function() {
    $user = User::factory()->create();
    FinisterreTask::factory()->create([
        'creator_id'  => $user->id,
        'assignee_id' => $user->id,
        'archived'    => true,
    ]);
    FinisterreTask::factory()->create([
        'creator_id'  => $user->id,
        'assignee_id' => $user->id,
        'archived'    => false,
    ]);

    expect(FinisterreTask::notArchived()->count())->toBe(1);
});

it('returns creator display name via creatorName()', function() {
    $creator = User::factory()->create(['name' => 'Linus']);
    $task = FinisterreTask::factory()->create([
        'creator_id'  => $creator->id,
        'assignee_id' => $creator->id,
    ]);

    expect($task->creatorName())->toBe('Linus');
});

it('returns N/A when creator is missing', function() {
    $user = User::factory()->create();
    $task = FinisterreTask::factory()->create([
        'creator_id'  => $user->id,
        'assignee_id' => $user->id,
    ]);
    $task->creator_id = null;
    $task->setRelation('creator', null);

    expect($task->creatorName())->toBe('N/A');
});

it('uses configured table name', function() {
    config(['finisterre.table_name' => 'finisterre_tasks']);
    expect((new FinisterreTask)->getTable())->toBe('finisterre_tasks');
});

it('declares correct casts', function() {
    $user = User::factory()->create();
    $task = FinisterreTask::factory()->create([
        'creator_id'  => $user->id,
        'assignee_id' => $user->id,
    ]);

    expect($task->status)->toBeInstanceOf(TaskStatusEnum::class)
        ->and($task->due_at)->toBeInstanceOf(Carbon::class);
});
