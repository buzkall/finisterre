<?php

namespace Buzkall\Finisterre\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface FinisterreContract
{
    public function canArchiveTasks(): bool;

    public function scopeUserIsActive(Builder $query): Builder;

    public function scopeAssignableUsers(Builder $query): Builder;

    public function getUserNameColumn(): string;
}
