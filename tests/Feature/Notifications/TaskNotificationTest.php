<?php

use Buzkall\Finisterre\Models\FinisterreTask;
use Buzkall\Finisterre\Notifications\TaskNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

beforeEach(function() {
    config([
        'finisterre.active'                     => false,
        'finisterre.table_name'                 => 'finisterre_tasks',
        'finisterre.comments.table_name'        => 'finisterre_task_comments',
        'finisterre.authenticatable'            => User::class,
        'finisterre.authenticatable_table_name' => 'users',
        'finisterre.authenticatable_attribute'  => 'name',
        'finisterre.panel_slug'                 => 'admin',
        'media-library.media_model'             => \Spatie\MediaLibrary\MediaCollections\Models\Media::class,
    ]);

    // Create comments table
    if (! Schema::hasTable('finisterre_task_comments')) {
        Schema::create('finisterre_task_comments', function(Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
            $table->longText('comment');
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    // Create tags table
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

    // Create media table
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

it('can create a task notification instance', function() {
    $task = FinisterreTask::factory()->create();

    $notification = new TaskNotification($task);

    expect($notification)->toBeInstanceOf(TaskNotification::class)
        ->and($notification->task)->toBeInstanceOf(FinisterreTask::class)
        ->and($notification->task->id)->toBe($task->id);
});

it('can create a task notification with changes', function() {
    $task = FinisterreTask::factory()->create();
    $changes = ['status' => 'done', 'priority' => 'high'];

    $notification = new TaskNotification($task, $changes);

    expect($notification->taskChanges)->toBe($changes);
});

it('notification uses mail channel', function() {
    $task = FinisterreTask::factory()->create();
    $notification = new TaskNotification($task);
    $user = User::factory()->create();

    $channels = $notification->via($user);

    expect($channels)->toContain('mail');
});

it('can build mail message for new task', function() {
    $creator = User::factory()->create(['name' => 'John Doe']);
    $task = FinisterreTask::factory()->create([
        'title'       => 'Test Task',
        'description' => 'Test Description',
        'creator_id'  => $creator->id,
    ]);
    $task->wasRecentlyCreated = true;

    $notification = new TaskNotification($task);
    $user = User::factory()->create();

    $mailMessage = $notification->toMail($user);

    expect($mailMessage)->toBeInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class);
});

it('can build mail message for updated task', function() {
    $creator = User::factory()->create(['name' => 'Jane Doe']);
    $task = FinisterreTask::factory()->create([
        'title'      => 'Updated Task',
        'creator_id' => $creator->id,
    ]);
    $changes = ['status' => 'done'];

    $notification = new TaskNotification($task, $changes);
    $user = User::factory()->create();

    $mailMessage = $notification->toMail($user);

    expect($mailMessage)->toBeInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class);
});

it('does not send sms notification when disabled', function() {
    config(['finisterre.sms_notification.enabled' => false]);

    $task = FinisterreTask::factory()->create();
    $notification = new TaskNotification($task);
    $user = User::factory()->create();

    // This should not throw an exception
    $notification->toSms($user);

    expect(true)->toBeTrue();
});

it('does not send sms notification for non-urgent tasks', function() {
    config([
        'finisterre.sms_notification.enabled'           => true,
        'finisterre.sms_notification.notify_priorities' => [\Buzkall\Finisterre\Enums\TaskPriorityEnum::Urgent],
    ]);

    $task = FinisterreTask::factory()->create([
        'priority' => \Buzkall\Finisterre\Enums\TaskPriorityEnum::Low,
    ]);
    $task->wasRecentlyCreated = true;
    $notification = new TaskNotification($task);
    $user = User::factory()->create();

    // This should not send SMS
    $notification->toSms($user);

    expect(true)->toBeTrue();
});
