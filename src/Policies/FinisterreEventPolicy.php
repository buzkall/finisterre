<?php

namespace Arzcode\Finisterre\Policies;

use Arzcode\Finisterre\Models\FinisterreEvent;
use Illuminate\Contracts\Auth\Authenticatable;

class FinisterreEventPolicy
{
    // Note to future self: remember that this policy can be overridden by the finisterre config
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, FinisterreEvent $event): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, FinisterreEvent $event): bool
    {
        return true;
    }

    public function delete(Authenticatable $user, FinisterreEvent $event): bool
    {
        return $user->id === $event->creator_id; // @phpstan-ignore-line
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function restore(Authenticatable $user, FinisterreEvent $event): bool
    {
        return false;
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return false;
    }

    public function forceDelete(Authenticatable $user, FinisterreEvent $event): bool
    {
        return false;
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return false;
    }
}
