<?php

namespace Arzcode\Finisterre\Support;

use Stringable;

/**
 * Narrows loosely typed values — config entries, form state, request payloads —
 * to the type a caller needs, falling back to a default when the value can't
 * stand for one (an array where a string was expected, say).
 */
class Typed
{
    public static function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof Stringable ? (string)$value : $default;
    }

    /**
     * The value as a string, or null when it is empty.
     */
    public static function nullableString(mixed $value): ?string
    {
        $string = self::string($value);

        return $string === '' ? null : $string;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return is_numeric($value) || is_bool($value) ? (int)$value : $default;
    }

    public static function float(mixed $value, float $default = 0.0): float
    {
        return is_numeric($value) ? (float)$value : $default;
    }

    /**
     * The value as an int, or null when it isn't numeric.
     */
    public static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * @return array<mixed>
     */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * The int and string entries of an array, as ids come back from a form or the session.
     *
     * @return list<int|string>
     */
    public static function ids(mixed $value): array
    {
        return array_values(array_filter(self::array($value), fn(mixed $id): bool => is_int($id) || is_string($id)));
    }

    /**
     * The scalar entries of an array as strings; anything else is dropped.
     *
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        return array_values(array_map(
            fn(mixed $item): string => self::string($item),
            array_filter(self::array($value), fn(mixed $item): bool => is_scalar($item) || $item instanceof Stringable),
        ));
    }
}
