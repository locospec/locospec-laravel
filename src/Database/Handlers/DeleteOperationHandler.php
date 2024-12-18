<?php

namespace Locospec\LLCS\Database\Handlers;

use Illuminate\Support\Facades\DB;
use Locospec\LLCS\Database\Contracts\OperationHandlerInterface;
use Locospec\LLCS\Database\Query\QueryResultFormatter;
use Locospec\LLCS\Database\Query\WhereExpressionBuilder;

class DeleteOperationHandler implements OperationHandlerInterface
{
    private WhereExpressionBuilder $whereBuilder;

    private QueryResultFormatter $formatter;

    private ?string $lastQuery = null;

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
        $numRowsAffected = $query->delete();

        // Get the SQL query that would be executed
        $this->lastQuery = $query->toRawSql();

        // Format and return the results
        return $this->formatter->format(
            ['rows_affected' => $numRowsAffected],
            $this->lastQuery,
            $startTime
        );
    }

    public function getQuery(): ?string
    {
        return $this->lastQuery;
    }
}
