<?php

namespace Buzkall\Finisterre\Policies;

use Buzkall\Finisterre\Models\FinisterreTask;
use Illuminate\Contracts\Auth\Authenticatable;

class FinisterreTaskReportPolicy
{
    public function before(Authenticatable $user): ?bool
    {
        if ($callback = config('finisterre.restrict_task_reports_callback')) {
            if (is_callable($callback) && $callback($user)) {
                return false;
            }
        }

        return null;
    }

    public function viewAny(Authenticatable $user): bool
    {
        return true;
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
        return false;
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
