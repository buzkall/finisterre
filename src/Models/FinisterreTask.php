<?php

namespace Buzkall\Finisterre\Models;

use Buzkall\Finisterre\Contracts\FinisterreReportable;
use Buzkall\Finisterre\Database\Factories\FinisterreTaskFactory;
use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\FinisterrePlugin;
use Buzkall\Finisterre\Observers\FinisterreTaskObserver;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

/**
 * @property string $title
 * @property string $description
 * @property Collection $tags
 * @property Collection $comments
 * @property TaskStatusEnum $status
 * @property bool $archived
 * @property TaskPriorityEnum $priority
 * @property array $subtasks
 * @property Carbon $due_at
 * @property Carbon $completed_at
 * @property int $creator_id
 * @property int $assignee_id
 * @property ?Model $subject
 */
class FinisterreTask extends Model implements HasMedia
{
    use HasFactory, HasTags, InteractsWithMedia;

    public $fillable = ['title', 'description', 'status', 'archived', 'priority', 'subtasks', 'due_at', 'completed_at',
        'creator_id', 'assignee_id', 'order_column', 'subject_type', 'subject_id'];
    protected $casts = [
        'status'       => TaskStatusEnum::class,
        'archived'     => 'boolean',
        'priority'     => TaskPriorityEnum::class,
        'subtasks'     => 'array',
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
        'order_column' => 'integer',
    ];
    protected $with = ['tags'];

    protected static function booted(): void
    {
        // Only apply in the Filament context, not in queue/console
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        // add global scope only for users that can only see their tasks
        if (app()->bound('filament') && FinisterrePlugin::get()->canViewOnlyTheirTasks()) {
            static::addGlobalScope('canViewOnlyTheirTasks', fn($query) => $query->where('creator_id', auth()->id()));
        }

        static::observe(FinisterreTaskObserver::class);
    }

    public function getTable()
    {
        return config('finisterre.table_name');
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where('archived', false);
    }

    protected static function newFactory(): FinisterreTaskFactory
    {
        return FinisterreTaskFactory::new();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FinisterreTaskComment::class, 'task_id');
    }

    public function taskChanges(): HasMany
    {
        return $this->hasMany(FinisterreTaskChange::class, 'task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('finisterre.authenticatable'), 'creator_id');
    }

    public function creatorName(): string
    {
        $creator = $this->creator;
        if (! $creator) {
            return 'N/A';
        }

        /** @var Authenticatable $creator */
        return $creator->getUserDisplayName();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(config('finisterre.authenticatable'), 'assignee_id');
    }

    /**
     * The host record this task was reported against, if any.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A right-hand label + (optional) deep link to the reported record,
     * prefixed with its translated resource label. Returns null when the
     * subject is not reportable.
     */
    public function subjectReportLink(): ?HtmlString
    {
        $subject = $this->subject;

        if (! $subject instanceof FinisterreReportable) {
            return null;
        }

        /** @var class-string<\Filament\Resources\Resource>|null $resource */
        $resource = Filament::getModelResource($subject);
        $type = e(Str::headline($resource ? $resource::getModelLabel() : class_basename($subject)));
        $label = e($subject->getFinisterreReportLabel());
        $url = $subject->getFinisterreReportUrl();

        $link = $url
            ? '<a href="' . e($url) . '" class="text-primary-600 underline" target="_blank">' . $label . '</a>'
            : $label;

        return new HtmlString($type . ': ' . $link);
    }

    /**
     * Override the tags() method from HasTags trait to use the correct pivot key.
     * When using a custom Tag model (FinisterreTag), Laravel defaults to 'finisterre_tag_id'
     * but the taggables table uses 'tag_id'.
     */
    public function tags(): MorphToMany
    {
        return $this
            ->morphToMany(FinisterreTag::class, 'taggable', 'taggables', null, 'tag_id')
            ->orderBy('order_column');
    }

    public static function getTagClassName(): string
    {
        return FinisterreTag::class;
    }
}
