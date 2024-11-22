<?php

namespace Locospec\LLCS;

use Locospec\LCS\LCS;
use Locospec\LCS\StateMachine\StateFlowPacket;

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

    public function getDefaultDatabaseDriver()
    {
        return $this->engine->getDefaultDriverOfType("database_driver");
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
    public function executeModelAction(string $modelName, string $actionName, array $input = []): StateFlowPacket
    {
        return $this->app->make('Locospec\LCS\Actions\ActionOrchestrator')
            ->execute($modelName, $actionName, $input);
    }
}
