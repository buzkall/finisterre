<?php

namespace Arzcode\Finisterre\Tests\Support;

use Filament\Models\Contracts\HasAvatar;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Workbench\App\Models\User;

/**
 * A host user that keeps its avatar in a media library, which is what most
 * Filament applications do. Reading the URL touches the `media` relation, so
 * unless the board loads it up front every card is a query — the N+1 an
 * application's query detector reports on the board.
 */
class MediaAvatarUser extends User implements HasAvatar, HasMedia
{
    use InteractsWithMedia;

    protected $table = 'users';

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->getFirstMediaUrl('avatar') ?: null;
    }
}
