<?php

use Arzcode\Finisterre\FinisterreServiceProvider;
use Arzcode\Finisterre\Support\PackageMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function() {
    $this->migrationsPath = database_path('migrations');

    if (! is_dir($this->migrationsPath)) {
        mkdir($this->migrationsPath, 0777, true);
    }

    // Start from a clean slate: another test's leftovers would look like
    // published migrations here.
    foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
        unlink($file);
    }

    $this->publish = function(string $name, string $timestamp = '2026_01_01_000000'): string {
        $path = $this->migrationsPath . '/' . $timestamp . '_' . $name . '.php';
        file_put_contents($path, "<?php\n");

        return $path;
    };
});

afterEach(function() {
    foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
        unlink($file);
    }
});

function createMigrationsTable(): void
{
    Schema::create('migrations', function($table) {
        $table->increments('id');
        $table->string('migration');
        $table->integer('batch');
    });
}

it('reports every shipped migration as unpublished when nothing was published', function() {
    expect(PackageMigrations::unpublished())->toBe(FinisterreServiceProvider::migrationNames());
});

it('only reports the migrations that are still missing', function() {
    ($this->publish)('create_finisterre_tables');

    expect(PackageMigrations::unpublished())
        ->not->toContain('create_finisterre_tables')
        ->toContain('create_finisterre_subtasks_table');
});

it('matches a published migration through its timestamp prefix', function() {
    $path = ($this->publish)('add_subtasks_to_finisterre_tasks', '2026_02_03_101112');

    $status = collect(PackageMigrations::status())->firstWhere('name', 'add_subtasks_to_finisterre_tasks');

    expect($status['file'])->toBe($path);
});

it('lists published migrations that have not run yet as pending', function() {
    ($this->publish)('create_finisterre_tables');
    createMigrationsTable();

    expect(PackageMigrations::pending())->toContain('2026_01_01_000000_create_finisterre_tables');

    Schema::drop('migrations');
});

it('does not list a migration as pending once the migrations table records it', function() {
    ($this->publish)('create_finisterre_tables');
    createMigrationsTable();

    DB::table('migrations')->insert([
        'migration' => '2026_01_01_000000_create_finisterre_tables',
        'batch'     => 1,
    ]);

    expect(PackageMigrations::pending())->toBe([]);

    Schema::drop('migrations');
});

it('reports the migrated state as unknown while the migrations table is missing', function() {
    ($this->publish)('create_finisterre_tables');

    $status = collect(PackageMigrations::status())->firstWhere('name', 'create_finisterre_tables');

    expect($status['migrated'])->toBeNull();
});
