<?php

namespace LCSLaravel;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use LCSEngine\Actions\ActionOrchestrator;
use LCSEngine\Actions\CustomActionOrchestrator;
use LCSEngine\Actions\StateMachineFactory;
use LCSEngine\LCS;
use LCSEngine\Registry\DatabaseDriverInterface;
use LCSEngine\Registry\GeneratorInterface;
use LCSEngine\Registry\ValidatorInterface;
use LCSLaravel\Commands\LLCSCommand;
use LCSLaravel\Database\DatabaseOperator;
use LCSLaravel\Generators\DefaultGenerator;
use LCSLaravel\Http\Controllers\ModelActionController;
use LCSLaravel\Validations\DefaultValidator;
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

        // Register CustomActionOrchestrator
        $this->app->singleton(CustomActionOrchestrator::class, function ($app) {
            return new CustomActionOrchestrator(
                $app->make(LCS::class),
                $app->make(StateMachineFactory::class)
            );
        });

        // Bind the validator. Users can override this via the config if desired.
        $this->app->singleton(ValidatorInterface::class, function () {
            return new DefaultValidator;
        });

        // Bind the generator. Users can override this via the config if desired.
        $this->app->singleton(GeneratorInterface::class, function () {
            return new DefaultGenerator;
        });

        // Register LLCS
        $this->app->bind('llcs', function ($app) {
            $databaseConnections = config('locospec-laravel.drivers.database_connections', []);
            $validators = config('locospec-laravel.validators', []);
            $generators = config('locospec-laravel.generators', []);

            // Register database operator with LCS
            $lcs = $app->make(LCS::class);
            $registryManager = $lcs->getRegistryManager();
            $dbOperator = $app->make(DatabaseDriverInterface::class);
            $validator = $app->make(ValidatorInterface::class);
            $generator = $app->make(GeneratorInterface::class);

            // Register default implementations
            $registryManager->register(
                'database_driver',
                ['name' => 'default', 'className' => $dbOperator]
            );

            $registryManager->register(
                'generator',
                ['name' => 'default', 'className' => $generator]
            );

            $registryManager->register(
                'validator',
                ['name' => 'default', 'className' => $validator]
            );

            // Register additional database connections
            foreach ($databaseConnections as $connectionName) {
                if ($connectionName === 'default') {
                    throw new Exception('Invalid connection name: default');
                }

                $registryManager->register(
                    'database_driver',
                    ['name' => $connectionName, 'className' => $dbOperator]
                );
            }

            // Register additional validators
            foreach ($validators as $validatorName => $validatorClass) {
                if ($validatorName === 'default') {
                    throw new Exception('Invalid validator name: default');
                }

                $registryManager->register(
                    'validator',
                    ['name' => $validatorName, 'className' => $validatorClass]
                );
            }

            // Register additional generators
            foreach ($generators as $generatorName => $generatorClass) {
                if ($generatorName === 'default') {
                    throw new Exception('Invalid generator name: default');
                }

                $registryManager->register(
                    'generator',
                    ['name' => $generatorName, 'className' => $generatorClass]
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
            Route::post('{spec}/{action}', [ModelActionController::class, 'handle'])
                ->where('spec', '([a-zA-Z0-9]+(_[a-zA-Z0-9]+)*)')
                ->where('action', '(_[a-zA-Z0-9]+(_[a-zA-Z0-9]+)*)')
                ->name('spec.action');
        });
    }

    public function boot(): void
    {
        $loggingConfig = config('locospec-laravel.logging', []);
        $showLog = ! empty($loggingConfig) && $loggingConfig['enabled'];
        parent::boot();

        if ($showLog) {
            // Log::info('Booting LLCS');
        }

        try {
            if (! LCS::isInitialized()) {
                LCS::bootstrap([
                    'paths' => config('locospec-laravel.paths', []),
                    'logging' => $loggingConfig,
                    'cache_path' => config('locospec-laravel.cache_path', ''),
                ]);
                if ($showLog) {
                    // Log::info('LCS bootstrapped successfully');
                }
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
