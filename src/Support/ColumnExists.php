<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\Support\Concerns\ScopedPerRequest;
use Illuminate\Support\Facades\Schema;

/**
 * Schema::hasColumn() asks the database every time, and the scopes and checks that
 * need it run on every query and every render. The answers are kept for the length of
 * the request, in a scoped binding that Octane and the queue workers start empty for
 * the next one, so a column added by a deploy is seen without restarting anything.
 */
class ColumnExists
{
    use ScopedPerRequest;

    /** @var array<string, bool> */
    protected array $answers = [];

    public static function in(string $table, string $column): bool
    {
        return static::current()->check($table, $column);
    }

    /**
     * Forget the answers, for code that changes the schema and then asks again.
     */
    public static function forget(): void
    {
        static::current()->answers = [];
    }

    protected function check(string $table, string $column): bool
    {
        $connection = Schema::getConnection()->getName();

        return $this->answers[$connection . '.' . $table . '.' . $column] ??= Schema::hasColumn($table, $column);
    }
}
