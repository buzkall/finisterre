<?php

namespace Arzcode\Finisterre\Models;

use Arzcode\Finisterre\Support\Typed;
use Arzcode\Finisterre\Support\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $task_id
 * @property ?int $user_id
 */
class FinisterreTaskChange extends Model
{
    public $fillable = ['task_id', 'user_id'];

    public function getTable(): string
    {
        return Typed::string(config('finisterre.task_changes_table_name'));
    }

    /** @return BelongsTo<FinisterreTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(FinisterreTask::class, 'task_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class(), 'user_id');
    }
}
