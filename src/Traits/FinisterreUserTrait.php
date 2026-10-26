<?php

namespace Arzcode\Finisterre\Traits;

use Arzcode\Finisterre\Models\FinisterreTaskChange;
use Arzcode\Finisterre\Support\AuthenticatableFilter;
use Arzcode\Finisterre\Support\ColumnExists;
use Arzcode\Finisterre\Support\Typed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

trait FinisterreUserTrait
{
    public function canArchiveTasks(): bool
    {
        return (bool)config('finisterre.active');
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeUserIsActive(Builder $query): Builder
    {
        return $query->when(
            ColumnExists::in(Typed::string(config('finisterre.authenticatable_table_name')), 'active'),
            fn($query) => $query->where('active', true)
        );
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeAssignableUsers(Builder $query): Builder
    {
        $filterColumn = Typed::string(config('finisterre.authenticatable_filter_column'));
        $table = Typed::string(config('finisterre.authenticatable_table_name'));

        return $query
            ->when(
                $filterColumn && ColumnExists::in($table, $filterColumn),
                fn($query) => $query->whereIn($filterColumn, AuthenticatableFilter::values())
            )->when(
                ColumnExists::in($table, 'active'),
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
            $cols = implode(', ', array_map($grammar->wrap(...), $attr));

            return DB::raw("CONCAT_WS(' ', $cols)");
        }

        return $attr;
    }

    /** @return HasMany<FinisterreTaskChange, $this> */
    public function taskChanges(): HasMany
    {
        return $this->hasMany(FinisterreTaskChange::class, 'user_id');
    }
}
