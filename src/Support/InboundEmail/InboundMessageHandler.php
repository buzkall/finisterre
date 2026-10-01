<?php

namespace Arzcode\Finisterre\Support\InboundEmail;

use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Support\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Turns a received email into a comment on the task it answers, whichever driver
 * fetched it. Anything it can't place is skipped and logged, never thrown, so the
 * drivers can mark it handled and move on.
 */
class InboundMessageHandler
{
    public function __construct(private readonly ReplyParser $parser = new ReplyParser) {}

    public function handle(InboundMessage $message): ?FinisterreTaskComment
    {
        if ($this->isAutoResponse($message)) {
            return $this->skip('automatic reply', $message);
        }

        $taskId = ReplyToken::findTaskId([
            $message->header('in-reply-to'),
            $message->header('references'),
            ...$message->recipients,
        ]);

        /** @var FinisterreTask|null $task */
        $task = $taskId === null ? null : FinisterreTask::query()->find($taskId);

        if ($task === null) {
            return $this->skip('not a reply to a task email', $message);
        }

        if (filled($message->messageId) && FinisterreTaskComment::query()->where('email_message_id', $message->messageId)->exists()) {
            return $this->skip('already imported', $message);
        }

        if ($this->failsAuthentication($message)) {
            return $this->skip('sender failed SPF, DKIM or DMARC', $message);
        }

        $sender = $this->sender($message->from);

        if (! $sender instanceof Model
            || Gate::forUser($sender)->denies('view', $task)
            || Gate::forUser($sender)->denies('create', FinisterreTaskComment::class)) {
            return $this->skip('sender is not a user who can comment on the task', $message);
        }

        $body = $this->parser->parse($message->html, $message->text);

        if (blank(strip_tags($body, '<img>'))) {
            return $this->skip('empty reply', $message);
        }

        // The people the email thread is between, but never the one replying: their
        // email client already shows what they wrote.
        $notifyIds = collect([$task->creator_id, $task->assignee_id])
            ->filter()
            ->reject(fn($id) => $id === $sender->getKey())
            ->unique()
            ->values()
            ->all();

        try {
            /** @var FinisterreTaskComment $comment */
            $comment = $task->comments()->create([
                'comment'          => $body,
                'creator_id'       => $sender->getKey(),
                'notify_user_ids'  => $notifyIds,
                'email_message_id' => $message->messageId,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A webhook retry or an overlapping fetch imported it in the meantime.
            return $this->skip('already imported', $message);
        }

        if ($notifyIds !== []) {
            $comment->deliver();
        }

        return $comment;
    }

    private function sender(?string $email): ?Model
    {
        if (blank($email)) {
            return null;
        }

        // Only users who can be picked in the app: inactive or filtered-out users can't comment by email either.
        return UserModel::assignableQuery()->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();
    }

    /**
     * The From header is whatever the sender typed, so a reply is only trusted when the
     * receiving server didn't find it spoofed. Only the topmost Authentication-Results
     * counts, the one the receiving server added; a missing one can't be judged.
     */
    private function failsAuthentication(InboundMessage $message): bool
    {
        $results = strtolower((string)$message->header('authentication-results'));

        if ($results === '') {
            return false;
        }

        // A domain without a DMARC policy (dmarc=none) falls back to SPF and DKIM.
        $dmarc = preg_match('/\bdmarc=(\w+)/', $results, $match) === 1 ? $match[1] : 'none';

        if ($dmarc !== 'none') {
            return $dmarc !== 'pass';
        }

        return preg_match('/\b(spf|dkim)=pass\b/', $results) !== 1;
    }

    /**
     * Out-of-office replies, bounces and list mail, which would otherwise answer
     * every notification with another comment.
     */
    private function isAutoResponse(InboundMessage $message): bool
    {
        $autoSubmitted = strtolower((string)$message->header('auto-submitted'));
        $precedence = strtolower((string)$message->header('precedence'));
        $from = strtolower((string)$message->from);

        return ($autoSubmitted !== '' && $autoSubmitted !== 'no')
            || $message->header('x-autoreply') !== null
            || $message->header('x-autorespond') !== null
            || in_array($precedence, ['bulk', 'junk', 'list', 'auto_reply'], true)
            || str_starts_with($from, 'mailer-daemon@')
            || str_starts_with($from, 'postmaster@')
            || trim((string)$message->header('return-path')) === '<>';
    }

    private function skip(string $reason, InboundMessage $message): null
    {
        Log::info('Finisterre: inbound email skipped, ' . $reason, [
            'message_id' => $message->messageId,
            'from'       => $message->from,
        ]);

        return null;
    }
}
