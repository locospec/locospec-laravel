<?php

namespace LCSLaravel\Database\Handlers;

use Illuminate\Support\Facades\DB;
use LCSLaravel\Database\Contracts\OperationHandlerInterface;
use LCSLaravel\Database\Query\QueryResultFormatter;
use LCSLaravel\Database\Query\WhereExpressionBuilder;
use Illuminate\Database\Query\Builder;

class DeleteOperationHandler implements OperationHandlerInterface
{
    private WhereExpressionBuilder $whereBuilder;

    private QueryResultFormatter $formatter;

    private ?Builder $lastQuery = null;

    public function __construct(
        WhereExpressionBuilder $whereBuilder,
        QueryResultFormatter $formatter
    ) {
        $this->whereBuilder = $whereBuilder;
        $this->formatter = $formatter;
    }

    /**
     * Handle delete operation following schema from database-operations/delete.json
     */
    public function handle(array $operation): array
    {
        if (! isset($operation['tableName'])) {
            throw new \InvalidArgumentException('Table name is required');
        }

        if (! isset($operation['filters'])) {
            throw new \InvalidArgumentException('Delete conditions (filters) are required');
        }

        $query = DB::connection($operation['connection'])->table($operation['tableName']);

        // Add where conditions
        $this->whereBuilder->build($query, $operation['filters']);

        // Start timing
        $startTime = microtime(true);

        // Execute the delete operation
        $numRowsAffected = $operation['softDelete'] ? $query->update([$operation['deleteColumn'] => now()]) : $query->delete();

        // Get the SQL query that would be executed
        $this->lastQuery = $query;

        // Format and return the results
        return $this->formatter->format(
            ['rows_affected' => $numRowsAffected],
            $this->lastQuery,
            $startTime
        );
    }

    public function getQuery(): ?Builder
    {
        return $this->lastQuery;
    }
}
