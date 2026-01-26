<?php

namespace Buzkall\Finisterre\Models;

use Spatie\Tags\Tag;

class FinisterreTag extends Tag
{
    protected $table = 'tags';

    public static function findOrCreateFromString(string $name, ?string $type = null, ?string $locale = null)
    {
        $locale = $locale ?? static::getLocale();

        $tag = static::findFromString($name, $type, $locale);

        if (! $tag) {
            $locales = config('finisterre.locales', [$locale]);
            $translations = array_fill_keys($locales, $name);

            $tag = static::create([
                'name' => $translations,
                'type' => $type,
            ]);
        }

        return $tag;
    }
}
