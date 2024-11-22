<?php

namespace Locospec\LLCS\Database\Handlers;

use Illuminate\Support\Facades\DB;
use Locospec\LLCS\Database\Contracts\OperationHandlerInterface;
use Locospec\LLCS\Database\Query\QueryResultFormatter;

class InsertOperationHandler implements OperationHandlerInterface
{
    private QueryResultFormatter $formatter;

    private ?string $lastQuery = null;

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

        $query = DB::table($operation['tableName']);

        // Start timing
        $startTime = microtime(true);

        // Execute the insert operation
        $query->insert($operation['data']);

        // Get the SQL query that would be executed
        $this->lastQuery = $query->toRawSql();

        // Format and return the results
        return $this->formatter->format(
            $operation['data'],
            $this->lastQuery,
            $startTime
        );
    }

    public function getQuery(): ?string
    {
        return $this->lastQuery;
    }
}
