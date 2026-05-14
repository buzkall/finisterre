<?php

use Buzkall\Finisterre\Commands\DispatchScheduledCommentsCommand;
use Buzkall\Finisterre\Models\FinisterreTask;
use Buzkall\Finisterre\Notifications\TaskCommentNotification;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
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
        'finisterre.authenticatable_attribute'  => 'name',
        'finisterre.panel_slug'                 => 'admin',
        'media-library.media_model'             => Media::class,
    ]);

    if (! Schema::hasTable('finisterre_task_comments')) {
        Schema::create('finisterre_task_comments', function(Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
            $table->longText('comment');
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    if (! Schema::hasColumn('finisterre_task_comments', 'scheduled_for')) {
        Schema::table('finisterre_task_comments', function(Blueprint $table) {
            $table->dateTime('scheduled_for')->nullable()->after('comment');
            $table->dateTime('sent_at')->nullable()->after('scheduled_for');
            $table->json('notify_user_ids')->nullable()->after('sent_at');
        });
    }

    if (! Schema::hasTable('finisterre_task_changes')) {
        Schema::create('finisterre_task_changes', function(Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
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

    $this->app[Kernel::class]
        ->registerCommand(new DispatchScheduledCommentsCommand);

    Route::get('/__test/tasks/{task}', fn() => 'ok')
        ->name('filament.admin.resources.finisterre-tasks.edit');

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

function makeTaskWithUsers(): array
{
    $creator = User::factory()->create();
    $assignee = User::factory()->create();
    $task = FinisterreTask::factory()->create([
        'creator_id'  => $creator->id,
        'assignee_id' => $assignee->id,
    ]);

    return [$task, $creator, $assignee];
}

it('persists scheduled_for, sent_at and notify_user_ids', function() {
    [$task, $creator, $assignee] = makeTaskWithUsers();

    $comment = $task->comments()->create([
        'comment'         => '<p>future</p>',
        'creator_id'      => $creator->id,
        'scheduled_for'   => now()->addHour(),
        'notify_user_ids' => [$assignee->id],
    ]);

    expect($comment->scheduled_for)->not->toBeNull()
        ->and($comment->sent_at)->toBeNull()
        ->and($comment->notify_user_ids)->toBe([$assignee->id])
        ->and($comment->isPending())->toBeTrue();
});

it('hides pending scheduled comments from non-creator via visibleTo scope', function() {
    [$task, $creator, $assignee] = makeTaskWithUsers();

    $task->comments()->create([
        'comment'       => '<p>secret</p>',
        'creator_id'    => $creator->id,
        'scheduled_for' => now()->addHour(),
    ]);

    expect($task->comments()->visibleTo($creator->id)->count())->toBe(1)
        ->and($task->comments()->visibleTo($assignee->id)->count())->toBe(0);
});

it('shows delivered scheduled comments to everyone', function() {
    [$task, $creator, $assignee] = makeTaskWithUsers();

    $task->comments()->create([
        'comment'       => '<p>delivered</p>',
        'creator_id'    => $creator->id,
        'scheduled_for' => now()->subHour(),
        'sent_at'       => now(),
    ]);

    expect($task->comments()->visibleTo($creator->id)->count())->toBe(1)
        ->and($task->comments()->visibleTo($assignee->id)->count())->toBe(1);
});

it('shows immediate (non-scheduled) comments to everyone', function() {
    [$task, $creator, $assignee] = makeTaskWithUsers();

    $task->comments()->create([
        'comment'    => '<p>now</p>',
        'creator_id' => $creator->id,
    ]);

    expect($task->comments()->visibleTo($creator->id)->count())->toBe(1)
        ->and($task->comments()->visibleTo($assignee->id)->count())->toBe(1);
});

it('deliver() sends notifications and stamps sent_at', function() {
    Notification::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();
    $extra = User::factory()->create();

    $comment = $task->comments()->create([
        'comment'         => '<p>scheduled body</p>',
        'creator_id'      => $creator->id,
        'scheduled_for'   => now()->subMinute(),
        'notify_user_ids' => [$assignee->id, $extra->id],
    ]);

    $comment->deliver();

    Notification::assertSentTo([$assignee, $extra], TaskCommentNotification::class);
    expect($comment->fresh()->sent_at)->not->toBeNull();
});

it('deliver() creates taskChanges for notified users except creator', function() {
    Notification::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();
    $extra = User::factory()->create();

    // Reset taskChanges created on task creation
    $task->taskChanges()->delete();

    $comment = $task->comments()->create([
        'comment'         => '<p>body</p>',
        'creator_id'      => $creator->id,
        'scheduled_for'   => now()->subMinute(),
        'notify_user_ids' => [$creator->id, $assignee->id, $extra->id],
    ]);

    $comment->deliver();

    $userIds = $task->taskChanges()->pluck('user_id')->all();
    expect($userIds)->toContain($assignee->id, $extra->id)
        ->and($userIds)->not->toContain($creator->id);
});

it('deliver() falls back to task assignee when notify_user_ids is empty', function() {
    Notification::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();

    $comment = $task->comments()->create([
        'comment'    => '<p>fallback</p>',
        'creator_id' => $creator->id,
    ]);

    $comment->deliver();

    Notification::assertSentTo([$assignee], TaskCommentNotification::class);
});

it('dispatch command delivers pending comments whose time has come', function() {
    Notification::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();

    $due = $task->comments()->create([
        'comment'         => '<p>due</p>',
        'creator_id'      => $creator->id,
        'scheduled_for'   => now()->subMinute(),
        'notify_user_ids' => [$assignee->id],
    ]);

    $future = $task->comments()->create([
        'comment'         => '<p>future</p>',
        'creator_id'      => $creator->id,
        'scheduled_for'   => now()->addHour(),
        'notify_user_ids' => [$assignee->id],
    ]);

    $this->artisan(DispatchScheduledCommentsCommand::class)->assertSuccessful();

    expect($due->fresh()->sent_at)->not->toBeNull()
        ->and($future->fresh()->sent_at)->toBeNull();
});

it('dispatch command does not redeliver already-sent comments', function() {
    Notification::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();

    $already = $task->comments()->create([
        'comment'         => '<p>already</p>',
        'creator_id'      => $creator->id,
        'scheduled_for'   => now()->subHour(),
        'sent_at'         => now()->subMinute(),
        'notify_user_ids' => [$assignee->id],
    ]);
    $sentBefore = $already->sent_at;

    $this->artisan(DispatchScheduledCommentsCommand::class)->assertSuccessful();

    expect($already->fresh()->sent_at->toIso8601String())->toBe($sentBefore->toIso8601String());
    Notification::assertNothingSent();
});

it('policy allows creator to update pending comment', function() {
    [$task, $creator] = makeTaskWithUsers();

    $pending = $task->comments()->create([
        'comment'       => '<p>p</p>',
        'creator_id'    => $creator->id,
        'scheduled_for' => now()->addHour(),
    ]);

    expect($creator->can('update', $pending))->toBeTrue();
});

it('policy forbids updating a sent comment', function() {
    [$task, $creator] = makeTaskWithUsers();

    $sent = $task->comments()->create([
        'comment'       => '<p>s</p>',
        'creator_id'    => $creator->id,
        'scheduled_for' => now()->subHour(),
        'sent_at'       => now(),
    ]);

    expect($creator->can('update', $sent))->toBeFalse();
});

it('policy forbids updating someone elses pending comment', function() {
    [$task, $creator] = makeTaskWithUsers();
    $other = User::factory()->create();

    $pending = $task->comments()->create([
        'comment'       => '<p>p</p>',
        'creator_id'    => $creator->id,
        'scheduled_for' => now()->addHour(),
    ]);

    expect($other->can('update', $pending))->toBeFalse();
});

it('policy still allows creator to delete pending comment', function() {
    [$task, $creator] = makeTaskWithUsers();

    $pending = $task->comments()->create([
        'comment'       => '<p>p</p>',
        'creator_id'    => $creator->id,
        'scheduled_for' => now()->addHour(),
    ]);

    expect($creator->can('delete', $pending))->toBeTrue();
});

it('isPending() returns false when sent_at is set', function() {
    [$task, $creator] = makeTaskWithUsers();

    $sent = $task->comments()->create([
        'comment'       => '<p>s</p>',
        'creator_id'    => $creator->id,
        'scheduled_for' => now()->subHour(),
        'sent_at'       => now(),
    ]);

    expect($sent->isPending())->toBeFalse();
});

it('isPending() returns false when there is no scheduled_for', function() {
    [$task, $creator] = makeTaskWithUsers();

    $immediate = $task->comments()->create([
        'comment'    => '<p>now</p>',
        'creator_id' => $creator->id,
    ]);

    expect($immediate->isPending())->toBeFalse();
});
