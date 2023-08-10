<?php

namespace Buzkall\Finisterre;

use Buzkall\Finisterre\Commands\FinisterreCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FinisterreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('finisterre')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_finisterre_table')
            ->hasCommand(FinisterreCommand::class);
    }
}
