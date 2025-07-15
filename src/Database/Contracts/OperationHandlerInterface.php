<?php

namespace LCSLaravel\Database\Contracts;

use Illuminate\Database\Query\Builder;

interface OperationHandlerInterface
{
    /**
     * Handle the database operation
     *
     * @param  array  $operation  The operation parameters
     * @return array Operation result with metadata
     */
    public function handle(array $operation): array;

    /**
     * Get the SQL query that would be executed
     *
     * @return string|null The SQL query or null if not applicable
     */
    public function getQuery(): ?Builder;
}
