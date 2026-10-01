<?php

namespace Arzcode\Finisterre\Support\InboundEmail;

/**
 * A received email as the drivers hand it over, whatever mailbox it came from.
 */
class InboundMessage
{
    /**
     * @param  array<int, string>  $recipients  To, Cc, Delivered-To…: where a plus-addressed token would be
     * @param  array<string, string>  $headers  lower-cased header name => value
     */
    public function __construct(
        public readonly ?string $messageId,
        public readonly ?string $from,
        public readonly array $recipients = [],
        public readonly string $html = '',
        public readonly string $text = '',
        public readonly array $headers = [],
    ) {}

    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower($name)] ?? null;

        return filled($value) ? (string)$value : null;
    }
}
