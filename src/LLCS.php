<?php

namespace Locospec\LLCS;

use Locospec\LCS\LCS;

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

    public function getRegistryManager()
    {
        return $this->engine->getRegistryManager();
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
    public function executeModelAction(string $modelName, string $actionName, array $input = []): array
    {
        return $this->app->make('Locospec\LCS\Actions\ActionOrchestrator')
            ->execute($modelName, $actionName, $input);
    }
}
