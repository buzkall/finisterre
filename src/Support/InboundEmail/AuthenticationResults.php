<?php

namespace Arzcode\Finisterre\Support\InboundEmail;

/**
 * What the receiving mail server found out about the sender of an email.
 *
 * A server may spread its verdict over several Authentication-Results headers
 * (Fastmail writes one for DKIM, DMARC and SPF and others for the rest), and the
 * sender can add headers of their own below them. The receiving server is the one
 * that wrote the topmost header, and it strips incoming headers that carry its
 * name (RFC 8601), so every header with that name is its own.
 */
class AuthenticationResults
{
    /**
     * The results of the receiving server, joined into one value.
     *
     * @param  array<int, string>  $headers  every Authentication-Results of the email, topmost first
     */
    public static function ofReceivingServer(array $headers): string
    {
        $headers = array_values(array_filter(array_map(trim(...), $headers)));

        if ($headers === []) {
            return '';
        }

        $server = self::server($headers[0]);

        return implode('; ', array_filter($headers, fn(string $header) => self::server($header) === $server));
    }

    /**
     * The same, read from the raw header block of an email. Mail libraries can't be
     * trusted with this header: they take its `;` and `=` for a list of parameters.
     */
    public static function fromRawHeaders(string $raw): string
    {
        $unfolded = preg_replace('/\r?\n[ \t]+/', ' ', $raw) ?? $raw;

        preg_match_all('/^Authentication-Results:(.*)$/mi', $unfolded, $matches);

        return self::ofReceivingServer($matches[1]);
    }

    /**
     * The name the server signs its results with: what comes before the first `;`,
     * without the version that may follow it.
     */
    private static function server(string $header): string
    {
        return strtolower(strtok(trim(strtok($header, ';') ?: ''), " \t") ?: '');
    }
}
