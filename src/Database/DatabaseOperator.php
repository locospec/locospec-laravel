<?php

namespace Locospec\LLCS\Database;

use Illuminate\Support\Facades\DB;
use Locospec\LCS\Database\DatabaseOperatorInterface;
use Locospec\LLCS\Database\Handlers\SelectOperationHandler;
use Locospec\LLCS\Database\Query\JsonPathHandler;
use Locospec\LLCS\Database\Query\QueryResultFormatter;
use Locospec\LLCS\Database\Query\WhereExpressionBuilder;

class DatabaseOperator implements DatabaseOperatorInterface
{
    private SelectOperationHandler $selectHandler;

    private array $queryLog = [];

    public function __construct()
    {
        $jsonPathHandler = new JsonPathHandler;
        $whereBuilder = new WhereExpressionBuilder($jsonPathHandler);
        $formatter = new QueryResultFormatter;

        $this->selectHandler = new SelectOperationHandler(
            $whereBuilder,
            $jsonPathHandler,
            $formatter
        );
    }

    public function run(array $operations): array
    {
        $useTransaction = $this->needsTransaction($operations);

        if ($useTransaction) {
            return DB::transaction(function () use ($operations) {
                return $this->executeOperations($operations);
            });
        }

        return $this->executeOperations($operations);
    }

    private function executeOperations(array $operations): array
    {
        if (count($operations) === 1) {
            return $this->executeSingleOperation($operations[0]);
        }

        $results = [];
        foreach ($operations as $operation) {
            $results[] = $this->executeSingleOperation($operation);
        }

        return $results;
    }

    private function executeSingleOperation(array $operation): array
    {
        $result = match ($operation['type']) {
            'select' => $this->selectHandler->handle($operation),
            default => throw new \InvalidArgumentException("Unsupported operation type: {$operation['type']}")
        };

        // Log the query
        if ($sql = $this->selectHandler->getQuery()) {
            $this->queryLog[] = $sql;
        }

        return $result;
    }

    private function needsTransaction(array $operations): bool
    {
        if (count($operations) > 1) {
            return true;
        }

        if (count($operations) === 1) {
            return ! in_array($operations[0]['type'], ['select', 'count']);
        }

        return false;
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }
}
