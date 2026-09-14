<?php

namespace Arzcode\Finisterre\Models;

use Arzcode\Finisterre\Contracts\FinisterreReportable;
use Arzcode\Finisterre\Database\Factories\FinisterreTaskFactory;
use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Observers\FinisterreTaskObserver;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Tags\HasTags;
use Throwable;

/**
 * @property string $title
 * @property string $description
 * @property Collection $tags
 * @property Collection $comments
 * @property TaskStatusEnum $status
 * @property bool $archived
 * @property TaskPriorityEnum $priority
 * @property Collection $subtasks
 * @property ?Carbon $due_at
 * @property ?Carbon $completed_at
 * @property int $creator_id
 * @property ?int $assignee_id
 * @property ?int $cover_media_id
 * @property ?list<string> $editor_files
 * @property ?Model $subject
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?string $assignee_name
 * @property-read ?string $creator_name
 * @property-read int $has_changes
 */
class FinisterreTask extends Model implements HasMedia
{
    use HasFactory, HasTags, InteractsWithMedia;

    /** Media conversion the card image is served from. */
    public const COVER_CONVERSION = 'finisterre-card';

    /** Custom property on the cover media holding its vertical focus, 0 (top) to 100 (bottom). */
    public const COVER_POSITION_PROPERTY = 'finisterre_cover_position';

    /** Custom property on an attachment copied from an image pasted into the description or a comment: that image's file. */
    public const COVER_SOURCE_PROPERTY = 'finisterre_editor_file';

    public $fillable = ['title', 'description', 'status', 'archived', 'priority', 'due_at', 'completed_at',
        'creator_id', 'assignee_id', 'order_column', 'subject_type', 'subject_id', 'cover_media_id'];
    protected $with = ['tags'];

    protected static function booted(): void
    {
        // Only apply in the Filament context, not in queue/console
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        // Users who can only see their tasks get just the ones they created.
        static::addGlobalScope('canViewOnlyTheirTasks', function(Builder $query): void {
            if (static::currentUserCanViewOnlyTheirTasks()) {
                $query->where('creator_id', auth()->id());
            }
        });

        static::observe(FinisterreTaskObserver::class);
    }

