<?php

namespace Arzcode\Finisterre\Policies;

use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Support\Typed;
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
        return true;
    }

    public function update(Authenticatable $user, FinisterreTaskComment $finisterreTaskComment): bool
    {
        return Typed::nullableInt($user->getAuthIdentifier()) === $finisterreTaskComment->creator_id
            && $finisterreTaskComment->sent_at === null;
    }

    public function delete(Authenticatable $user, FinisterreTaskComment $finisterreTaskComment): bool
    {
        return Typed::nullableInt($user->getAuthIdentifier()) === $finisterreTaskComment->creator_id;
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
