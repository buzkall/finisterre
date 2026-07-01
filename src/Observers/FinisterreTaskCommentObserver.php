<?php

namespace Arzcode\Finisterre\Observers;

use Arzcode\Finisterre\Models\FinisterreTaskComment;

class FinisterreTaskCommentObserver
{
    public function creating(FinisterreTaskComment $taskComment): void
    {
        $taskComment->creator_id = $taskComment->creator_id ?? auth()->id();
    }
}
