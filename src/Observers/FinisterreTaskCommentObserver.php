<?php

namespace Arzcode\Finisterre\Observers;

use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Support\EditorFiles;
use Arzcode\Finisterre\Support\UserModel;

class FinisterreTaskCommentObserver
{
    public function creating(FinisterreTaskComment $taskComment): void
    {
        $taskComment->creator_id ??= UserModel::authId();
    }

    public function saved(FinisterreTaskComment $taskComment): void
    {
        if ($taskComment->isDirty('comment')) {
            EditorFiles::claim($taskComment->comment, $taskComment->task_id);
        }
    }
}
