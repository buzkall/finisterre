<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Forms\Components\RichEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Which task each image pasted into a description or a comment belongs to.
 *
 * FilamentRouteController serves such an image to the users who can see its task,
 * and the HTML cannot say which task that is: anybody can write a file's URL into a
 * description of their own. So an upload is remembered in its uploader's session,
 * and the first description or comment saved from that session with it adds it to
 * its task's `editor_files`. Loading it from anywhere else afterwards grants nothing.
 */
class EditorFiles
{
    /** A rich editor image on the private disk, as the HTML loads it: a file at the disk's root. */
    public const PATTERN = '~finisterre-files/([^/"\'?#]+)(?=["\'?#])~';

    public const COLUMN = 'editor_files';

    /** The uploads of this session that no task has claimed yet. */
    public const SESSION_KEY = 'finisterre.editor_uploads';

    /**
     * Stores a rich editor upload the way Filament does, and remembers it when it went
     * to a private disk.
     */
    public static function store(TemporaryUploadedFile $file, RichEditor $component): mixed
    {
        $path = $file->store($component->getFileAttachmentsDirectory(), $component->getFileAttachmentsDiskName());

        if ($component->getFileAttachmentsVisibility() === 'public') {
            rescue(fn() => $component->getFileAttachmentsDisk()->setVisibility($path, 'public'), report: false);
        }

        if (is_string($path) && ! AttachmentsDisk::isPublic()) {
            session()->push(self::SESSION_KEY, $path);
        }

        return $path;
    }

    /**
     * Whether the file was uploaded in this session and not saved anywhere yet, so its
     * uploader sees it in the editor before the task or comment exists.
     */
    public static function uploadedInSession(string $path): bool
    {
        return in_array($path, static::sessionUploads(), true);
    }

    /**
     * Gives the task the images in the HTML that were uploaded in this session and no
     * task has claimed yet. Anything else the HTML loads is left alone.
     */
    public static function claim(?string $html, int|string|null $taskId): void
    {
        $uploads = static::sessionUploads();
        $paths = array_values(array_intersect(static::in($html), $uploads));

        if ($paths === [] || $taskId === null || ! static::columnExists()) {
            return;
        }

        static::grant($paths, $taskId);

        session()->put(self::SESSION_KEY, array_values(array_diff($uploads, $paths)));
    }

    /**
     * Adds the images to the task's list. The row is locked, so two comments saved on
     * the same task at once both keep theirs, and written through the query builder,
     * so the task's observers do not fire and updated_at stays as it was.
     *
     * @param  list<string>  $paths
     */
    public static function grant(array $paths, int|string $taskId): void
    {
        if ($paths === []) {
            return;
        }

        $table = config('finisterre.table_name', 'finisterre_tasks');

        DB::transaction(function() use ($table, $paths, $taskId): void {
            $current = DB::table($table)->where('id', $taskId)->lockForUpdate()->value(self::COLUMN);
            $files = array_values(array_unique([...(json_decode((string)$current, true) ?: []), ...$paths]));

            DB::table($table)->where('id', $taskId)->update([self::COLUMN => json_encode($files)]);
        });
    }

    public static function belongsTo(FinisterreTask $task, string $path): bool
    {
        return in_array($path, (array)$task->editor_files, true);
    }

    /**
     * The images the HTML loads from the private disk.
     *
     * @return list<string>
     */
    public static function in(?string $html): array
    {
        preg_match_all(self::PATTERN, (string)$html, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * The images the HTML loads from the attachments disk in use, as paths on it.
     *
     * A private disk serves them through the checked route (see in()); the public
     * disk leaves the rich editor's own `/storage/` URLs in place.
     *
     * @return list<string>
     */
    public static function imagesOnDisk(?string $html): array
    {
        if (! AttachmentsDisk::isPublic()) {
            return static::in($html);
        }

        preg_match_all(AttachmentsDisk::PUBLIC_IMAGE, (string)$html, $matches);

        return array_values(array_unique($matches[2]));
    }

    /**
     * False until the host has run the migration that adds it.
     */
    public static function columnExists(): bool
    {
        return Schema::hasColumn(config('finisterre.table_name', 'finisterre_tasks'), self::COLUMN);
    }

    /**
     * @return list<string>
     */
    protected static function sessionUploads(): array
    {
        return array_values((array)rescue(fn() => session()->get(self::SESSION_KEY, []), [], report: false));
    }
}
