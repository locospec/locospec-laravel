<?php

namespace Locospec\LLCS\Database\Processors;

use Locospec\LCS\Database\Operations\DatabaseOperationInterface;

interface DatabaseProcessorInterface
{
    /**
     * Process a database operation
     *
     * @param DatabaseOperationInterface $operation
     * @return array{
     *   result: mixed,
     *   sql: string,
     *   bindings: array,
     *   timing: array{
     *     started_at: float,
     *     ended_at: float,
     *     duration: float
     *   }
     * }
     */
    public function process(DatabaseOperationInterface $operation): array;
}
