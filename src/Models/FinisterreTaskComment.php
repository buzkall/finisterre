<?php

namespace Buzkall\Finisterre\Models;

use Buzkall\Finisterre\Database\Factories\FinisterreTaskCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $comment
 */
class FinisterreTaskComment extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public $fillable = ['task_id', 'comment', 'creator_id'];
    protected $touches = ['task'];

    protected static function booted(): void
    {
        static::creating(function($taskComment) {
            $taskComment->creator_id = $taskComment->creator_id ?? auth()->id();
        });
    }

    public function getTable()
    {
        return config('finisterre.comments.table_name');
    }

    protected static function newFactory(): FinisterreTaskCommentFactory
    {
        return FinisterreTaskCommentFactory::new();
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(FinisterreTask::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('finisterre.authenticatable'), 'creator_id');
    }
}
