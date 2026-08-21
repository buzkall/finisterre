<?php

namespace Arzcode\Finisterre\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinisterreTaskChange extends Model
{
    public $fillable = ['task_id', 'user_id'];

    public function getTable()
    {
        return 'finisterre_task_changes';
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(FinisterreTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('finisterre.authenticatable'), 'user_id');
    }
}
