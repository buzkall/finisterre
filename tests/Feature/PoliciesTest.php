<?php

use Buzkall\Finisterre\Models\FinisterreTask;
use Buzkall\Finisterre\Models\FinisterreTaskComment;
use Buzkall\Finisterre\Policies\FinisterreTaskCommentPolicy;
use Buzkall\Finisterre\Policies\FinisterreTaskPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

beforeEach(function() {
    config([
        'finisterre.active'                     => false,
        'finisterre.table_name'                 => 'finisterre_tasks',
        'finisterre.comments.table_name'        => 'finisterre_task_comments',
        'finisterre.task_changes_table_name'    => 'finisterre_task_changes',
        'finisterre.authenticatable'            => User::class,
        'finisterre.authenticatable_table_name' => 'users',
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

    if (! Schema::hasTable('finisterre_task_comments')) {
        Schema::create('finisterre_task_comments', function(Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
            $table->longText('comment');
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->json('notify_user_ids')->nullable();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    if (! Schema::hasColumn('finisterre_task_comments', 'scheduled_for')) {
        Schema::table('finisterre_task_comments', function(Blueprint $table) {
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->json('notify_user_ids')->nullable();
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

// FinisterreTaskPolicy
it('task policy allows viewing for everyone', function() {
    $user = User::factory()->create();
    $task = FinisterreTask::factory()->create(['creator_id' => $user->id]);

    $policy = new FinisterreTaskPolicy;
    expect($policy->view($user, $task))->toBeTrue();
});

it('task policy allows creating for everyone', function() {
    $user = User::factory()->create();

    $policy = new FinisterreTaskPolicy;
    expect($policy->create($user))->toBeTrue();
});

it('task policy allows updating for everyone', function() {
    $user = User::factory()->create();
    $task = FinisterreTask::factory()->create();

    $policy = new FinisterreTaskPolicy;
    expect($policy->update($user, $task))->toBeTrue();
});

it('task policy allows the creator to delete the task', function() {
    $creator = User::factory()->create();
    $task = FinisterreTask::factory()->create(['creator_id' => $creator->id]);

    $policy = new FinisterreTaskPolicy;
    expect($policy->delete($creator, $task))->toBeTrue();
});

it('task policy forbids non-creator from deleting the task', function() {
    $creator = User::factory()->create();
    $other = User::factory()->create();
    $task = FinisterreTask::factory()->create(['creator_id' => $creator->id]);

    $policy = new FinisterreTaskPolicy;
    expect($policy->delete($other, $task))->toBeFalse();
});

it('task policy forbids restore and forceDelete', function() {
    $user = User::factory()->create();
    $task = FinisterreTask::factory()->create();

    $policy = new FinisterreTaskPolicy;
    expect($policy->restore($user, $task))->toBeFalse()
        ->and($policy->forceDelete($user, $task))->toBeFalse()
        ->and($policy->restoreAny($user))->toBeFalse()
        ->and($policy->forceDeleteAny($user))->toBeFalse()
        ->and($policy->deleteAny($user))->toBeFalse();
});

// FinisterreTaskCommentPolicy
it('comment policy allows view/viewAny/create for everyone', function() {
    $user = User::factory()->create();
    $task = FinisterreTask::factory()->create();
    $comment = $task->comments()->create([
        'comment'    => '<p>x</p>',
        'creator_id' => $user->id,
    ]);

    $policy = new FinisterreTaskCommentPolicy;
    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->view($user, $comment))->toBeTrue()
        ->and($policy->create($user))->toBeTrue();
});

it('comment policy allows creator to delete their own comment', function() {
    $creator = User::factory()->create();
    $task = FinisterreTask::factory()->create();
    $comment = $task->comments()->create([
        'comment'    => '<p>x</p>',
        'creator_id' => $creator->id,
    ]);

    $policy = new FinisterreTaskCommentPolicy;
    expect($policy->delete($creator, $comment))->toBeTrue();
});

it('comment policy forbids non-creator from deleting a comment', function() {
    $creator = User::factory()->create();
    $other = User::factory()->create();
    $task = FinisterreTask::factory()->create();
    $comment = $task->comments()->create([
        'comment'    => '<p>x</p>',
        'creator_id' => $creator->id,
    ]);

    $policy = new FinisterreTaskCommentPolicy;
    expect($policy->delete($other, $comment))->toBeFalse();
});

it('comment policy forbids restore, forceDelete, deleteAny', function() {
    $user = User::factory()->create();
    $task = FinisterreTask::factory()->create();
    $comment = $task->comments()->create([
        'comment'    => '<p>x</p>',
        'creator_id' => $user->id,
    ]);

    $policy = new FinisterreTaskCommentPolicy;
    expect($policy->restore($user, $comment))->toBeFalse()
        ->and($policy->forceDelete($user, $comment))->toBeFalse()
        ->and($policy->restoreAny($user))->toBeFalse()
        ->and($policy->forceDeleteAny($user))->toBeFalse()
        ->and($policy->deleteAny($user))->toBeFalse();
});
