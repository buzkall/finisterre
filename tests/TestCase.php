<?php

namespace Buzkall\Finisterre\Tests;

use Buzkall\Finisterre\FinisterreServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn(string $modelName) => 'Buzkall\\FinisterrePlugin\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );

        // Configure factory for User model
        Factory::guessFactoryNamesUsing(function(string $modelName) {
            if ($modelName === 'Workbench\\App\\Models\\User') {
                return 'Workbench\\Database\\Factories\\UserFactory';
            }

            return 'Buzkall\\FinisterrePlugin\\Database\\Factories\\' . class_basename($modelName) . 'Factory';
        });
    }

    protected function getPackageProviders($app)
    {
        return [
            // FinisterreServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Livewire' => \Livewire\Livewire::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Set up the finisterre config for testing
        config()->set('finisterre.authenticatable', \Workbench\App\Models\User::class);
        config()->set('finisterre.table_name', 'finisterre_tasks');

        // Run your package migrations
        $migration = include __DIR__ . '/../database/migrations/create_finisterre_tables.php.stub';
        $migration->up();

        // Run users migration for testing
        $usersMigration = include __DIR__ . '/../workbench/database/migrations/create_users_table.php';
        $usersMigration->up();
    }
}
