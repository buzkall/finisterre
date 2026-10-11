<?php

namespace Arzcode\Finisterre\Policies;

use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\TaskAccess;
use Arzcode\Finisterre\Support\TaskFollowers;
use Arzcode\Finisterre\Support\Typed;
use Illuminate\Contracts\Auth\Authenticatable;

class FinisterreTaskPolicy
{
    // Note to future self: remember that this policy can be overridden by the finisterre config
    public function viewAny(Authenticatable $user): bool
    {
        return FinisterrePlugin::get()->canViewAllTasks()
            || FinisterrePlugin::get()->canViewOnlyTheirTasks()
            // Somebody with neither still gets the tasks they were made a follower of.
            || TaskFollowers::followsAny($user->getAuthIdentifier());
    }

    public function view(Authenticatable $user, FinisterreTask $finisterreTask): bool
    {
        return TaskAccess::canView($user, $finisterreTask);
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    /**
     * Followers who cannot see all tasks may read and comment, not edit.
     */
    public function update(Authenticatable $user, FinisterreTask $finisterreTask): bool
    {
        return TaskAccess::canUpdate($user, $finisterreTask);
    }

    public function delete(Authenticatable $user, FinisterreTask $finisterreTask): bool
    {
        return Typed::nullableInt($user->getAuthIdentifier()) === $finisterreTask->creator_id;
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
