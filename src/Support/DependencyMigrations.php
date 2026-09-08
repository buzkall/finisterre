<?php

namespace Arzcode\Finisterre\Support;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The migrations Finisterre needs from the spatie packages it builds on.
 *
 * Tasks carry tags (spatie/laravel-tags) and attachments
 * (spatie/laravel-medialibrary). Both packages ship their migration as a stub
 * that `migrate` never sees until it is published, and a host application that
 * never used either package on its own has no reason to have published it — so
 * the board died on its first query with "relation tags does not exist" while
 * every migration Finisterre ships had run.
 *
 * A dependency is settled when its tables are in the database (whatever
 * migration created them — squashed, renamed, or the host's own) or when a
 * migration file is published and waiting for `migrate`. Only the rest has to
 * be published.
 */
class DependencyMigrations
{
    /**
     * One entry per spatie package, keyed by the base name of its migration.
     *
     * @return list<array{package: string, purpose: string, name: string, tables: list<string>, provider: string, tag: string}>
     */
    public static function all(): array
    {
        return [
            [
                'package'  => 'spatie/laravel-tags',
                'purpose'  => 'task tags',
                'name'     => 'create_tag_tables',
                'tables'   => ['tags', 'taggables'],
                'provider' => 'Spatie\Tags\TagsServiceProvider',
                'tag'      => 'tags-migrations',
            ],
            [
                'package'  => 'spatie/laravel-medialibrary',
                'purpose'  => 'task attachments',
                'name'     => 'create_media_table',
                'tables'   => ['media'],
                'provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
                'tag'      => 'medialibrary-migrations',
            ],
        ];
    }

    /**
     * One row per dependency: the published migration file (or null), and
     * whether its tables are in the database — null when that can't be known
     * because the database can't be read.
     *
     * @return list<array{package: string, purpose: string, name: string, tables: list<string>, provider: string, tag: string, file: string|null, present: bool|null}>
     */
    public static function status(): array
    {
        return array_map(fn(array $dependency): array => [
            ...$dependency,
            'file'    => PackageMigrations::publishedFile($dependency['name']),
            'present' => self::tablesExist($dependency['tables']),
        ], self::all());
    }

    /**
     * The dependencies whose tables are missing and that have no published
     * migration waiting to create them — the ones that still have to be
     * published. Nothing is reported while the database can't be read: the
     * tables may well be there.
     *
     * @return list<array{package: string, purpose: string, name: string, tables: list<string>, provider: string, tag: string, file: string|null, present: bool|null}>
     */
    public static function missing(): array
    {
        return array_values(array_filter(
            self::status(),
            fn(array $dependency): bool => $dependency['present'] === false && $dependency['file'] === null
        ));
    }

    /**
     * Published dependency migrations whose tables are not in the database
     * yet, by file base name — what `migrate` will create next.
     *
     * @return list<string>
     */
    public static function pending(): array
    {
        return array_values(array_map(
            fn(array $dependency): string => basename((string)$dependency['file'], '.php'),
            array_filter(
                self::status(),
                fn(array $dependency): bool => $dependency['present'] === false && $dependency['file'] !== null
            )
        ));
    }

    /**
     * The `vendor:publish` arguments that publish a dependency's migration.
     *
     * @param  array{provider: string, tag: string}  $dependency
     * @return array{'--provider': string, '--tag': string}
     */
    public static function publishArguments(array $dependency): array
    {
        return [
            '--provider' => $dependency['provider'],
            '--tag'      => $dependency['tag'],
        ];
    }

    /**
     * The `vendor:publish` command line that publishes a dependency's
     * migration, for the instructions printed when it isn't done automatically.
     *
     * @param  array{provider: string, tag: string}  $dependency
     */
    public static function publishCommand(array $dependency): string
    {
        return sprintf(
            'php artisan vendor:publish --provider="%s" --tag="%s"',
            $dependency['provider'],
            $dependency['tag']
        );
    }

    /**
     * Whether every given table exists, or null when the database can't be read.
     *
     * @param  list<string>  $tables
     */
    protected static function tablesExist(array $tables): ?bool
    {
        try {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return null;
        }
    }
}
