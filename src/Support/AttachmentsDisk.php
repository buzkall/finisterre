<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\Models\FinisterreTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Where Finisterre keeps its files, and the switch from the public disk to a
 * private one that the installer and `finisterre:update` offer.
 *
 * On the public disk the web server hands a file to anybody who has its URL. The
 * private disk lives outside public/, and FilamentRouteController serves its files
 * only to users who can see their task. Switching takes an entry in the host's
 * config/filesystems.php and a changed key in its config/finisterre.php; the files
 * already uploaded are moved by `finisterre:privatize-attachments`.
 */
class AttachmentsDisk
{
    public const PRIVATE_DISK = 'finisterre';

    /** An image loaded from the public disk's /storage/ URL. Only files at its root: that is where the rich editor put them. */
    public const PUBLIC_IMAGE = '~(src=["\'])(?:https?://[^/"\']+)?/storage/(?!finisterre-files/)([^/"\'?#]+)(["\'])~i';

    public static function name(): string
    {
        return config('finisterre.attachments_disk') ?? 'public';
    }

    public static function isPublic(): bool
    {
        return self::name() === 'public';
    }

    public static function isConfigured(): bool
    {
        return is_array(config('filesystems.disks.' . self::name()));
    }

    /**
     * Add the private disk to config/filesystems.php and point config/finisterre.php
     * at it (publishing that file first when the host never did), then use it for the
     * rest of this process so the files can be moved right away.
     *
     * @return list<string> what could not be done and has to be done by hand
     */
    public static function makePrivate(): array
    {
        $problems = [];

        if (! self::addDiskTo(config_path('filesystems.php'))) {
            $problems[] = sprintf("Add a '%s' disk to config/filesystems.php: its `disks` array could not be found (see \"Private attachments\" in the README).", self::PRIVATE_DISK);
        }

        $config = config_path('finisterre.php');

        if (! is_file($config)) {
            rescue(fn() => Artisan::call('vendor:publish', ['--tag' => 'finisterre-config']), report: false);
        }

        if (! self::useDisk($config, self::PRIVATE_DISK)) {
            $problems[] = sprintf("Set 'attachments_disk' => '%s' in config/finisterre.php.", self::PRIVATE_DISK);
        }

        if ($problems === []) {
            // A host that already defines a disk by that name keeps its own.
            if (! is_array(config('filesystems.disks.' . self::PRIVATE_DISK))) {
                config()->set('filesystems.disks.' . self::PRIVATE_DISK, self::runtimeDisk());
            }

            config()->set('finisterre.attachments_disk', self::PRIVATE_DISK);
        }

        return $problems;
    }

    /**
     * Append the private disk to the `disks` array of a filesystems config file.
     * True when the file has the disk afterwards, including when it already did.
     *
     * The array is found with PHP's tokenizer rather than by counting brackets in the
     * text: a config file is free to carry comments and strings with brackets and
     * apostrophes in them, and those are exactly what a text scan trips on.
     */
    public static function addDiskTo(string $path): bool
    {
        $contents = is_file($path) ? (string)file_get_contents($path) : '';

        if (preg_match('/^\s*[\'"]' . self::PRIVATE_DISK . '[\'"]\s*=>/m', $contents)) {
            return true;
        }

        $array = self::disksArray($contents);

        if ($array === null) {
            return false;
        }

        [$close, $lastEnd, $needsComma] = $array;

        $lineStart = strrpos(substr($contents, 0, $close), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $closeIndent = (string)preg_replace('/\S.*$/', '', substr($contents, $lineStart, $close - $lineStart));

        $head = rtrim(substr($contents, 0, $lastEnd) . ($needsComma ? ',' : '') . substr($contents, $lastEnd, $close - $lastEnd));

        $patched = $head . "\n\n" . self::diskEntry($closeIndent . '    ') . "\n\n" . $closeIndent . substr($contents, $close);

        return file_put_contents($path, $patched) !== false;
    }

    /**
     * Set `attachments_disk` in a finisterre config file. Commented-out lines are left
     * alone, and so is the comment that may follow the value.
     */
    public static function useDisk(string $path, string $disk): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $contents = (string)file_get_contents($path);
        $updated = preg_replace('/^(\s*[\'"]attachments_disk[\'"]\s*=>\s*)([\'"])[^\'"]*\2/m', '${1}\'' . $disk . '\'', $contents, 1, $count);

        if ($count === 0 || $updated === null) {
            return false;
        }

        return $updated === $contents || file_put_contents($path, $updated) !== false;
    }

