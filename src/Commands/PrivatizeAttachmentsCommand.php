<?php

namespace Arzcode\Finisterre\Commands;

use Arzcode\Finisterre\Controllers\FilamentRouteController;
use Arzcode\Finisterre\Support\AttachmentsDisk;
use Arzcode\Finisterre\Support\EditorFiles;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Moves what was uploaded before the host switched to a private attachments disk.
 *
 * Switching the disk only changes where new files go. Task attachments uploaded
 * until then stay on the public disk, and the HTML of descriptions and comments
 * still loads its images from the public disk's /storage/ URL, where anybody with
 * the link can open them. Attachments are moved with their conversions and their
 * media rows repointed; images are moved and the HTML pointed at the route that
 * checks who is asking.
 */
class PrivatizeAttachmentsCommand extends Command
{
    public $signature = 'finisterre:privatize-attachments
        {--from=public : The disk the old files were stored on}
        {--force : Move the files and update the database; without it nothing is changed}
        {--keep-originals : Copy the files instead of moving them}';
    public $description = 'Move the task attachments and rich editor images still on the public disk onto the private attachments disk.';
    protected Filesystem $source;
    protected Filesystem $target;
    protected string $from;
    protected string $to;
    protected bool $force;

    /** @var array{moved: int, missing: int, failed: int} */
    protected array $attachments = ['moved' => 0, 'missing' => 0, 'failed' => 0];

    /**
     * The files of the attachments copied this run, deleted from the source at the end.
     *
     * @var list<string>
     */
    protected array $attachmentFiles = [];

    /**
     * What became of each pasted image, so one loaded from several places is handled once.
     *
     * @var array<string, 'moved'|'private'|'missing'|'failed'>
     */
    protected array $images = [];

    public function handle(): int
    {
        $this->force = (bool)$this->option('force');
        $this->from = (string)$this->option('from');
        $this->to = AttachmentsDisk::name();

        intro('Finisterre: privatize attachments' . ($this->force ? '' : ' (dry run)'));

        if ($this->to === $this->from) {
            warning(sprintf("attachments_disk is '%s', the disk the files would be moved off. Point it at a private disk first (see \"Private attachments\" in the README).", $this->to));

            return self::FAILURE;
        }

        try {
            $this->source = Storage::disk($this->from);
            $this->target = Storage::disk($this->to);
        } catch (InvalidArgumentException $invalidArgumentException) {
            warning($invalidArgumentException->getMessage());

            return self::FAILURE;
        }

        // The moved images are served only to the tasks recorded as owning them.
        if (! EditorFiles::columnExists()) {
            warning('The tasks table has no editor_files column yet. Run the migrations first (`php artisan finisterre:update`).');

            return self::FAILURE;
        }

        $this->moveAttachments();

        $rows = [];

        foreach (AttachmentsDisk::htmlColumns() as [$table, $column, $taskKey]) {
            $rows[] = [$table . '.' . $column, (string)$this->rewrite($table, $column, $taskKey)];
        }

        $this->report($rows);

        if (! $this->force) {
            outro('Nothing was changed. Run it again with --force to apply.');

            return self::SUCCESS;
        }

        $this->deleteOriginals();

        outro('Done.');

        return self::SUCCESS;
    }

    /**
     * Copies each task attachment's files and repoints its media row at the private
     * disk. The row keeps its id, so the card image (cover_media_id) still finds it.
     * Straight through the query builder, like the HTML below: the media observer has
     * no business reacting to a file changing disks.
     */
    protected function moveAttachments(): void
    {
        AttachmentsDisk::taskMediaOn($this->from)->orderBy('id')->each(function(Media $media): void {
            if (! $this->source->exists($media->getPathRelativeToRoot())) {
                $this->attachments['missing']++;

                return;
            }

            if (! $this->force) {
                $this->attachments['moved']++;

                return;
            }

            $files = $this->attachmentFilesOf($media);
            $copied = [];

            foreach ($files as $file) {
                $existed = $this->target->exists($file);

                if (! $this->copyFile($file)) {
                    // The media row still points at the source, so whatever made it
                    // across would sit on the target with nothing loading it and no
                    // later run knowing it is there.
                    foreach ($copied as $done) {
                        rescue(fn() => $this->target->delete($done), report: false);
                    }

                    $this->attachments['failed']++;

                    return;
                }

                if (! $existed) {
                    $copied[] = $file;
                }
            }

            DB::table($media->getTable())->where($media->getKeyName(), $media->getKey())->update([
                'disk'             => $this->to,
                'conversions_disk' => in_array($media->conversions_disk, [null, $this->from], true) ? $this->to : $media->conversions_disk,
            ]);

            array_push($this->attachmentFiles, ...$files);
            $this->attachments['moved']++;
        });
    }

