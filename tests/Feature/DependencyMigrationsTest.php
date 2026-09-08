<?php

use Arzcode\Finisterre\Support\DependencyMigrations;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function() {
    $this->migrationsPath = database_path('migrations');

    if (! is_dir($this->migrationsPath)) {
        mkdir($this->migrationsPath, 0777, true);
    }

    foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
        unlink($file);
    }

    foreach (['tags', 'taggables', 'media'] as $table) {
        Schema::dropIfExists($table);
    }
});

afterEach(function() {
    foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
        unlink($file);
    }
});

it('reports the tags and media migrations as missing when neither the tables nor a file exist', function() {
    expect(array_column(DependencyMigrations::missing(), 'name'))
        ->toBe(['create_tag_tables', 'create_media_table'])
        ->and(DependencyMigrations::pending())->toBe([]);
});

it('is satisfied by tables that exist, whatever migration created them', function() {
    foreach (['tags', 'taggables', 'media'] as $table) {
        Schema::create($table, fn(Blueprint $table) => $table->id());
    }

    expect(DependencyMigrations::missing())->toBe([])
        ->and(DependencyMigrations::pending())->toBe([]);
});

it('still reports the tags migration while only one of its tables exists', function() {
    Schema::create('tags', fn(Blueprint $table) => $table->id());
    Schema::create('media', fn(Blueprint $table) => $table->id());

    expect(array_column(DependencyMigrations::missing(), 'name'))->toBe(['create_tag_tables']);
});

it('treats a published migration file as pending rather than missing', function() {
    file_put_contents($this->migrationsPath . '/2026_01_01_000000_create_tag_tables.php', "<?php\n");

    expect(array_column(DependencyMigrations::missing(), 'name'))->toBe(['create_media_table'])
        ->and(DependencyMigrations::pending())->toBe(['2026_01_01_000000_create_tag_tables']);
});

it('builds the vendor:publish invocation for a dependency', function() {
    [$tags] = DependencyMigrations::all();

    expect(DependencyMigrations::publishArguments($tags))->toBe([
        '--provider' => 'Spatie\Tags\TagsServiceProvider',
        '--tag'      => 'tags-migrations',
    ])->and(DependencyMigrations::publishCommand($tags))
        ->toBe('php artisan vendor:publish --provider="Spatie\Tags\TagsServiceProvider" --tag="tags-migrations"');
});
