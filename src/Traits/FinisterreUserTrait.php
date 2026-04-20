<?php

namespace Buzkall\Finisterre\Traits;

use Buzkall\Finisterre\Models\FinisterreTaskChange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        return config('finisterre.authenticatable_attribute', 'name') ?? 'name';
    }

    public function taskChanges(): HasMany
    {
        return $this->hasMany(FinisterreTaskChange::class, 'user_id');
    }
}
