<?php

use Arzcode\Finisterre\Controllers\ResendInboundController;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Notifications\TaskCommentNotification;
use Arzcode\Finisterre\Notifications\TaskNotification;
use Arzcode\Finisterre\Policies\FinisterreTaskCommentPolicy;
use Arzcode\Finisterre\Support\InboundEmail\AuthenticationResults;
use Arzcode\Finisterre\Support\InboundEmail\InboundMessage;
use Arzcode\Finisterre\Support\InboundEmail\InboundMessageHandler;
use Arzcode\Finisterre\Support\InboundEmail\ReplyParser;
use Arzcode\Finisterre\Support\InboundEmail\ReplyToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mime\Email;
use Workbench\App\Models\User;

class NoCreateCommentPolicy extends FinisterreTaskCommentPolicy
{
    public function create($user): bool
    {
        return false;
    }
}

beforeEach(function() {
    Notification::fake();

    config([
        'finisterre.mail.inbound.enabled'       => true,
        'finisterre.mail.inbound.reply_address' => 'tasks@example.com',
    ]);

    $this->creator = User::factory()->create(['email' => 'carla@example.com']);
    $this->assignee = User::factory()->create(['email' => 'rita@example.com']);

    $this->task = FinisterreTask::factory()->create([
        'creator_id'  => $this->creator->id,
        'assignee_id' => $this->assignee->id,
    ]);
});

function sentHeaders(MailMessage $mail): Email
{
    $email = new Email;

    foreach ($mail->callbacks as $callback) {
        $callback($email);
    }

    return $email;
}

function replyTo(FinisterreTask $task, array $overrides = []): InboundMessage
{
    return new InboundMessage(...array_merge([
        'messageId'  => 'reply-' . uniqid() . '@mail.example.com',
        'from'       => 'Rita@Example.com',
        'recipients' => ['tasks@example.com'],
        'html'       => '<div>Done, see you tomorrow</div><div class="gmail_quote">On Monday Carla wrote: the old email</div>',
        'text'       => '',
        'headers'    => ['in-reply-to' => '<' . ReplyToken::messageId($task->id) . '>'],
    ], $overrides));
}

it('sends task emails with the reply address, a signed Message-ID and the reply line', function() {
    $mail = (new TaskNotification($this->task->fresh()))->toMail($this->assignee);
    $messageId = sentHeaders($mail)->getHeaders()->get('Message-ID')->getBodyAsString();
    $html = (string)$mail->render();

    expect($mail->replyTo)->toBe([['tasks@example.com', null]])
        ->and(ReplyToken::findTaskId([$messageId]))->toBe($this->task->id)
        ->and($html)->toContain(__('finisterre::finisterre.mail.reply_above'))
        // Above the logo: a reply quotes the email from its top, right under what was written.
        ->and(strpos($html, 'finisterre-reply-above'))->toBeLessThan(strpos($html, 'class="header"'));
});

it('puts the signed task in the reply address with plus addressing', function() {
    config(['finisterre.mail.inbound.plus_addressing' => true]);

    $comment = $this->task->comments()->create(['comment' => '<p>Hi</p>', 'creator_id' => $this->creator->id]);
    $replyTo = (new TaskCommentNotification($comment))->toMail($this->assignee)->replyTo[0][0];

    expect($replyTo)->toStartWith('tasks+' . $this->task->id . '-')->toEndWith('@example.com')
        ->and(ReplyToken::findTaskId([$replyTo]))->toBe($this->task->id);
});

it('leaves task emails untouched while reply by email is off', function() {
    config(['finisterre.mail.inbound.enabled' => false]);

    $mail = (new TaskNotification($this->task->fresh()))->toMail($this->assignee);

    expect($mail->replyTo)->toBe([])
        ->and(sentHeaders($mail)->getHeaders()->has('Message-ID'))->toBeFalse()
        ->and((string)$mail->render())->not->toContain(__('finisterre::finisterre.mail.reply_above'));
});

it('rejects a task id whose signature was not made with the app key', function() {
    $forged = str_replace('finisterre-' . $this->task->id . '-', 'finisterre-' . ($this->task->id + 1) . '-', ReplyToken::messageId($this->task->id));

    expect(ReplyToken::findTaskId([$forged]))->toBeNull();
});

it('turns a reply into a comment by its author and notifies the other side of the thread', function() {
    $comment = app(InboundMessageHandler::class)->handle(replyTo($this->task));

    expect($comment)->toBeInstanceOf(FinisterreTaskComment::class)
        ->and($comment->creator_id)->toBe($this->assignee->id)
        ->and($comment->comment)->toContain('Done, see you tomorrow')->not->toContain('the old email');

    Notification::assertSentTo($this->creator, TaskCommentNotification::class);
    Notification::assertNotSentTo($this->assignee, TaskCommentNotification::class);
});

