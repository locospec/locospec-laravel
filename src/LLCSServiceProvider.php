<?php

namespace Locospec\LLCS;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Locospec\LCS\Actions\ActionOrchestrator;
use Locospec\LCS\Actions\StateMachineFactory;
use Locospec\LCS\LCS;
use Locospec\LCS\Registry\DatabaseDriverInterface;
use Locospec\LLCS\Commands\LLCSCommand;
use Locospec\LLCS\Database\DatabaseOperator;
use Locospec\LLCS\Http\Controllers\ModelActionController;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LLCSServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('locospec-laravel')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_locospec_laravel_table')
            ->hasCommand(LLCSCommand::class);
    }

    public function register(): void
    {
        parent::register();

        // Register LCS first as it's a dependency
        $this->app->singleton(LCS::class, function () {
            Log::info('Creating LCS instance');

            return new LCS;
        });

        // Register DatabaseOperator
        $this->app->singleton(DatabaseDriverInterface::class, function () {
            return new DatabaseOperator;
        });

        // Register StateMachineFactory
        $this->app->singleton(StateMachineFactory::class, function ($app) {
            return new StateMachineFactory($app->make(LCS::class));
        });

        // Register ActionOrchestrator
        $this->app->singleton(ActionOrchestrator::class, function ($app) {
            return new ActionOrchestrator(
                $app->make(LCS::class),
                $app->make(StateMachineFactory::class)
            );
        });

        // Register LLCS
        $this->app->bind('llcs', function ($app) {
            Log::info('Creating LLCS instance');

            $databaseConnections = config('locospec-laravel.drivers.database_connections', []);

            // Register database operator with LCS
            $lcs = $app->make(LCS::class);
            $registryManager = $lcs->getRegistryManager();
            $dbOperator = $app->make(DatabaseDriverInterface::class);

            $registryManager->register(
                'database_driver',
                ['name' => 'default', 'className' => $dbOperator]
            );

            foreach ($databaseConnections as $key => $databaseConnection) {

                if ($databaseConnection === 'default') {
                    throw new Exception(
                        'Invalid connection name default'
                    );
                }

                $registryManager->register(
                    'database_driver',
                    ['name' => $databaseConnection, 'className' => $dbOperator]
                );
            }

            return new LLCS($app);
        });

        // Register routes
        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        $config = config('locospec-laravel.routing', []);

        Route::group([
            'prefix' => $config['prefix'] ?? 'lcs',
            'middleware' => $config['middleware'] ?? ['api'],
            'as' => ($config['as'] ?? 'lcs').'.',
        ], function () {
            Route::post('{model}/{action}', [ModelActionController::class, 'handle'])
                ->where('model', '[a-z0-9-]+')
                ->where('action', '[a-z0-9-]+')
                ->name('model.action');
        });
    }

    public function boot(): void
    {
        parent::boot();

        Log::info('Booting LLCS');

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
            throw $e;
        }
    }
}
