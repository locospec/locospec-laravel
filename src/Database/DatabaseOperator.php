<?php

namespace Locospec\LLCS\Database;

use Illuminate\Support\Facades\DB;
use Locospec\LCS\Registry\DatabaseDriverInterface;
use Locospec\LLCS\Database\Handlers\DeleteOperationHandler;
use Locospec\LLCS\Database\Handlers\InsertOperationHandler;
use Locospec\LLCS\Database\Handlers\SelectOperationHandler;
use Locospec\LLCS\Database\Handlers\UpdateOperationHandler;
use Locospec\LLCS\Database\Query\JsonPathHandler;
use Locospec\LLCS\Database\Query\QueryResultFormatter;
use Locospec\LLCS\Database\Query\WhereExpressionBuilder;

class DatabaseOperator implements DatabaseDriverInterface
{
    private SelectOperationHandler $selectHandler;

    private InsertOperationHandler $insertHandler;

    private UpdateOperationHandler $updateHandler;

    private DeleteOperationHandler $deleteHandler;

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

        $this->updateHandler = new UpdateOperationHandler(
            $whereBuilder,
            $formatter
        );

        $this->deleteHandler = new DeleteOperationHandler(
            $whereBuilder,
            $formatter
        );

        $this->insertHandler = new InsertOperationHandler(
            $formatter
        );
    }

    public function getName(): string
    {
        return 'laravel';
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
        // if (count($operations) === 1) {
        //     return $this->executeSingleOperation($operations[0]);
        // }

        $results = [];
        foreach ($operations as $operation) {
            $results[] = $this->executeSingleOperation($operation);
        }

        return $results;
    }

    private function executeSingleOperation(array $operation): array
    {
        $dbOpResult = match ($operation['type']) {
            'select' => $this->selectHandler->handle($operation),
            'insert' => $this->insertHandler->handle($operation),
            'update' => $this->updateHandler->handle($operation),
            'delete' => $this->deleteHandler->handle($operation),
            default => throw new \InvalidArgumentException("Unsupported operation type: {$operation['type']}")
        };

        $dbOpResult['operation'] = $operation;

        // Log the query
        if ($sql = $this->selectHandler->getQuery()) {
            $this->queryLog[] = $sql;
        }

        if ($sql = $this->insertHandler->getQuery()) {
            $this->queryLog[] = $sql;
        }

        if ($sql = $this->updateHandler->getQuery()) {
            $this->queryLog[] = $sql;
        }

        if ($sql = $this->deleteHandler->getQuery()) {
            $this->queryLog[] = $sql;
        }

        return $dbOpResult;
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
