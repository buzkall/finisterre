<?php

namespace Arzcode\Finisterre\Tests;

use Arzcode\Finisterre\FinisterreServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Workbench\App\Models\User;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn(string $modelName) => 'Arzcode\\FinisterrePlugin\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );

        // Configure factory for User model
        Factory::guessFactoryNamesUsing(function(string $modelName) {
            if ($modelName === 'Workbench\\App\\Models\\User') {
                return 'Workbench\\Database\\Factories\\UserFactory';
            }

            return 'Arzcode\\FinisterrePlugin\\Database\\Factories\\' . class_basename($modelName) . 'Factory';
        });
    }

    protected function getPackageProviders($app)
    {
        return [
            LivewireServiceProvider::class,
            FinisterreServiceProvider::class,
            LaravelSettingsServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Livewire' => Livewire::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        // The web middleware (event frontend routes) needs an encryption key.
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));

        // Set up the finisterre config for testing
        config()->set('finisterre.authenticatable', User::class);

        // The base spatie/laravel-settings table, needed by the package's
        // settings migration (registered by the provider and picked up by any
        // test running `migrate`, e.g. through RefreshDatabase).
        $settingsMigration = include __DIR__ . '/../vendor/spatie/laravel-settings/database/migrations/create_settings_table.php.stub';
        $settingsMigration->up();

        // Run your package migrations
        $migration = include __DIR__ . '/../database/migrations/create_finisterre_tables.php.stub';
        $migration->up();

        $eventsMigration = include __DIR__ . '/../database/migrations/create_finisterre_events_tables.php.stub';
        $eventsMigration->up();

        // Run users migration for testing
        $usersMigration = include __DIR__ . '/../workbench/database/migrations/create_users_table.php';
        $usersMigration->up();
    }
}