    /**
     * Decided per query rather than once at boot, because the model boots wherever
     * it is first touched — and that is not always a panel carrying the plugin. The
     * media observer builds a task on every file saved anywhere in the host, so a
     * host panel without Finisterre (a website editor, say) booted the model there
     * and FinisterrePlugin::get() threw "Plugin [finisterre] is not registered".
     * Outside a panel with the plugin nobody is restricted to their own tasks.
     */
    protected static function currentUserCanViewOnlyTheirTasks(): bool
    {
        if (! app()->bound('filament')) {
            return false;
        }

        try {
            return FinisterrePlugin::get()->canViewOnlyTheirTasks();
        } catch (Throwable) {
            return false;
        }
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

    /** @return HasMany<FinisterreTaskComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(FinisterreTaskComment::class, 'task_id');
    }

    /** @return HasMany<FinisterreSubtask, $this> */
    public function subtasks(): HasMany
    {
        return $this->hasMany(FinisterreSubtask::class, 'task_id')->orderBy('order_column');
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

    public function assigneeName(): ?string
    {
        $assignee = $this->assignee;
        if (! $assignee) {
            return null;
        }

        /** @var Authenticatable $assignee */
        return $assignee->getUserDisplayName();
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

        // Resolve the resource label defensively: this method is also rendered inside queued
        // notifications, where no Filament panel is bootstrapped and getModelResource() would throw.
        try {
            /** @var class-string<resource>|null $resource */
            $resource = Filament::getModelResource($subject);
        } catch (Throwable) {
            $resource = null;
        }
        $type = e(Str::headline($resource ? $resource::getModelLabel() : class_basename($subject)));
        $label = e($subject->getFinisterreReportLabel());
        $url = $subject->getFinisterreReportUrl();

        $link = $url
            ? '<a href="' . e($url) . '" class="text-primary-600 underline" target="_blank">' . $label . '</a>'
            : $label;

        return new HtmlString($type . ': ' . $link);
    }

    /**
     * The attachment promoted to the task's card image, if any.
     *
     * A null cover_media_id is a deliberate "no card image", not a missing value:
     * the first image attached to a task fills it in (FinisterreMediaObserver), and
     * once the user clears it nothing puts it back on its own. The media table is
     * spatie's, so the column carries no foreign key — a row deleted behind our back
     * simply resolves to null here.
     *
     * @return BelongsTo<Media, $this>
     */
    public function coverMedia(): BelongsTo
    {
        /** @var class-string<Media> $model */
        $model = config('media-library.media_model') ?? Media::class;

        return $this->belongsTo($model, 'cover_media_id');
    }

    /**
     * What to render as the task's card image, or null when it has none.
     *
     * The thumbnail is sized for a 300px board card and a table cell. Anything wider,
     * like the banner across the task page, asks for the original: stretching the
     * thumbnail to the page's width is what turns it into a blur.
     *
     * Attachments uploaded before the conversion existed have no thumbnail, and
     * regenerating them is the host's call (`php artisan media-library:regenerate`),
     * so the original stands in until then rather than 404ing.
     */
    public function coverUrl(bool $thumbnail = true): ?string
    {
        $media = $this->coverMedia;

        if (! $media instanceof Media || ! str_starts_with((string)$media->mime_type, 'image/')) {
            return null;
        }

        return $thumbnail && $media->hasGeneratedConversion(self::COVER_CONVERSION)
            ? $media->getUrl(self::COVER_CONVERSION)
            : $media->getUrl();
    }

    /**
     * Which part of the card image shows where it is cropped, as the vertical
     * `object-position` percentage: 0 pins the top edge, 100 the bottom, 50 the middle.
     *
     * It lives on the media row rather than the task, so it belongs to the picture:
     * switching the card image to another attachment and back finds it where it was
     * left. Both the board card and the task page banner read it.
     */
    public function coverPosition(): float
    {
        $media = $this->coverMedia;

        if (! $media instanceof Media) {
            return 50.0;
        }

        return max(0.0, min(100.0, (float)$media->getCustomProperty(self::COVER_POSITION_PROPERTY, 50)));
    }

    /**
     * The pasted image the card image was copied from, when it came from the
     * description or a comment rather than being attached. The task page uses it to
     * show that image's star filled.
     */
    public function coverSourceFile(): ?string
    {
        $media = $this->coverMedia;

        return $media instanceof Media ? $media->getCustomProperty(self::COVER_SOURCE_PROPERTY) : null;
    }

    /**
     * A card-sized version of every image attached to a task.
     *
     * The board renders one per card, so serving the originals there means a column
     * of full-size photos. It is scaled down but not cropped: the card crops it in the
     * browser at the position the user dragged it to, and a crop baked into the file
     * would have thrown that part of the picture away already.
     *
     * Deferred rather than queued or immediate: the package cannot assume the host
     * runs a worker, and a thumbnail is not worth failing an upload over — a file
     * whose contents do not match its extension makes the image driver throw, and
     * after the response that is a reported error instead of a 500 in the user's
     * face. Until a thumbnail exists coverUrl() serves the original, so nothing is
     * missing in the meantime.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // The manipulations go last: fit() forwards to the image driver, so anything
        // chained after it is no longer talking to the conversion.
        $this->addMediaConversion(self::COVER_CONVERSION)
            ->performOnCollections('tasks')
            ->deferred()
            ->fit(Fit::Max, 600, 2400);
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

    /**
     * Filament's SpatieMediaLibraryFileUpload field is named "attachments". When the host app
     * enables Model::shouldBeStrict(), Filament internally calls data_get($record, 'attachments'),
     * which would otherwise throw a MissingAttributeException. Exposing it as a lazy-load-safe
     * accessor (returning the already-loaded media for the field's collection, or an empty
     * collection) keeps the package compatible with strict mode without triggering a query.
     */
    protected function attachments(): Attribute
    {
        return Attribute::get(
            fn(): Collection => $this->relationLoaded('media')
                ? $this->getRelation('media')->where('collection_name', 'tasks')->values()
                : collect()
        );
    }

    protected function casts(): array
    {
        return [
            'status'       => TaskStatusEnum::class,
            'archived'     => 'boolean',
            'priority'     => TaskPriorityEnum::class,
            'due_at'       => 'datetime',
            'completed_at' => 'datetime',
            'order_column' => 'integer',
            'editor_files' => 'array',
        ];
    }
}
