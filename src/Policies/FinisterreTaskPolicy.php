<?php

namespace Buzkall\Finisterre\Policies;

use Buzkall\Finisterre\FinisterrePlugin;
use Buzkall\Finisterre\Models\FinisterreTask;
use Illuminate\Contracts\Auth\Authenticatable;

class FinisterreTaskPolicy
{
    // Note to future self: remember that this policy can be overridden by the finisterre config
    public function viewAny(Authenticatable $user): bool
    {
        return FinisterrePlugin::get()->canViewAllTasks() || FinisterrePlugin::get()->canViewOnlyTheirTasks();
    }

    public function view(Authenticatable $user, FinisterreTask $finisterreTask): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, FinisterreTask $finisterreTask): bool
    {
        return true;
    }

    public function delete(Authenticatable $user, FinisterreTask $finisterreTask): bool
    {
        return $user->id === $finisterreTask->creator_id; // @phpstan-ignore-line
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function restore(Authenticatable $user, FinisterreTask $finisterreTask): bool
    {
        return false;
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return false;
    }

    public function forceDelete(Authenticatable $user, FinisterreTask $finisterreTask): bool
    {
        return false;
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return false;
    }
}
