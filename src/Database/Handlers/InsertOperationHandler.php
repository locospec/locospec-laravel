<?php

namespace LCSLaravel\Database\Handlers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LCSLaravel\Database\Contracts\OperationHandlerInterface;
use LCSLaravel\Database\Query\QueryResultFormatter;

class InsertOperationHandler implements OperationHandlerInterface
{
    private QueryResultFormatter $formatter;

    private ?Builder $lastQuery = null;

    public function __construct(QueryResultFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * Handle insert operation following schema from database-operations/insert.json
     */
    public function handle(array $operation): array
    {
        if (! isset($operation['tableName'])) {
            throw new \InvalidArgumentException('Table name is required');
        }

        if (! isset($operation['data']) || ! is_array($operation['data']) || empty($operation['data'])) {
            throw new \InvalidArgumentException('Data array is required and cannot be empty');
        }

        $query = DB::connection($operation['connection'])->table($operation['tableName']);

        // Start timing
        $startTime = microtime(true);

        // Execute the insert operation
        $queryResult = $query->insert($operation['data']);

        // Get the SQL query that would be executed
        $this->lastQuery = $query;

        // Format and return the results
        return $this->formatter->format(
            $operation['data'],
            $this->lastQuery,
            $startTime
        );
    }

    public function getQuery(): ?Builder
    {
        return $this->lastQuery;
    }
}
