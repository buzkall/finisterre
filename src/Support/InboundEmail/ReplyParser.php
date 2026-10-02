<?php

namespace Arzcode\Finisterre\Support\InboundEmail;

use Arzcode\Finisterre\Support\Typed;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Keeps only what the person wrote in a reply: the quoted email below it and the
 * clients' quote wrappers are cut off, and the rest is sanitized, since comments
 * are rendered as HTML.
 */
class ReplyParser
{
    /**
     * Where the quoted email starts in the HTML of the common clients. The body is
     * cut at the earliest one found.
     *
     * @var array<int, string>
     */
    private const array HTML_QUOTE_MARKERS = [
        'gmail_quote',                // Gmail
        'id="divRplyFwdMsg"',         // Outlook
        'id="appendonsend"',          // Outlook
        'yahoo_quoted',               // Yahoo
        'protonmail_quote',           // Proton Mail
        'zmail_extra',                // Zoho
        'type="cite"',                // Apple Mail
        'moz-cite-prefix',            // Thunderbird
        '-----Original Message-----', // classic Outlook
    ];

    /**
     * Where the quoted email starts in a plain text reply. A From: line only counts
     * when the other header lines of a quoted email follow it, so a reply that
     * mentions "De: ..." isn't cut there.
     *
     * @var array<int, string>
     */
    private const array TEXT_QUOTE_MARKERS = [
        '/^-+\s*Original Message\s*-+/im',
        '/^On .+ wrote:$/im',
        '/^El .+ escribió:$/im',
        '/^_{10,}/m',
        '/^From:\s.+\R(?:.*\R){0,3}?(?:Sent|Date|To|Subject):\s/im',
        '/^De:\s.+\R(?:.*\R){0,3}?(?:Enviado|Fecha|Para|Asunto):\s/im',
    ];

    public function parse(string $html, string $text): string
    {
        if (filled(trim(strip_tags($html, '<img>')))) {
            $reply = $this->cutHtml($html);

            if (filled(trim(strip_tags($reply, '<img>')))) {
                return $this->sanitize($reply);
            }
        }

        $text = trim($this->cutText($text));

        return $text === '' ? '' : $this->sanitize('<p>' . nl2br(e($text)) . '</p>');
    }

    private function cutHtml(string $html): string
    {
        $cut = collect([...$this->replyAboveMarkers(), ...self::HTML_QUOTE_MARKERS])
            ->map(fn(string $marker) => stripos($html, $marker))
            ->filter(fn($position) => $position !== false)
            ->min();

        if ($cut === null) {
            return $html;
        }

        // Step back to the start of the tag holding the marker, so no half tag is left.
        $tagStart = strrpos(substr($html, 0, $cut), '<');

        return substr($html, 0, $tagStart === false ? $cut : $tagStart);
    }

    private function cutText(string $text): string
    {
        $positions = collect($this->replyAboveMarkers())
            ->map(fn(string $marker) => stripos($text, $marker))
            ->merge(array_map(
                fn(string $pattern) => preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) ? $match[0][1] : false,
                self::TEXT_QUOTE_MARKERS,
            ));

        $cut = $positions->filter(fn($position) => $position !== false)->min();

        // Lines quoted with ">" are dropped one by one instead of cutting there, since
        // they may sit between the parts of an interleaved reply.
        return Typed::string(preg_replace('/^>.*(?:\R|$)/m', '', $cut === null ? $text : substr($text, 0, $cut)));
    }

    /**
     * The line task emails open with, in every language the package speaks, since
     * the email may have been sent in another locale than the one reading it.
     *
     * @return array<int, string>
     */
    private function replyAboveMarkers(): array
    {
        return collect(glob(__DIR__ . '/../../../resources/lang/*', GLOB_ONLYDIR) ?: [])
            ->map(fn(string $dir) => __('finisterre::finisterre.mail.reply_above', [], basename($dir)))
            ->push(__('finisterre::finisterre.mail.reply_above'))
            ->reject(fn(string $phrase) => $phrase === 'finisterre::finisterre.mail.reply_above')
            ->push('finisterre-reply-above')
            ->unique()
            ->values()
            ->all();
    }

    private function sanitize(string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['http', 'https'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        return $this->tidy((new HtmlSanitizer($config))->sanitize($html));
    }

    /**
     * Drops what mail clients leave around the words: the indentation of their HTML,
     * which the markdown of the notification emails takes for code blocks, the empty
     * blocks of a blank signature, and the "On … wrote:" line some put above the quote
     * instead of inside it.
     */
    private function tidy(string $html): string
    {
        // A line break is a space in HTML, except inside <pre>.
        $parts = preg_split('#(<pre\b.*?</pre>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $parts[$index] = preg_replace('/\s*\R\s*/', ' ', $part) ?? $part;
            }
        }

        $html = implode('', $parts);

        do {
            $previous = $html;

            $html = preg_replace([
                '#<(div|p|span)>\s*</\1>#i',
                '#(?:\s|<br\s*/?>|<(div|p)>\s*<br\s*/?>\s*</\1>)+$#i',
                '#<(div|p)>\s*(?:On\b[^<]{0,200}\bwrote:|El\b[^<]{0,200}\bescribió:)\s*</\1>$#iu',
            ], '', $html) ?? $html;
        } while ($html !== $previous);

        return trim($html);
    }
}
