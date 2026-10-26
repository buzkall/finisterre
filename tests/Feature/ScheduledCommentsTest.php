<?php

use Arzcode\Finisterre\Commands\DispatchScheduledCommentsCommand;
use Arzcode\Finisterre\FinisterreServiceProvider;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Notifications\ScheduledCommentSentNotification;
use Arzcode\Finisterre\Notifications\TaskCommentNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
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
        ->name('filament.admin.resources.finisterre-tasks.view');

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

it('emails the delivered comment body, not the latest comment on the task', function() {
    Notification::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();

    // An older comment that is the one being delivered now
    $older = $task->comments()->create([
        'comment'         => '<p>this is the older comment being delivered</p>',
        'creator_id'      => $creator->id,
        'scheduled_for'   => now()->subMinute(),
        'notify_user_ids' => [$assignee->id],
    ]);

    // A newer comment exists on the task (higher id / latest)
    $task->comments()->create([
        'comment'    => '<p>this is a newer unrelated comment</p>',
        'creator_id' => $creator->id,
    ]);

    $older->deliver();

    Notification::assertSentTo($assignee, TaskCommentNotification::class, function($notification) use ($older) {
        $mail = $notification->toMail($older->task->assignee);

        return collect($mail->introLines)->contains(fn($line) => str_contains($line, 'older comment being delivered'))
            && collect($mail->introLines)->doesntContain(fn($line) => str_contains($line, 'newer unrelated comment'));
    });
});

it('deliver() notifies the creator that their scheduled message has been sent', function() {
    Notification::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();

    $comment = $task->comments()->create([
        'comment'         => '<p>scheduled body</p>',
        'creator_id'      => $creator->id,
        'scheduled_for'   => now()->subMinute(),
        'notify_user_ids' => [$assignee->id],
    ]);

    $comment->deliver();

    Notification::assertSentTo($creator, ScheduledCommentSentNotification::class);
});

it('deliver() does not send the sent-confirmation for immediate comments', function() {
    Notification::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();

    $comment = $task->comments()->create([
        'comment'         => '<p>immediate body</p>',
        'creator_id'      => $creator->id,
        'notify_user_ids' => [$assignee->id],
    ]);

    $comment->deliver();

    Notification::assertNotSentTo($creator, ScheduledCommentSentNotification::class);
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

it('deliver() goes to the task followers, except its author, when it names nobody', function() {
    Notification::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();

    $comment = $task->comments()->create([
        'comment'    => '<p>fallback</p>',
        'creator_id' => $creator->id,
    ]);

    $comment->deliver();

    Notification::assertSentTo([$assignee], TaskCommentNotification::class);
    Notification::assertNotSentTo($creator, TaskCommentNotification::class);
});

it('deliver() notifies nobody when the list of people to notify was emptied', function() {
    Notification::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();

    $comment = $task->comments()->create([
        'comment'         => '<p>quiet</p>',
        'creator_id'      => $creator->id,
        'notify_user_ids' => [],
    ]);

    $comment->deliver();

    Notification::assertNothingSent();
    expect($comment->fresh()->sent_at)->not->toBeNull();
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

it('claim() is won by one caller only', function() {
    [$task, $creator] = makeTaskWithUsers();

    $comment = $task->comments()->create([
        'comment'       => '<p>due</p>',
        'creator_id'    => $creator->id,
        'scheduled_for' => now()->subMinute(),
    ]);

    $first = FinisterreTaskComment::find($comment->id);
    $second = FinisterreTaskComment::find($comment->id);

    expect($first->claim())->toBeTrue()
        ->and($second->claim())->toBeFalse()
        ->and($first->sent_at)->not->toBeNull()
        ->and($comment->fresh()->sent_at)->not->toBeNull();
});

it('deliver() notifies nobody for a comment another run has claimed', function() {
    Notification::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();

    $comment = $task->comments()->create([
        'comment'         => '<p>due</p>',
        'creator_id'      => $creator->id,
        'scheduled_for'   => now()->subMinute(),
        'notify_user_ids' => [$assignee->id],
    ]);

    $stale = FinisterreTaskComment::find($comment->id);
    $comment->claim();

    expect($stale->deliver())->toBeNull();
    Notification::assertNothingSent();
});

it('dispatch command delivers a comment once when the command overlaps itself', function() {
    Notification::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();

    $task->comments()->create([
        'comment'         => '<p>due</p>',
        'creator_id'      => $creator->id,
        'scheduled_for'   => now()->subMinute(),
        'notify_user_ids' => [$assignee->id],
    ]);

    // A second server loads the same comment before the first one has claimed it.
    $loadedByTheOther = FinisterreTaskComment::query()->whereNull('sent_at')->get();

    $this->artisan(DispatchScheduledCommentsCommand::class)->assertSuccessful();

    foreach ($loadedByTheOther as $comment) {
        expect($comment->claim())->toBeFalse();
        $comment->deliver();
    }

    Notification::assertSentToTimes($assignee, TaskCommentNotification::class, 1);
});

it('deliver() stamps the comment and notifies the rest when one recipient fails', function() {
    Exceptions::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();
    $extra = User::factory()->create();

    $dispatcher = Mockery::mock(Dispatcher::class);
    $sentTo = [];
    $dispatcher->shouldReceive('send')->andReturnUsing(function($user, $notification) use ($assignee, &$sentTo) {
        if ($notification instanceof TaskCommentNotification && $user->is($assignee)) {
            throw new RuntimeException('mail is down');
        }

        $sentTo[] = $user->id;
    });
    app()->instance(Dispatcher::class, $dispatcher);

    $comment = $task->comments()->create([
        'comment'         => '<p>due</p>',
        'creator_id'      => $creator->id,
        'scheduled_for'   => now()->subMinute(),
        'notify_user_ids' => [$assignee->id, $extra->id],
    ]);

    $notified = $comment->deliver();

    expect($comment->fresh()->sent_at)->not->toBeNull()
        ->and($notified->pluck('id')->all())->toBe([$extra->id])
        ->and($sentTo)->toContain($extra->id, $creator->id);
    Exceptions::assertReported(RuntimeException::class);

});

it('hands a scheduled comment back for the next run when it fails before anybody is notified', function() {
    Notification::fake();

    [$task, $creator, $assignee] = makeTaskWithUsers();

    $comment = $task->comments()->create([
        'comment'       => '<p>due</p>',
        'creator_id'    => $creator->id,
        'scheduled_for' => now()->subMinute(),
    ]);

    // Looking the recipients up fails, so nobody has been told yet.
    config(['finisterre.authenticatable' => 'Missing\\User']);

    expect(fn() => FinisterreTaskComment::find($comment->id)->deliver())->toThrow(InvalidArgumentException::class)
        ->and($comment->fresh()->sent_at)->toBeNull();

    config(['finisterre.authenticatable' => User::class]);
    $task->follow($assignee);

    $this->artisan(DispatchScheduledCommentsCommand::class)->assertSuccessful();

    expect($comment->fresh()->sent_at)->not->toBeNull();
    Notification::assertSentTo($assignee, TaskCommentNotification::class);
});

it('schedules the package commands to run on one server', function() {
    $schedule = new Schedule;
    config(['finisterre.mail.inbound.enabled' => true, 'finisterre.mail.inbound.driver' => 'imap']);

    (fn() => $this->registerSchedule($schedule))->call(new FinisterreServiceProvider(app()));

    $events = collect($schedule->events());

    expect($events)->toHaveCount(3)
        ->and($events->every(fn($event) => $event->onOneServer))->toBeTrue();
});
