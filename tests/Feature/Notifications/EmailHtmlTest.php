<?php

use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Notifications\ScheduledCommentSentNotification;
use Arzcode\Finisterre\Notifications\TaskCommentNotification;
use Arzcode\Finisterre\Notifications\TaskNotification;
use Arzcode\Finisterre\Support\SafeHtml;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

const EVIL = '<p>Hello</p><script>alert(1)</script><img src="x" onerror="alert(2)"><a href="javascript:alert(3)" onclick="alert(4)">click</a>';

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

    if (! Schema::hasColumn('finisterre_tasks', 'subject_id')) {
        Schema::table('finisterre_tasks', fn(Blueprint $table) => $table->nullableMorphs('subject'));
    }

    if (! Schema::hasTable('finisterre_task_changes')) {
        Schema::create('finisterre_task_changes', function(Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
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

    Route::get('/__test/tasks/{task}', fn() => 'ok')
        ->name('filament.admin.resources.finisterre-tasks.view');
});

function mailHtml(MailMessage $mail): string
{
    return collect($mail->introLines)->map(fn($line) => (string)($line instanceof Stringable ? $line : $line))->implode("\n");
}

function expectClean(string $html): void
{
    expect($html)->toContain('Hello')
        ->not->toContain('<script')
        ->not->toContain('alert(1)')
        ->not->toContain('onerror')
        ->not->toContain('onclick')
        ->not->toContain('javascript:');
}

it('strips scripts from the description in the new task email', function() {
    $task = FinisterreTask::factory()->create(['description' => EVIL, 'creator_id' => User::factory()->create()->id]);

    expectClean(mailHtml((new TaskNotification($task))->toMail(User::factory()->create())));
});

it('strips scripts from the comment in the comment email', function() {
    $task = FinisterreTask::factory()->create(['creator_id' => User::factory()->create()->id]);
    $comment = $task->comments()->create(['comment' => EVIL, 'creator_id' => $task->creator_id]);

    expectClean(mailHtml((new TaskCommentNotification($comment))->toMail(User::factory()->create())));
});

it('strips scripts from the history listed in an email', function() {
    $task = FinisterreTask::factory()->create(['description' => EVIL, 'creator_id' => User::factory()->create()->id]);
    $older = $task->comments()->create(['comment' => EVIL, 'creator_id' => $task->creator_id]);
    $older->forceFill(['created_at' => now()->subDay()])->save();
    $latest = $task->comments()->create(['comment' => '<p>Hello</p>', 'creator_id' => $task->creator_id]);

    expectClean(mailHtml((new TaskCommentNotification($latest))->toMail(User::factory()->create())));
    expectClean(mailHtml((new TaskNotification($task, ['title' => 'x']))->toMail(User::factory()->create())));
});

it('stores descriptions and comments already sanitized', function() {
    $task = FinisterreTask::factory()->create(['description' => EVIL, 'creator_id' => User::factory()->create()->id]);
    $comment = $task->comments()->create(['comment' => EVIL, 'creator_id' => $task->creator_id]);

    expectClean((string)$task->fresh()?->description);
    expectClean((string)$comment->fresh()?->comment);
});

it('sanitizes the descriptions and comments saved before, without touching the tasks', function() {
    $task = FinisterreTask::factory()->create(['creator_id' => User::factory()->create()->id]);
    $comment = $task->comments()->create(['comment' => '<p>Fine</p>', 'creator_id' => $task->creator_id]);

    // Written straight to the table, the way rows saved by an older version are there.
    DB::table('finisterre_tasks')->where('id', $task->id)->update(['description' => EVIL, 'updated_at' => '2026-01-01 00:00:00']);
    DB::table('finisterre_task_comments')->where('id', $comment->id)->update(['comment' => EVIL]);

    (include __DIR__ . '/../../../database/auto/2026_10_16_000000_sanitize_finisterre_rich_text.php')->up();

    expectClean((string)DB::table('finisterre_tasks')->where('id', $task->id)->value('description'));
    expectClean((string)DB::table('finisterre_task_comments')->where('id', $comment->id)->value('comment'));
    expect(DB::table('finisterre_tasks')->where('id', $task->id)->value('updated_at'))->toBe('2026-01-01 00:00:00');
});

it('strips scripts from the comment in the sent confirmation email', function() {
    $task = FinisterreTask::factory()->create(['creator_id' => User::factory()->create()->id]);
    $comment = $task->comments()->create(['comment' => EVIL, 'creator_id' => $task->creator_id, 'scheduled_for' => now()->subMinute()]);

    expectClean(mailHtml((new ScheduledCommentSentNotification($comment))->toMail(User::factory()->create())));
});

it('keeps formatting and links when sanitizing', function() {
    expect(SafeHtml::clean('<p>a <strong>b</strong> <a href="https://example.test/x">c</a></p><ul><li>d</li></ul>'))
        ->toContain('<strong>b</strong>')
        ->toContain('href="https://example.test/x"')
        ->toContain('<li>d</li>')
        ->and(SafeHtml::clean(null))->toBe('');
});

it('turns plain text into escaped paragraphs', function() {
    expect(SafeHtml::fromPlainText("one <b>\ntwo\n\n\nthree & four"))->toBe("<p>one &lt;b&gt;<br>\ntwo</p><p>three &amp; four</p>")
        ->and(SafeHtml::fromPlainText("  \n "))->toBe('');
});
