<?php

namespace Arzcode\Finisterre\Casts;

use Arzcode\Finisterre\Support\SafeHtml;
use Arzcode\Finisterre\Support\Typed;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Rich text is sanitized as it is written, whatever writes it (the editor, a reply by
 * email, a report, a command), so every page, email and notification that shows it
 * can take it as stored.
 */
class SafeHtmlCast implements CastsInboundAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $html = Typed::nullableString($value);

        return blank($html) ? $html : SafeHtml::clean($html);
    }
}
