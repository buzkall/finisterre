<?php

namespace Arzcode\Finisterre\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface FinisterreContract
{
    public function canArchiveTasks(): bool;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeUserIsActive(Builder $query): Builder;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeAssignableUsers(Builder $query): Builder;

    public function getUserNameColumn(): string;
}
