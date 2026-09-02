<?php

namespace Arzcode\Finisterre\Tests\Support;

use Filament\Models\Contracts\HasAvatar;
use Workbench\App\Models\User;

/**
 * A host user that uploads avatars, the way Filament expects one to: the
 * contract is implemented and the URL is null until there is a file.
 */
class AvatarUser extends User implements HasAvatar
{
    protected $table = 'users';

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->name === 'Sin Foto' ? null : '/storage/avatars/' . $this->id . '.jpg';
    }
}
