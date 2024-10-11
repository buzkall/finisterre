<?php

namespace Buzkall\Finisterre\Policies;

use Buzkall\Finisterre\Models\FinisterreTaskComment;
use Illuminate\Contracts\Auth\Authenticatable;

class FinisterreTaskCommentPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, FinisterreTaskComment $finisterreTaskComment): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, FinisterreTaskComment $finisterreTaskComment): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, FinisterreTaskComment $finisterreTaskComment): bool
    {
        return $user->id === $finisterreTaskComment->creator_id;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function restore(Authenticatable $user, FinisterreTaskComment $finisterreTaskComment): bool
    {
        return false;
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return false;
    }

    public function forceDelete(Authenticatable $user, FinisterreTaskComment $finisterreTaskComment): bool
    {
        return false;
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return false;
    }
}