    /**
     * The original plus its conversions and responsive images. Those are picked out
     * of their directories by the names media library gives them, rather than taking
     * whole directories: a host's path generator may share a directory between media.
     *
     * @return list<string>
     */
    protected function attachmentFilesOf(Media $media): array
    {
        $generator = PathGeneratorFactory::create($media);
        $name = pathinfo($media->file_name, PATHINFO_FILENAME);

        $derived = collect([$generator->getPathForConversions($media), $generator->getPathForResponsiveImages($media)])
            ->flatMap(fn(string $directory): array => $this->source->files(rtrim($directory, '/')))
            ->filter(fn(string $file): bool => str_starts_with(basename($file), $name . '-') || str_starts_with(basename($file), $name . '___'));

        return collect([$media->getPathRelativeToRoot()])->merge($derived)->unique()->values()->all();
    }

    /**
     * Rewrites a column row by row and returns how many rows changed (or would).
     *
     * Through the query builder on purpose: saving the models would fire their
     * observers, logging a change and notifying people about an edit nobody made.
     */
    protected function rewrite(string $table, string $column, string $taskKey): int
    {
        $changed = 0;

        DB::table($table)
            ->select(array_unique(['id', $taskKey, $column]))
            ->where($column, 'like', '%/storage/%')
            ->chunkById(200, function(Collection $rows) use ($table, $column, $taskKey, &$changed): void {
                foreach ($rows as $row) {
                    $html = (string)$row->{$column};
                    $private = [];

                    $rewritten = (string)preg_replace_callback(AttachmentsDisk::PUBLIC_IMAGE, function(array $match) use (&$private): string {
                        if (! in_array($this->locate($match[2]), ['moved', 'private'], true)) {
                            return $match[0];
                        }

                        $private[] = $match[2];

                        return $match[1] . '/' . FilamentRouteController::URL_PATH . '/' . $match[2] . $match[3];
                    }, $html);

                    if ($rewritten === $html) {
                        continue;
                    }

                    $changed++;

                    if ($this->force) {
                        DB::table($table)->where('id', $row->id)->update([$column => $rewritten]);

                        if ($row->{$taskKey} !== null) {
                            EditorFiles::grant($private, $row->{$taskKey});
                        }
                    }
                }
            });

        return $changed;
    }

    /**
     * @return 'moved'|'private'|'missing'|'failed'
     */
    protected function locate(string $file): string
    {
        return $this->images[$file] ??= match (true) {
            $this->target->exists($file) => 'private',
            $this->source->exists($file) => $this->copy($file),
            default                      => 'missing',
        };
    }

    /**
     * @return 'moved'|'failed'
     */
    protected function copy(string $file): string
    {
        if (! $this->force) {
            return 'moved';
        }

        return $this->copyFile($file) ? 'moved' : 'failed';
    }

    /**
     * A disk configured to throw counts as a failed copy rather than ending the run.
     */
    protected function copyFile(string $file): bool
    {
        return (bool)rescue(function() use ($file): bool {
            $stream = $this->source->readStream($file);

            if (! is_resource($stream)) {
                return false;
            }

            try {
                return (bool)$this->target->writeStream($file, $stream);
            } finally {
                fclose($stream);
            }
        }, false, report: false);
    }

    /**
     * @param  list<array{string, string}>  $rows
     */
    protected function report(array $rows): void
    {
        $images = collect($this->images)->countBy();
        $moved = $this->force ? "Moved from '{$this->from}'" : "To move from '{$this->from}'";

        table(['Task attachments', 'Count'], [
            [$moved, (string)$this->attachments['moved']],
            ["Missing on '{$this->from}' (left as they were)", (string)$this->attachments['missing']],
            ['Could not be copied (left as they were)', (string)$this->attachments['failed']],
        ]);

        table(['Column', $this->force ? 'Rows rewritten' : 'Rows to rewrite'], $rows);

        table(['Pasted images', 'Count'], [
            [$moved, (string)$images->get('moved', 0)],
            ["Already on '{$this->to}'", (string)$images->get('private', 0)],
            ['Missing on both disks (left as they were)', (string)$images->get('missing', 0)],
            ['Could not be copied (left as they were)', (string)$images->get('failed', 0)],
        ]);

        $this->listImages('missing', 'Missing images');
        $this->listImages('failed', 'Images that could not be copied');
    }

    /**
     * Only once every row points at the private copy, so an interrupted run never
     * leaves anything loading a file that is already gone.
     */
    protected function deleteOriginals(): void
    {
        if ($this->option('keep-originals')) {
            note("The originals were kept on '{$this->from}', where they can still be opened by anybody with the link.");

            return;
        }

        $images = array_keys(array_filter($this->images, fn(string $status): bool => in_array($status, ['moved', 'private'], true)));
        $deleted = 0;

        foreach (array_unique([...$this->attachmentFiles, ...$images]) as $file) {
            if ($this->source->exists($file)) {
                $deleted += (int)$this->source->delete($file);
            }
        }

        info(sprintf("%d original(s) deleted from '%s'.", $deleted, $this->from));
    }

    protected function listImages(string $status, string $heading): void
    {
        $files = array_keys(array_filter($this->images, fn(string $value): bool => $value === $status));

        if ($files !== []) {
            warning($heading . ': ' . implode(', ', $files));
        }
    }
}
