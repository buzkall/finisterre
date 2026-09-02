<?php

namespace Arzcode\Finisterre\Support;

use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Model;

class UserAvatar
{
    /**
     * The avatar a host application has for a user, or null when it has none.
     *
     * Deliberately not Filament's own getUserAvatarUrl(): that one never returns
     * null, it falls back to a ui-avatars.com URL. On a board that would swap the
     * package's initials circles for one remote image request per card, for every
     * host, whether or not it stores avatars at all.
     */
    public static function url(?Model $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($user instanceof HasAvatar) {
            return $user->getFilamentAvatarUrl();
        }

        // Filament's second lookup, for hosts that keep the URL in a column and
        // never implement the contract. Read from the raw attributes so a model
        // without the column does not trip preventAccessingMissingAttributes().
        return $user->getAttributes()['avatar_url'] ?? null;
    }
}
