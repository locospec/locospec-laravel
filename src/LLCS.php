<?php

namespace Locospec\LLCS;

use Locospec\Engine\LCS;
use Locospec\Engine\Registry\ValidatorInterface;
use Locospec\Engine\Registry\GeneratorInterface;

class LLCS
{
    protected $app;

    protected LCS $engine;

    public function __construct($app)
    {
        $this->app = $app;
        $this->engine = $app->make(LCS::class);
    }

    public function getEngine(): LCS
    {
        return $this->engine;
    }

    public function getLogger()
    {
        return $this->engine->getLogger();
    }

    public function getRegistryManager()
    {
        return $this->engine->getRegistryManager();
    }

    public function getDefaultDatabaseDriver()
    {
        return $this->engine->getDefaultDriverOfType('database_driver');
    }

    public function processSpecification(string $path)
    {
        return $this->engine->processSpecificationFile($path);
    }

    public function getModelDefinition(string $modelName)
    {
        return $this->engine->getRegistryManager()->get('model', $modelName);
    }

    /**
     * Execute a model action
     */
    public function executeModelAction(ValidatorInterface $curdValidator, GeneratorInterface $generator, string $modelName, string $actionName, array $input = []): array
    {
        $data = $this->app->make('Locospec\Engine\Actions\ActionOrchestrator')
            ->execute($curdValidator, $generator, $modelName, $actionName, $input);

        return $data->currentOutput;
    }

    public function getDefaultValidator(): ValidatorInterface
    {
        return $this->app->make(ValidatorInterface::class);
    }
    
    public function getDefaultGenerator(): GeneratorInterface
    {
        return $this->app->make(GeneratorInterface::class);
    }
}
