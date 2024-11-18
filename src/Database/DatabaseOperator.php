<?php

namespace Locospec\LLCS\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Locospec\LCS\Database\DatabaseOperatorInterface;
use Locospec\LLCS\Database\DatabaseUtils;
use Locospec\LCS\Query\CursorPagination;
use Locospec\LCS\Query\FilterGroup;
use Locospec\LCS\Query\Query;
use Locospec\LCS\Query\Pagination;

class DatabaseOperator implements DatabaseOperatorInterface
{
    private WhereExpressionBuilder $whereBuilder;
    private PaginationHandler $paginationHandler;
    private array $queryLog = [];

    public function __construct()
    {
        $this->whereBuilder = new WhereExpressionBuilder();
        $this->paginationHandler = new PaginationHandler();
    }

    private function executeOperation(callable $operation, Builder|string $query): array
    {
        $startTime = microtime(true);

        try {
            $sql = $query instanceof Builder ? $query->toRawSql() : $query;
            $result = $operation();

            $endTime = microtime(true);

            // Store in query log
            $this->queryLog[] = [
                'sql' => $sql,
                'start' => $startTime,
                'end' => $endTime
            ];

            return [
                'result' => $result,
                'sql' => $sql,
                'timing' => [
                    'started_at' => $startTime,
                    'ended_at' => $endTime,
                    'duration' => $endTime - $startTime
                ]
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Database operation failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    public function insert(string $table, array $data): array
    {
        $query = DB::table($table);
        return $this->executeOperation(fn() => $query->insert($data), $query);
    }

    public function update(string $table, array $data, FilterGroup $conditions): array
    {
        $query = DB::table($table);
        $this->whereBuilder->build($query, $conditions);
        return $this->executeOperation(fn() => $query->update($data), $query);
    }

    public function delete(string $table, FilterGroup $conditions): array
    {
        $query = DB::table($table);
        $this->whereBuilder->build($query, $conditions);
        return $this->executeOperation(fn() => $query->delete(), $query);
    }

    public function softDelete(string $table, FilterGroup $conditions): array
    {
        return $this->update($table, ['deleted_at' => now()], $conditions);
    }

    public function select(string $table, array $columns, FilterGroup $conditions): array
    {
        $query = DB::table($table);
        $columns = DatabaseUtils::addJsonSelects($columns);
        $query->select($columns);

        $this->whereBuilder->build($query, $conditions);

        return $this->executeOperation(
            fn() => $query->get()->map(fn($item) => DatabaseUtils::resultsToArray($item))->all(),
            $query
        );
    }

    public function count(string $table, FilterGroup $conditions): array
    {
        $query = DB::table($table);
        $this->whereBuilder->build($query, $conditions);
        return $this->executeOperation(fn() => $query->count(), $query);
    }

    public function paginate(string $table, array $columns, Pagination $pagination, ?FilterGroup $conditions = null): array
    {
        $query = $this->buildBaseQuery($table, $columns, $conditions);
        return $this->executeOperation(
            fn() => $this->paginationHandler->paginate($query, $pagination),
            $query
        );
    }

    public function cursorPaginate(string $table, array $columns, CursorPagination $cursor, ?FilterGroup $conditions = null): array
    {
        $query = $this->buildBaseQuery($table, $columns, $conditions);
        return $this->executeOperation(
            fn() => $this->paginationHandler->cursorPaginate($query, $cursor),
            $query
        );
    }

    public function raw(string $sql, array $bindings = []): array
    {
        return $this->executeOperation(
            fn() => DB::select($sql, $bindings),
            $sql
        );
    }

    public function getWhereAttributes(): array
    {
        return $this->whereBuilder->getWhereAttributes();
    }

    public function executeQuery(Query $query): array
    {
        $table = $query->getModelName();
        $queryBuilder = $this->buildBaseQuery($table, ['*'], $query->getFilters());

        // Apply sorts
        if ($sorts = $query->getSorts()) {
            foreach ($sorts->getSorts() as $sort) {
                $attribute = $sort->getAttribute();
                if (str_contains($attribute, '->')) {
                    $attribute = DatabaseUtils::handleJsonPathQuery($attribute);
                }
                $queryBuilder->orderBy($attribute, $sort->getDirection());
            }
        }

        // Apply pagination
        if ($pagination = $query->getPagination()) {
            if ($pagination instanceof CursorPagination) {
                return $this->cursorPaginate($table, ['*'], $pagination, $query->getFilters());
            }
            return $this->paginate($table, ['*'], $pagination, $query->getFilters());
        }

        // Execute regular select query
        return $this->executeOperation(
            fn() => $queryBuilder->get()->map(fn($item) => DatabaseUtils::resultsToArray($item))->all(),
            $queryBuilder
        );
    }

    private function buildBaseQuery(string $table, array $columns, ?FilterGroup $conditions = null): Builder
    {
        $query = DB::table($table);
        $columns = DatabaseUtils::addJsonSelects($columns);
        $query->select($columns);

        if ($conditions) {
            $this->whereBuilder->build($query, $conditions);
        }

        return $query;
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
