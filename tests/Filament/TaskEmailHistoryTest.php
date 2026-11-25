<?php

use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Notifications\TaskCommentNotification;
use Arzcode\Finisterre\Notifications\TaskNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
use Workbench\App\Models\User;

beforeEach(function() {
    Notification::fake();

    $this->creator = User::factory()->create(['name' => 'Carla Creator']);
    $this->replier = User::factory()->create(['name' => 'Rita Replier']);

    $this->travelTo(now()->subDays(3));
    $this->task = FinisterreTask::factory()->create([
        'description' => '<p>The description</p>',
        'creator_id'  => $this->creator->id,
    ]);

    $this->travelTo(now()->addDay());
    $this->first = $this->task->comments()->create(['comment' => '<p>First comment</p>', 'creator_id' => $this->creator->id]);

    $this->travelTo(now()->addDay());
    $this->second = $this->task->comments()->create(['comment' => '<p>Second comment</p>', 'creator_id' => $this->replier->id]);

    $this->travelBack();
});

function mailBody(MailMessage $mail): string
{
    return collect($mail->introLines)->map(fn($line) => (string)$line)->implode("\n");
}

it('shows the task history newest first with each author in the changes email', function() {
    $body = mailBody((new TaskNotification($this->task->fresh(), ['status' => 'doing']))->toMail($this->replier));

    expect($body)
        ->toContain(__('finisterre::finisterre.notification.history'))
        ->toContain('Rita Replier')
        ->toContain('Carla Creator')
        ->and(strpos($body, 'Second comment'))->toBeLessThan(strpos($body, 'First comment'))
        ->and(strpos($body, 'First comment'))->toBeLessThan(strpos($body, 'The description'));
});

it('leaves the description out of the history of a new task email, which already shows it', function() {
    $body = mailBody((new TaskNotification($this->task->fresh()))->toMail($this->replier));

    expect(substr_count($body, 'The description'))->toBe(1);
});

it('names the attachments of a new task in its email', function() {
    Storage::fake('public');

    // loaded before the files exist, as it is on the task being created
    $task = $this->task->fresh()->load('media');

    $task->addMediaFromString('contents')->usingFileName('report_v2.pdf')->toMediaCollection('tasks', 'public');
    $task->addMediaFromString('contents')->usingFileName('photo.png')->toMediaCollection('tasks', 'public');
    $task->addMediaFromString('contents')->usingFileName('elsewhere.pdf')->toMediaCollection('other', 'public');

    $body = mailBody((new TaskNotification($task))->toMail($this->replier));

    expect($body)
        ->toContain('<strong>' . __('finisterre::finisterre.attachments') . ' (2):</strong> report_v2.pdf, photo.png')
        ->not->toContain('elsewhere.pdf')
        ->and(strpos($body, 'The description'))->toBeLessThan(strpos($body, 'report_v2.pdf'));
});

it('says nothing about attachments in the email of a new task without them, nor in the changes email', function() {
    Storage::fake('public');

    expect(mailBody((new TaskNotification($this->task->fresh()))->toMail($this->replier)))
        ->not->toContain(__('finisterre::finisterre.attachments'));

    $this->task->addMediaFromString('contents')->usingFileName('report.pdf')->toMediaCollection('tasks', 'public');

    expect(mailBody((new TaskNotification($this->task->fresh(), ['status' => 'doing']))->toMail($this->replier)))
        ->not->toContain('report.pdf');
});

it('leaves the comment itself and anything newer out of the comment email history', function() {
    $body = mailBody((new TaskCommentNotification($this->first))->toMail($this->replier));

    expect(substr_count($body, 'First comment'))->toBe(1)
        ->and($body)->not->toContain('Second comment')
        ->toContain('The description');
});

it('cuts the history to the configured number of entries', function() {
    config(['finisterre.mail.history_entries' => 1]);

    $body = mailBody((new TaskNotification($this->task->fresh(), ['status' => 'doing']))->toMail($this->replier));

    expect($body)->toContain('Second comment')
        ->not->toContain('First comment')
        ->not->toContain('The description');
});

it('leaves the history out when it is set to 0 entries', function() {
    config(['finisterre.mail.history_entries' => 0]);

    $body = mailBody((new TaskNotification($this->task->fresh(), ['status' => 'doing']))->toMail($this->replier));

    expect($body)->not->toContain(__('finisterre::finisterre.notification.history'))
        ->not->toContain('Second comment');
});

it('threads every email about a task under the same root', function() {
    $headersOf = function(MailMessage $mail): array {
        $email = new Email;
        foreach ($mail->callbacks as $callback) {
            $callback($email);
        }

        return [$email->getHeaders()->get('In-Reply-To')?->getBodyAsString(), $email->getHeaders()->get('References')?->getBodyAsString()];
    };

    $taskHeaders = $headersOf((new TaskNotification($this->task->fresh()))->toMail($this->replier));
    $commentHeaders = $headersOf((new TaskCommentNotification($this->second))->toMail($this->creator));

    $threadId = '<finisterre-task-' . $this->task->id . '@' . parse_url(config('app.url'), PHP_URL_HOST) . '>';

    expect($taskHeaders)->toBe([$threadId, $threadId])
        ->and($commentHeaders)->toBe([$threadId, $threadId]);
});

it('keeps only the newest history entry open and folds the older ones into an excerpt', function() {
    $body = mailBody((new TaskNotification($this->task->fresh(), ['status' => 'doing']))->toMail($this->replier));

    expect(substr_count($body, '<details'))->toBe(3)
        ->and(substr_count($body, '<details open'))->toBe(1)
        ->and(strpos($body, '<details open'))->toBeLessThan(strpos($body, 'Second comment'))
        ->and($body)->toContain('&mdash; <span style="color:#9ca3af;">First comment</span>')
        ->not->toContain('&mdash; <span style="color:#9ca3af;">Second comment</span>');
});

it('cuts a long history entry to a short plain text excerpt', function() {
    $this->first->update(['comment' => '<p>' . str_repeat('Lorem &amp; ipsum ', 20) . '</p>']);

    $body = mailBody((new TaskNotification($this->task->fresh(), ['status' => 'doing']))->toMail($this->replier));

    expect($body)->toContain('#9ca3af;">Lorem &amp; ipsum')
        ->toMatch('/#9ca3af;">[^<]+\.\.\.<\/span>/');
});

it('leaves out of the history the entries an empty editor saved with no text', function(string $empty) {
    $this->task->update(['description' => $empty]);
    $this->first->update(['comment' => $empty]);

    $body = mailBody((new TaskNotification($this->task->fresh(), ['status' => 'doing']))->toMail($this->replier));

    expect(substr_count($body, '<details'))->toBe(1)
        ->and($body)->toContain('Second comment');
})->with(['<p></p>', '<p>&nbsp;</p>', "<p><br></p>\n<p> </p>"]);

it('keeps a history entry that only holds an image', function() {
    $this->first->update(['comment' => '<p><img src="https://example.com/a.png"></p>']);

    $body = mailBody((new TaskNotification($this->task->fresh(), ['status' => 'doing']))->toMail($this->replier));

    expect(substr_count($body, '<details'))->toBe(3);
});

it('shows no history when the only entry is an empty description', function() {
    $this->task->comments()->delete();
    $this->task->update(['description' => '<p></p>']);

    $body = mailBody((new TaskNotification($this->task->fresh(), ['status' => 'doing']))->toMail($this->replier));

    expect($body)->not->toContain('<details');
});