it('finds the task in a plus-addressed recipient when the Message-ID was rewritten', function() {
    config(['finisterre.mail.inbound.plus_addressing' => true]);

    $comment = app(InboundMessageHandler::class)->handle(replyTo($this->task, [
        'recipients' => [ReplyToken::replyAddress($this->task->id)],
        'headers'    => ['in-reply-to' => '<rewritten@amazonses.com>'],
    ]));

    expect($comment?->task_id)->toBe($this->task->id);
});

it('imports the same email only once', function() {
    $reply = replyTo($this->task);

    app(InboundMessageHandler::class)->handle($reply);
    app(InboundMessageHandler::class)->handle($reply);

    expect($this->task->comments()->count())->toBe(1);
});

it('skips replies it cannot trust', function(array $overrides) {
    expect(app(InboundMessageHandler::class)->handle(replyTo($this->task, $overrides)))->toBeNull()
        ->and($this->task->comments()->count())->toBe(0);
})->with([
    'an unknown sender' => [['from' => 'stranger@example.com']],
    'no task reference' => [['headers' => ['in-reply-to' => '<something-else@example.com>']]],
    'an out-of-office'  => [['headers' => ['auto-submitted' => 'auto-replied']]],
    'an empty reply'    => [['html' => '<div class="gmail_quote">only the quote</div>']],
    'a spoofed sender'  => [['headers' => ['authentication-results' => 'mx.example.com; spf=pass smtp.mailfrom=evil.test; dmarc=fail header.from=example.com']]],
    'no passing check'  => [['headers' => ['authentication-results' => 'mx.example.com; spf=softfail; dkim=none']]],
]);

it('takes replies the receiving server authenticated', function(string $results) {
    $reply = replyTo($this->task);
    $reply = replyTo($this->task, ['headers' => [...$reply->headers, 'authentication-results' => $results]]);

    expect(app(InboundMessageHandler::class)->handle($reply))->toBeInstanceOf(FinisterreTaskComment::class);
})->with([
    'dmarc pass'           => 'mx.example.com; dkim=pass header.d=example.com; dmarc=pass header.from=example.com',
    'no dmarc, spf passed' => 'mx.example.com; spf=pass smtp.mailfrom=example.com; dmarc=none',
    'several headers'      => AuthenticationResults::ofReceivingServer([
        'mx.example.com; x-csa=none; x-ptr=pass smtp.helo=mail.example.com',
        'mx.example.com; dkim=pass header.d=example.com; dmarc=pass header.from=example.com',
    ]),
]);

it('skips replies from users the comment policy does not let comment', function() {
    Gate::policy(FinisterreTaskComment::class, NoCreateCommentPolicy::class);

    expect(app(InboundMessageHandler::class)->handle(replyTo($this->task)))->toBeNull();
});

it('skips replies from users outside the assignable users', function() {
    if (! Schema::hasColumn('users', 'role')) {
        Schema::table('users', function(Blueprint $table) {
            $table->string('role')->nullable();
        });
    }

    config([
        'finisterre.authenticatable_filter_column' => 'role',
        'finisterre.authenticatable_filter_value'  => 'admin',
    ]);

    expect(app(InboundMessageHandler::class)->handle(replyTo($this->task)))->toBeNull();

    $this->assignee->forceFill(['role' => 'admin'])->save();

    expect(app(InboundMessageHandler::class)->handle(replyTo($this->task)))->toBeInstanceOf(FinisterreTaskComment::class);
});

it('skips an email another request imported while it was being handled', function() {
    $reply = replyTo($this->task);

    // What a webhook retry running at the same time leaves behind, after the "already imported" check.
    FinisterreTaskComment::creating(function() use ($reply) {
        FinisterreTaskComment::flushEventListeners();
        DB::table('finisterre_task_comments')->insert([
            'task_id'          => $this->task->id,
            'comment'          => '<p>Done</p>',
            'creator_id'       => $this->assignee->id,
            'email_message_id' => $reply->messageId,
        ]);
    });

    expect(app(InboundMessageHandler::class)->handle($reply))->toBeNull()
        ->and($this->task->comments()->count())->toBe(1);

    Notification::assertNothingSent();
});

it('strips scripts and event handlers from the reply', function() {
    $body = (new ReplyParser)->parse('<p onclick="steal()">Hello<script>alert(1)</script></p>', '');

    expect($body)->toBe('<p>Hello</p>');
});

it('falls back to the plain text part and cuts the quoted email', function() {
    $body = (new ReplyParser)->parse('', "Sounds good\n\nOn Mon, 28 Sep 2026 Carla wrote:\n> the old email");

    expect($body)->toBe('<p>Sounds good</p>');
});

