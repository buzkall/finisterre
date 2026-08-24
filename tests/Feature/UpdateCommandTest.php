<?php

use Arzcode\Finisterre\Commands\UpdateCommand;
use Arzcode\Finisterre\FinisterreServiceProvider;
use Arzcode\Finisterre\Support\PackageMigrations;
use Illuminate\Contracts\Console\Kernel;

beforeEach(function() {
    // The package provider isn't registered in the test app, so register the
    // command on its own — that is all these tests exercise.
    $this->app[Kernel::class]->registerCommand(new UpdateCommand);

    $this->migrationsPath = database_path('migrations');

    if (! is_dir($this->migrationsPath)) {
        mkdir($this->migrationsPath, 0777, true);
    }

    foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
        unlink($file);
    }
});

afterEach(function() {
    foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
        unlink($file);
    }
});

it('fails the check when migrations are still unpublished', function() {
    $this->artisan('finisterre:update', ['--check' => true])
        ->expectsOutputToContain('are not published yet')
        ->assertFailed();
});

it('reports nothing outstanding once every migration is published and run', function() {
    foreach (FinisterreServiceProvider::migrationNames() as $index => $name) {
        file_put_contents(
            sprintf('%s/2026_01_01_%06d_%s.php', $this->migrationsPath, $index, $name),
            "<?php\n"
        );
    }

    $this->artisan('finisterre:update', ['--check' => true])
        ->expectsOutputToContain('Every migration shipped by this version is published')
        ->assertSuccessful();

    expect(PackageMigrations::unpublished())->toBe([]);
});

it('leaves everything untouched when the prompts are declined', function() {
    $this->artisan('finisterre:update')
        ->expectsConfirmation('Publish the missing migrations now?', 'no')
        ->expectsConfirmation('Re-publish the Filament assets (`php artisan filament:assets`)?', 'no')
        ->expectsConfirmation('Run `npm run build` now?', 'no')
        ->assertSuccessful();

    expect(PackageMigrations::unpublished())->toBe(FinisterreServiceProvider::migrationNames());
});
