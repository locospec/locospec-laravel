<?php

namespace Locospec\LLCS;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Locospec\LLCS\Commands\LLCSCommand;

class LLCSServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('locospec-laravel')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_locospec_laravel_table')
            ->hasCommand(LLCSCommand::class);
    }
}