    /**
     * What is still on a disk other than the attachments disk: task attachments, and
     * descriptions or comments loading an image whose file is there. An image whose
     * file is gone is not counted, since moving it cannot fix it.
     *
     * @return array{attachments: int, contents: int}
     */
    public static function publicLeftovers(string $from = 'public'): array
    {
        return [
            'attachments' => (int)rescue(fn() => self::taskMediaOn($from)->count(), 0, report: false),
            'contents'    => (int)rescue(fn() => self::rowsLoadingImagesFrom($from), 0, report: false),
        ];
    }

    /**
     * @return Builder<Media>
     */
    public static function taskMediaOn(string $disk): Builder
    {
        /** @var class-string<Media> $model */
        $model = config('media-library.media_model') ?? Media::class;

        // A host may map tasks to a morph alias, which media library then stores.
        return $model::query()
            ->whereIn('model_type', array_values(array_unique([FinisterreTask::class, Relation::getMorphAlias(FinisterreTask::class)])))
            ->where('disk', $disk);
    }

    /**
     * The columns holding rich editor HTML, as [table, column, column with the task id].
     *
     * @return list<array{string, string, string}>
     */
    public static function htmlColumns(): array
    {
        return [
            [config('finisterre.table_name', 'finisterre_tasks'), 'description', 'id'],
            [config('finisterre.comments.table_name', 'finisterre_task_comments'), 'comment', 'task_id'],
        ];
    }

    protected static function rowsLoadingImagesFrom(string $disk): int
    {
        $storage = Storage::disk($disk);
        $rows = 0;

        foreach (self::htmlColumns() as [$table, $column]) {
            DB::table($table)
                ->select(['id', $column])
                ->where($column, 'like', '%/storage/%')
                ->orderBy('id')
                ->each(function(object $row) use ($column, $storage, &$rows): void {
                    preg_match_all(self::PUBLIC_IMAGE, (string)$row->{$column}, $matches);

                    if (collect($matches[2])->contains(fn(string $file): bool => $storage->exists($file))) {
                        $rows++;
                    }
                });
        }

        return $rows;
    }

    /**
     * Where the top-level `disks` array closes, where its last entry ends, and
     * whether that entry still needs a comma before another one can follow.
     *
     * @return array{int, int, bool}|null
     */
    protected static function disksArray(string $contents): ?array
    {
        $offset = 0;
        $depth = null;
        $expect = null;
        $lastText = '';
        $lastEnd = 0;

        foreach (token_get_all($contents) as $token) {
            $id = is_array($token) ? $token[0] : null;
            $text = is_array($token) ? $token[1] : $token;
            $start = $offset;
            $offset += strlen($text);

            if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if ($depth !== null) {
                $depth += match ($text) {
                    '['     => 1,
                    ']'     => -1,
                    default => 0,
                };

                if ($depth === 0) {
                    return [$start, $lastEnd, ! in_array($lastText, [',', '['], true)];
                }

                $lastText = $text;
                $lastEnd = $offset;

                continue;
            }

            if ($id === T_CONSTANT_ENCAPSED_STRING && trim($text, '\'"') === 'disks') {
                $expect = T_DOUBLE_ARROW;
            } elseif ($expect === T_DOUBLE_ARROW && $id === T_DOUBLE_ARROW) {
                $expect = '[';
            } elseif ($expect === '[' && $text === '[') {
                $depth = 1;
                $lastText = '[';
                $lastEnd = $offset;
            } else {
                $expect = null;
            }
        }

        return null;
    }

    protected static function diskEntry(string $indent): string
    {
        $lines = [
            "// Finisterre's attachments. Outside public/, so the web server cannot hand",
            '// them out: the package serves them only to users who can see their task.',
            "'" . self::PRIVATE_DISK . "' => [",
            "    'driver'     => 'local',",
            "    'root'       => storage_path('app/finisterre-files'),",
            "    'url'        => rtrim((string)env('APP_URL', 'http://localhost'), '/') . '/storage/finisterre-files',",
            "    'visibility' => 'public',",
            "    'throw'      => false,",
            '],',
        ];

        return implode("\n", array_map(fn(string $line): string => $indent . $line, $lines));
    }

    /**
     * The same disk as diskEntry() writes, for the process that just wrote it.
     *
     * @return array<string, mixed>
     */
    protected static function runtimeDisk(): array
    {
        return [
            'driver'     => 'local',
            'root'       => storage_path('app/finisterre-files'),
            'url'        => rtrim((string)config('app.url', 'http://localhost'), '/') . '/storage/finisterre-files',
            'visibility' => 'public',
            'throw'      => false,
        ];
    }
}
