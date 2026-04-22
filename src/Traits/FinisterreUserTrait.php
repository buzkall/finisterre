<?php

namespace Buzkall\Finisterre\Traits;

use Buzkall\Finisterre\Models\FinisterreTaskChange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait FinisterreUserTrait
{
    public function canArchiveTasks(): bool
    {
        if (! config('finisterre.active')) {
            return false;
        }

        return true;
    }

    public function scopeUserIsActive(Builder $query): Builder
    {
        return $query->when(
            Schema::hasColumn(config('finisterre.authenticatable_table_name'), 'active'),
            fn($query) => $query->where('active', true)
        );
    }

    public function scopeAssignableUsers(Builder $query): Builder
    {
        $filterColumn = config('finisterre.authenticatable_filter_column');
        $filterValue = config('finisterre.authenticatable_filter_value');
        $table = config('finisterre.authenticatable_table_name');

        return $query
            ->when(
                $filterColumn && Schema::hasColumn($table, $filterColumn),
                fn($query) => $query->whereIn($filterColumn, (array)$filterValue)
            )->when(
                Schema::hasColumn($table, 'active'),
                fn($query) => $query->where('active', true)
            );
    }

    public function getUserNameColumn(): string
    {
        $attr = config('finisterre.authenticatable_attribute', 'name') ?? 'name';

        return is_array($attr) ? ($attr[0] ?? 'name') : $attr;
    }

    public function getUserDisplayName(): string
    {
        $attr = config('finisterre.authenticatable_attribute', 'name') ?? 'name';

        if (is_array($attr)) {
            return collect($attr)
                ->map(fn($col) => $this->{$col})
                ->filter()
                ->implode(' ');
        }

        return (string)($this->{$attr} ?? '');
    }

    public static function getUserNameSelectExpression(): Expression|string
    {
        $attr = config('finisterre.authenticatable_attribute', 'name') ?? 'name';

        if (is_array($attr)) {
            $grammar = DB::connection()->getQueryGrammar();
            $cols = implode(', ', array_map(fn($c) => $grammar->wrap($c), $attr));

            return DB::raw("CONCAT_WS(' ', $cols)");
        }

        return $attr;
    }

    public function taskChanges(): HasMany
    {
        return $this->hasMany(FinisterreTaskChange::class, 'user_id');
    }
}
