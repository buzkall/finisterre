<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\FinisterreServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Publish / run status of the migrations shipped by the package.
 *
 * Every migration is published with a timestamp prefix chosen at publish time,
 * so the only stable identifier is the base name (see
 * FinisterreServiceProvider::migrationNames()). Matching on that base name is
 * what lets an upgrade tell "this version ships a migration you never
 * published" apart from "you published it already".
 *
 * A host application that ran `php artisan schema:dump --prune` has no file
 * left for the migrations it squashed, only a row in the migrations table (and
 * the INSERT that recreates it inside database/schema). Those count as applied,
 * not as missing: republishing them would copy them back under a fresh
 * timestamp, which `migrate` would then try to run a second time.
 *
 * The name isn't always there to match either. An application the package grew
 * out of built the same tables from migrations of its own, under names of their
 * own, before the package shipped one — so its earliest package migrations have
 * neither a file nor a record anywhere, even though the schema they describe is
 * in place. The order the migrations must run in settles those: a later one can
 * only have run against the schema the earlier ones leave behind, so everything
 * before the last one known to have run is applied too, whatever the
 * application calls it.
 */
class PackageMigrations
{
    /**
     * One row per migration the package ships, in the order they must run.
     *
     * `file` is the published path in database/migrations, or null when the
     * migration was never published or was pruned by a schema dump. `migrated`
     * is null when the answer can't be known — the migrations table can't be
     * read (no database connection yet). `record` is the name the migrations
     * table (or the schema dump) knows this migration by, which is not
     * necessarily the name of the published file, and is null for a migration
     * only the ordering vouches for. `squashed` marks a migration whose effect
     * is already in this application's schema and whose file is gone, so there
     * is nothing left to publish or run.
     *
     * @return list<array{name: string, file: string|null, migrated: bool|null, squashed: bool, record: string|null}>
     */
    public static function status(): array
    {
        $ran = self::ranMigrations();
        $dumped = self::schemaDumpMigrations();

        $status = array_map(function(string $name) use ($ran, $dumped): array {
            $file = self::publishedFile($name);
            $record = self::recordFor($name, $ran ?? []) ?? self::recordFor($name, $dumped);
            $migrated = $ran === null ? null : self::recordFor($name, $ran) !== null;

            return [
                'name'     => $name,
                'file'     => $file,
                'migrated' => $migrated,
                'squashed' => $file === null && $record !== null,
                'record'   => $record,
            ];
        }, FinisterreServiceProvider::migrationNames());

        return self::markImpliedByOrder($status);
    }

    /**
     * Mark the migrations nothing names but that must have run anyway.
     *
     * Every migration builds on the schema the ones before it leave behind, so
     * the last migration this application is known to have run — a row in the
     * migrations table, or a name in the schema dump — settles every earlier
     * one: those tables and columns are in place, whatever migration of its own
     * the application created them from. A migration that still has a published
     * file is left alone: that file is what `migrate` will run, whether or not
     * the schema it describes is already there.
     *
     * @param  list<array{name: string, file: string|null, migrated: bool|null, squashed: bool, record: string|null}>  $status
     * @return list<array{name: string, file: string|null, migrated: bool|null, squashed: bool, record: string|null}>
     */
    protected static function markImpliedByOrder(array $status): array
    {
        $last = null;

        foreach ($status as $index => $migration) {
            if ($migration['record'] !== null) {
                $last = $index;
            }
        }

        if ($last === null) {
            return $status;
        }

        foreach ($status as $index => $migration) {
            if ($index < $last && $migration['file'] === null && $migration['record'] === null) {
                $status[$index]['squashed'] = true;
            }
        }

        return $status;
    }

    /**
     * Base names of the migrations that have never been published and that
     * nothing else accounts for — the ones an upgrade still has to publish.
     *
     * @return list<string>
     */
    public static function unpublished(): array
    {
        return self::names(fn(array $migration): bool => $migration['file'] === null && ! $migration['squashed']);
    }