it('keeps a plain text reply that starts with a quoted line or mentions a From: line', function() {
    $body = (new ReplyParser)->parse('', "> Can you check it?\nChecked, it works\nDe: Carla, to see it tomorrow\nThanks");

    expect($body)->toBe('<p>Checked, it works<br /> De: Carla, to see it tomorrow<br /> Thanks</p>');
});

it('cuts a plain text reply at the headers of the quoted email', function() {
    $body = (new ReplyParser)->parse('', "Done\n\nFrom: Carla <carla@example.com>\nSent: Monday\nSubject: the old email");

    expect($body)->toBe('<p>Done</p>');
});

it('cuts a reply at the reply line of the email it answers, in any language', function() {
    $html = '<p>Hecho</p><div>' . __('finisterre::finisterre.mail.reply_above', [], 'es') . '</div><p>the old email</p>';

    expect((new ReplyParser)->parse($html, ''))->toBe('<p>Hecho</p>');
});

it('keeps only the words of a reply, without the markup mail clients leave around them', function(string $html, string $expected) {
    expect((new ReplyParser)->parse($html, ''))->toBe($expected);
})->with([
    // Proton Mail: an indented, empty signature block, which markdown emails would show as code.
    'an indented empty signature' => [
        "<div style=\"font-size: 14px;\">A la tercera</div>\r\n<div class=\"protonmail_signature_block\">\r\n    <div>\r\n        \r\n            </div>\r\n</div>\r\n<div><br></div><div class=\"protonmail_quote\">the old email</div>",
        '<div>A la tercera</div>',
    ],
    // Fastmail: the line that introduces the quote sits above it, not inside it.
    'the line above the quote' => [
        '<div>Hecho</div><div><br></div><div>On Thu, Oct 1, 2026, at 13:48, arzcode wrote:</div><blockquote type="cite">the old email</blockquote>',
        '<div>Hecho</div>',
    ],
    'the same line in Spanish' => [
        '<p>Hecho</p><p>El jue, 1 oct 2026, a las 13:48, arzcode escribió:</p><blockquote type="cite">the old email</blockquote>',
        '<p>Hecho</p>',
    ],
    'the line breaks of a code block' => [
        "<p>Mira:</p>\n<pre>if (true) {\n    run();\n}</pre>",
        "<p>Mira:</p> <pre>if (true) {\n    run();\n}</pre>",
    ],
]);

describe('resend webhook', function() {
    beforeEach(function() {
        config([
            'finisterre.mail.inbound.driver'                => 'resend',
            'finisterre.mail.inbound.resend.api_key'        => 're_test',
            'finisterre.mail.inbound.resend.webhook_secret' => 'whsec_' . base64_encode('the-secret'),
        ]);

        $this->event = json_encode(['type' => 'email.received', 'data' => ['email_id' => 'abc-123']]);
    });

    function postResendEvent(string $body, string $key = 'the-secret')
    {
        $id = 'msg_1';
        $timestamp = (string)time();
        $signature = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));

        return test()->call('POST', ResendInboundController::PATH, [], [], [], [
            'CONTENT_TYPE'        => 'application/json',
            'HTTP_SVIX_ID'        => $id,
            'HTTP_SVIX_TIMESTAMP' => $timestamp,
            'HTTP_SVIX_SIGNATURE' => 'v1,' . $signature,
        ], $body);
    }

    it('fetches the received email from resend and turns it into a comment', function() {
        Http::fake(['api.resend.com/emails/receiving/abc-123' => Http::response([
            'from'       => 'Rita <rita@example.com>',
            'to'         => ['tasks@example.com'],
            'message_id' => '<resend-1@mail.example.com>',
            'html'       => '<p>On it</p>',
            'text'       => null,
            'headers'    => ['In-Reply-To' => '<' . ReplyToken::messageId($this->task->id) . '>'],
        ])]);

        postResendEvent($this->event)->assertNoContent();

        expect($this->task->comments()->sole())
            ->comment->toBe('<p>On it</p>')
            ->creator_id->toBe($this->assignee->id)
            ->email_message_id->toBe('resend-1@mail.example.com');

        Http::assertSent(fn($request) => $request->hasHeader('Authorization', 'Bearer re_test'));
    });

    it('refuses a request signed with another secret', function() {
        Http::fake();

        postResendEvent($this->event, 'another-secret')->assertUnauthorized();

        Http::assertNothingSent();
    });

    it('is not there while the resend driver is off', function() {
        config(['finisterre.mail.inbound.driver' => 'imap']);

        postResendEvent($this->event)->assertNotFound();
    });
});
