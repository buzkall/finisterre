<?php

namespace Buzkall\Finisterre\Support;

use BackedEnum;

class AuthenticatableFilter
{
    /**
     * Normalize the configured filter value(s) into a flat list of scalars,
     * unwrapping any backed enums. Accepts an array, a single value, or a
     * comma-separated string (as stored by the settings page).
     *
     * @return array<int, mixed>
     */
    public static function values(): array
    {
        $value = config('finisterre.authenticatable_filter_value');

        if (is_string($value)) {
            $value = array_values(array_filter(
                array_map('trim', explode(',', $value)),
                static fn($item) => $item !== '',
            ));
        }

        return array_map(
            static fn($item) => self::scalar($item),
            is_array($value) ? $value : [$value],
        );
    }

    /**
     * Unwrap a backed enum to its scalar value; pass other values through unchanged.
     */
    public static function scalar(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
