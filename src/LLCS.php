<?php

namespace Locospec\LLCS;

use Locospec\LCS\LCS;

class LLCS
{
    protected $app;

    protected $engine;

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

    // Add methods to expose EnginePhp functionality with Laravel integration
    public function processSpecification(string $path)
    {
        return $this->engine->processSpecificationFile($path);
    }

    // Add Laravel-specific helper methods
    public function getModelDefinition(string $modelName)
    {
        return $this->engine->getRegistryManager()->get('model', $modelName);
    }
}
