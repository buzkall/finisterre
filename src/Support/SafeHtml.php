<?php

namespace Arzcode\Finisterre\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Rich text written by the people using the panel (descriptions, comments, replies
 * by email) goes into pages and emails as HTML, so it is sanitized as it is saved
 * (see SafeHtmlCast) the same way Filament sanitizes the description it shows.
 */
class SafeHtml
{
    /**
     * Strip scripts, event handlers and anything else that can run from the HTML.
     * Uses the sanitizer of the panel, so what a host allows there applies here.
     */
    public static function clean(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        return static::sanitizer()->sanitize($html);
    }

    /**
     * Plain text as HTML: escaped, blank lines starting a new paragraph and single
     * line breaks kept.
     */
    public static function fromPlainText(?string $text): string
    {
        $paragraphs = preg_split('/(?:\r\n|\r|\n){2,}/', trim((string)$text)) ?: [];

        return collect($paragraphs)
            ->filter(fn(string $paragraph): bool => trim($paragraph) !== '')
            ->map(fn(string $paragraph): string => '<p>' . nl2br(e(trim($paragraph)), false) . '</p>')
            ->implode('');
    }

    protected static function sanitizer(): HtmlSanitizerInterface
    {
        if (app()->bound(HtmlSanitizerInterface::class)) {
            return app(HtmlSanitizerInterface::class);
        }

        return new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->allowAttribute('class', allowedElements: '*')
                ->allowAttribute('data-id', allowedElements: '*')
                ->allowAttribute('data-type', allowedElements: '*')
                ->allowAttribute('style', allowedElements: '*')
                ->allowAttribute('width', allowedElements: 'img')
                ->allowAttribute('height', allowedElements: 'img')
                ->withMaxInputLength(500000)
        );
    }
}
