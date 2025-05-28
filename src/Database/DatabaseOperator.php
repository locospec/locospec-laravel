<?php

namespace LCSLaravel\Database;

use Illuminate\Support\Facades\DB;
use LCSEngine\Registry\DatabaseDriverInterface;
use LCSLaravel\Database\Handlers\DeleteOperationHandler;
use LCSLaravel\Database\Handlers\InsertOperationHandler;
use LCSLaravel\Database\Handlers\SelectOperationHandler;
use LCSLaravel\Database\Handlers\UpdateOperationHandler;
use LCSLaravel\Database\Query\JsonPathHandler;
use LCSLaravel\Database\Query\QueryResultFormatter;
use LCSLaravel\Database\Query\WhereExpressionBuilder;
use LCSLaravel\Facades\LLCS;

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
        LLCS::getLogger()->info('Running database operations', ['operationCount' => count($operations)]);
        $useTransaction = $this->needsTransaction($operations);

        if ($useTransaction) {
            LLCS::getLogger()->info('Executing operations inside a transaction');

            return DB::transaction(function () use ($operations) {
                return $this->executeOperations($operations);
            });
        }

        LLCS::getLogger()->info('Executing operations without a transaction');

        return $this->executeOperations($operations);
    }

    private function executeOperations(array $operations): array
    {
        // if (count($operations) === 1) {
        //     return $this->executeSingleOperation($operations[0]);
        // }
        LLCS::getLogger()->info('Processing multiple database operations', ['operationCount' => count($operations)]);
        $results = [];
        foreach ($operations as $operation) {
            $results[] = $this->executeSingleOperation($operation);
        }
        LLCS::getLogger()->info('All operations executed successfully');

        return $results;
    }

    private function executeSingleOperation(array $operation): array
    {
        $connection = $operation['connection'] !== 'default' ? $operation['connection'] : env('DB_CONNECTION');
        $operation['connection'] = $connection;

        LLCS::getLogger()->info('Executing single database operation', [
            'operationType' => $operation['type'],
            'connection' => $connection,
        ]);

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

        LLCS::getLogger()->info('Operation executed successfully', [
            'operationType' => $operation['type'],
            'query' => $dbOpResult['sql'] ?? null,
        ]);

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
