<?php

namespace Arzcode\Finisterre\Models;

use Arzcode\Finisterre\Support\Typed;
use Spatie\Tags\Tag;

/**
 * @property string $name
 * @property ?string $type
 */
class FinisterreTag extends Tag
{
    protected $table = 'tags';

    public static function findOrCreateFromString(string $name, ?string $type = null, ?string $locale = null): self
    {
        $locale ??= Typed::nullableString(static::getLocale());

        $tag = static::findFromString($name, $type, $locale);

        if (! $tag instanceof static) {
            $locales = Typed::strings(config('finisterre.locales', [$locale]));
            $translations = array_fill_keys($locales, $name);

            $tag = static::create([
                'name' => $translations,
                'type' => $type,
            ]);
        }

        return $tag;
    }
}
