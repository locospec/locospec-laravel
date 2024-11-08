<?php

namespace Locospec\LLCS;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Locospec\LCS\LCS;
use Locospec\LLCS\Commands\LLCSCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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

    public function register()
    {
        parent::register();

        Log::info('Registering LLCS bindings');

        // Register LCS first as it's a dependency
        $this->app->singleton(LCS::class, function () {
            Log::info('Creating LCS instance');

            return new LCS;
        });

        // Register LLCS with proper Application injection
        $this->app->singleton('llcs', function (Application $app) {
            Log::info('Creating LLCS instance');

            return new LLCS($app);
        });
    }

    public function boot()
    {
        parent::boot();

        Log::info('Booting LLCS');

        $this->app->beforeResolving('llcs', function ($name) {
            Log::info('Before resolving LLCS', ['name' => $name]);
        });

        $this->app->afterResolving('llcs', function ($resolved, $app) {
            Log::info('After resolving LLCS', ['resolved' => get_class($resolved)]);
        });

        // Bootstrap LCS with Laravel configuration
        try {
            if (! LCS::isInitialized()) {
                LCS::bootstrap([
                    'paths' => config('locospec-laravel.paths', []),
                ]);
                Log::info('LCS bootstrapped successfully');
            }
        } catch (\Exception $e) {
            Log::error('Failed to bootstrap LCS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function packageRegistered()
    {
        Log::info('packageRegistered');
    }

    public function bootingPackage()
    {
        Log::info('bootingPackage');
    }
}
