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
 */
class PackageMigrations
{
    /**
     * One row per migration the package ships, in the order they must run.
     *
     * `file` is the published path in database/migrations, or null when the
     * migration was never published. `migrated` is null when the answer can't
     * be known — the migration isn't published, or the migrations table can't
     * be read (no database connection yet).
     *
     * @return list<array{name: string, file: string|null, migrated: bool|null}>
     */
    public static function status(): array
    {
        $ran = self::ranMigrations();

        return array_map(function(string $name) use ($ran): array {
            $file = self::publishedFile($name);

            return [
                'name'     => $name,
                'file'     => $file,
                'migrated' => $file === null || $ran === null
                    ? null
                    : in_array(basename($file, '.php'), $ran, true),
            ];
        }, FinisterreServiceProvider::migrationNames());
    }

    /**
     * Base names of the migrations that have never been published.
     *
     * @return list<string>
     */
    public static function unpublished(): array
    {
        return array_values(array_map(
            fn(array $migration): string => $migration['name'],
            array_filter(self::status(), fn(array $migration): bool => $migration['file'] === null)
        ));
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
            array_filter(self::status(), fn(array $migration): bool => $migration['migrated'] === false)
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
}