    /**
     * Base names of the migrations that are already part of this application's
     * schema and have no file left to publish — squashed away by a schema dump,
     * or built by migrations of the application's own that the ordering vouches
     * for.
     *
     * @return list<string>
     */
    public static function squashed(): array
    {
        return self::names(fn(array $migration): bool => $migration['squashed']);
    }

    /**
     * Published migrations that have no row in the migrations table yet.
     * Empty when the migrations table can't be read.
     *
     * @return list<string>
     */
    public static function pending(): array
    {
        return array_values(array_map(
            fn(array $migration): string => basename((string)$migration['file'], '.php'),
            array_filter(
                self::status(),
                fn(array $migration): bool => $migration['file'] !== null && $migration['migrated'] === false
            )
        ));
    }

    /**
     * Paths of published migrations that already ran under a different file
     * name — a copy published after the original was squashed away. `migrate`
     * would run them a second time, against tables that already exist.
     *
     * @return list<string>
     */
    public static function republished(): array
    {
        return array_values(array_map(
            fn(array $migration): string => (string)$migration['file'],
            array_filter(
                self::status(),
                fn(array $migration): bool => $migration['file'] !== null
                    && $migration['record'] !== null
                    && basename((string)$migration['file'], '.php') !== $migration['record']
            )
        ));
    }

    /**
     * The published file for a migration base name, or null when it was never
     * published. Guards against a stray duplicate by returning the oldest one.
     */
    public static function publishedFile(string $name): ?string
    {
        $matches = glob(database_path('migrations/*' . $name . '.php')) ?: [];

        sort($matches);

        return $matches[0] ?? null;
    }

    /**
     * Every published file for the given base names, deduplicated.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public static function publishedFiles(array $names): array
    {
        $files = [];

        foreach ($names as $name) {
            foreach (glob(database_path('migrations/*' . $name . '.php')) ?: [] as $file) {
                $files[$file] = $file;
            }
        }

        return array_values($files);
    }

    /**
     * Delete the published files for the given base names and return the ones
     * that went. Used to undo the copies `vendor:publish` makes of migrations
     * the host application had squashed away.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public static function removePublished(array $names): array
    {
        return self::removeFiles(self::publishedFiles($names));
    }

    /**
     * Delete the given migration files and return the base names that went.
     *
     * @param  list<string>  $files
     * @return list<string>
     */
    public static function removeFiles(array $files): array
    {
        $removed = [];

        foreach ($files as $file) {
            if (@unlink($file)) {
                $removed[] = basename($file, '.php');
            }
        }

        sort($removed);

        return $removed;
    }

    /**
     * @param  callable(array{name: string, file: string|null, migrated: bool|null, squashed: bool, record: string|null}): bool  $filter
     * @return list<string>
     */
    protected static function names(callable $filter): array
    {
        return array_values(array_map(
            fn(array $migration): string => $migration['name'],
            array_filter(self::status(), $filter)
        ));
    }

    /**
     * The full migration name — timestamp prefix included — under which the
     * given base name appears in the given list, or null when it doesn't.
     *
     * @param  list<string>  $records
     */
    protected static function recordFor(string $name, array $records): ?string
    {
        foreach ($records as $record) {
            if ($record === $name || str_ends_with($record, '_' . $name)) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Migration names recorded in the migrations table, or null when it can't
     * be read (table missing, no connection configured).
     *
     * @return list<string>|null
     */
    protected static function ranMigrations(): ?array
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return null;
            }

            return DB::table('migrations')->pluck('migration')->all();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Migration names found in the squashed schema files under database/schema.
     * `schema:dump` appends the migrations table's rows to the dump, so a name
     * in there has run on every database the dump is loaded into — even before
     * the host application's migrations table exists.
     *
     * @return list<string>
     */
    protected static function schemaDumpMigrations(): array
    {
        $names = [];

        foreach (glob(database_path('schema/*')) ?: [] as $file) {
            if (! is_file($file)) {
                continue;
            }

            $contents = @file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            preg_match_all('/\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+/i', $contents, $matches);

            foreach ($matches[0] as $match) {
                $names[$match] = $match;
            }
        }

        return array_values($names);
    }
}
