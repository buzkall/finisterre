<?php

namespace Buzkall\Finisterre\Traits;

use Buzkall\Finisterre\FinisterrePlugin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait FinisterreUserTrait
{
    public function canAccessFinisterre(): bool
    {
        if (! config('finisterre.active')) {
            return false;
        }

        $plugin = FinisterrePlugin::get();

        if (! $plugin) {
            return false;
        }

        return $plugin->canViewAllTasks() || $plugin->canViewOnlyTheirTasks();
    }

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
            Schema::hasColumn('users', 'active'),
            fn($query) => $query->where('active', true)
        );
    }

    public function scopeAssignableUsers(Builder $query): Builder
    {
        return $query
            ->when(
                Schema::hasColumn('users', 'role'),
                fn($query) => $query->where('role', 'admin')
            )->when(
                Schema::hasColumn('users', 'active'),
                fn($query) => $query->where('active', true)
            );
    }

    public function getUserNameColumn(): string
    {
        return 'name';
    }
}
