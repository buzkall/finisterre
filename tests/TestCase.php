<?php

namespace Arzcode\Finisterre\Tests;

use Arzcode\Finisterre\FinisterreServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Encryption\Encrypter;
use Livewire\Livewire;
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
            // FinisterreServiceProvider::class,
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

        // Rendering any Blade view boots Laravel's encrypter, which refuses to
        // start without a key. Testbench only ships one in a skeleton .env that
        // `package:purge-skeleton` deletes on every install, so generate one per
        // run: nothing encrypted here outlives the process, so the key never has
        // to be reproducible — and none has to be committed.
        config()->set('app.key', 'base64:' . base64_encode(
            Encrypter::generateKey(config('app.cipher', 'AES-256-CBC'))
        ));

        // Only the Filament suite boots the real service provider, so the
        // package's own translations have to be registered here or every
        // __('finisterre::…') in a notification comes back as the raw key.
        $app['translator']->addNamespace('finisterre', __DIR__ . '/../resources/lang');

        // Set up the finisterre config for testing
        config()->set('finisterre.authenticatable', User::class);
        config()->set('finisterre.table_name', 'finisterre_tasks');

        // Run your package migrations
        $migration = include __DIR__ . '/../database/migrations/create_finisterre_tables.php.stub';
        $migration->up();

        // Run users migration for testing
        $usersMigration = include __DIR__ . '/../workbench/database/migrations/create_users_table.php';
        $usersMigration->up();
    }
}
