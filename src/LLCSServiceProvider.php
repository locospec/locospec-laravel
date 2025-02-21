<?php

namespace Locospec\LLCS;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Locospec\Engine\Actions\ActionOrchestrator;
use Locospec\Engine\Actions\StateMachineFactory;
use Locospec\Engine\LCS;
use Locospec\Engine\Registry\DatabaseDriverInterface;
use Locospec\Engine\Registry\ValidatorInterface;
use Locospec\LLCS\Commands\LLCSCommand;
use Locospec\LLCS\Database\DatabaseOperator;
use Locospec\LLCS\Http\Controllers\ModelActionController;
use Locospec\LLCS\Validations\DefaultValidator;
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
            LCS::getLogger()->info('Creating LCS instance');

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

        // Bind the validator. Users can override this via the config if desired.
        $this->app->singleton(ValidatorInterface::class, function () {
            $validatorClass = config('locospec-laravel.validator', new DefaultValidator);

            return $validatorClass;
            // return new DefaultValidator;
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
                    'logging' => config('locospec-laravel.logging', []),
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
