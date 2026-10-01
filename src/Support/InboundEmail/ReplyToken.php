<?php

namespace Arzcode\Finisterre\Support\InboundEmail;

use Arzcode\Finisterre\Support\Typed;
use Illuminate\Support\Str;

/**
 * The signed task id that routes an emailed reply back to its task.
 *
 * It travels in the Message-ID of every task email (finisterre-12-3f9a…-random@host),
 * which mail clients copy into the In-Reply-To and References of a reply, and, with
 * plus addressing, in the Reply-To address (tasks+12-3f9a…@example.com). The hash is
 * an HMAC of the task id keyed by the app key, so a reply can't be aimed at another
 * task by editing the id.
 */
class ReplyToken
{
    private const int HASH_LENGTH = 16;

    public static function messageId(int $taskId): string
    {
        return 'finisterre-' . $taskId . '-' . self::hash($taskId) . '-' . Str::lower(Str::random(12)) . '@' . self::host();
    }

    /**
     * The Reply-To of a task email, or null when no reply address is set.
     */
    public static function replyAddress(int $taskId): ?string
    {
        $address = trim(Typed::string(config('finisterre.mail.inbound.reply_address')));

        if (! str_contains($address, '@')) {
            return null;
        }

        if (! config('finisterre.mail.inbound.plus_addressing')) {
            return $address;
        }

        [$local, $domain] = explode('@', $address, 2);

        return $local . '+' . $taskId . '-' . self::hash($taskId) . '@' . $domain;
    }

    /**
     * The first task id with a valid signature in any of the given header values
     * (In-Reply-To, References, To, …).
     *
     * @param  array<int, string|null>  $values
     */
    public static function findTaskId(array $values): ?int
    {
        $pattern = '/(?:finisterre-|\+)(\d+)-([0-9a-f]{' . self::HASH_LENGTH . '})[-@]/i';

        foreach ($values as $value) {
            preg_match_all($pattern, (string)$value, $matches, PREG_SET_ORDER);

            foreach ($matches as [, $taskId, $hash]) {
                if (hash_equals(self::hash((int)$taskId), strtolower($hash))) {
                    return (int)$taskId;
                }
            }
        }

        return null;
    }

    private static function hash(int $taskId): string
    {
        return substr(hash_hmac('sha256', 'finisterre-reply-' . $taskId, Typed::string(config('app.key'))), 0, self::HASH_LENGTH);
    }

    private static function host(): string
    {
        return parse_url(Typed::string(config('app.url')), PHP_URL_HOST) ?: 'localhost';
    }
}
