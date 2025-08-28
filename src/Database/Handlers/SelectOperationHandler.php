<?php

namespace LCSLaravel\Database\Handlers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LCSLaravel\Database\Contracts\OperationHandlerInterface;
use LCSLaravel\Database\Query\JsonPathHandler;
use LCSLaravel\Database\Query\QueryResultFormatter;
use LCSLaravel\Database\Query\WhereExpressionBuilder;

class SelectOperationHandler implements OperationHandlerInterface
{
    private WhereExpressionBuilder $whereBuilder;

    private JsonPathHandler $jsonPathHandler;

    private QueryResultFormatter $formatter;

    private ?Builder $lastQuery = null;

    public function __construct(
        WhereExpressionBuilder $whereBuilder,
        JsonPathHandler $jsonPathHandler,
        QueryResultFormatter $formatter
    ) {
        $this->whereBuilder = $whereBuilder;
        $this->jsonPathHandler = $jsonPathHandler;
        $this->formatter = $formatter;
    }

    /**
     * Handle select operation following schema from database-operations/select.json
     */
    public function handle(array $operation): array
    {
        if (! isset($operation['tableName'])) {
            throw new \InvalidArgumentException('Table name is required');
        }

        $query = DB::connection($operation['connection'])->table($operation['tableName']);

        // Handle attributes (select columns)
        if (isset($operation['attributes'])) {
            $attributes = array_map(function ($attr) {
                // Check if this attribute already has an alias (contains " AS ")
                if (str_contains($attr, ' AS ')) {
                    // This is a complete SQL expression with alias, wrap it with DB::raw
                    return DB::raw($attr);
                }

                // Check if this is a CASE expression FIRST (before JSON path check)
                if (preg_match('/^CASE\s+/i', $attr)) {
                    return DB::raw($attr);
                }

                // Check if this is an aggregate function (COUNT, SUM, AVG, MIN, MAX)
                // Pattern matches: FUNCTION(...) AS alias or FUNCTION(*)
                if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX)\s*\(/i', $attr)) {
                    return DB::raw($attr);
                }

                // Check for other SQL expressions (CAST, COALESCE, CONCAT, etc.)
                if (preg_match('/^(CAST|COALESCE|CONCAT|NULLIF|IFNULL|IF)\s*\(/i', $attr)) {
                    return DB::raw($attr);
                }

                // Handle JSON paths (only if not a SQL expression)
                if (str_contains($attr, '->')) {
                    // return $this->jsonPathHandler->handle($attr);
                    return DB::raw($attr);
                }

                return $attr;
            }, $operation['attributes']);
            $query->select($attributes);
        }

        // Handle filters
        if (isset($operation['filters'])) {
            $this->whereBuilder->build($query, $operation['filters']);
        }

        // Handle sorts
        if (isset($operation['sorts'])) {
            foreach ($operation['sorts'] as $sort) {
                if (! isset($sort['attribute'])) {
                    continue;
                }
                $attribute = str_contains($sort['attribute'], '->')
                    ? $this->jsonPathHandler->handle($sort['attribute'])
                    : $sort['attribute'];
                $direction = $sort['direction'] ?? 'ASC';
                $query->orderBy($attribute, $direction);
            }
        }

        // Add condition for non-deleted records
        if (isset($operation['deleteColumn'])) {
            $query->whereNull($operation['deleteColumn']);
        }

        // Handle joins
        if (isset($operation['joins'])) {
            foreach ($operation['joins'] as $join) {
                $this->applyJoin($query, $join);
            }
            // dd($attributes);
            // dd($query->toRawSql()); // For debugging purposes, remove in production
        }

        // Handle groupBy
        if (isset($operation['groupBy']) && is_array($operation['groupBy'])) {
            // Laravel's groupBy can accept multiple columns as separate arguments or as an array
            // Since we're getting an array, we can pass it directly
            $query->groupBy(...$operation['groupBy']);
        }

        // if (isset($operation['groupBy'])) {
        //     dd($query->toRawSql()); // For debugging purposes, remove in production
        // }

        // Handle pagination
        if (isset($operation['pagination'])) {
            return $this->handlePagination($query, $operation['pagination']);
        }

        // Execute query
        $startTime = microtime(true);
        $results = $query->get();
        $this->lastQuery = $query;

        return $this->formatter->format($results, $this->lastQuery, $startTime);
    }

    /**
     * Apply join to the query based on join configuration
     */
    private function applyJoin($query, array $join): void
    {
        if (! isset($join['type']) || ! isset($join['table'])) {
            throw new \InvalidArgumentException('Join requires type and table properties');
        }

        $table = $join['table'];

        // Handle alias if provided
        if (isset($join['alias'])) {
            $table = $table.' as '.$join['alias'];
        }

        // Apply the appropriate join type
        switch ($join['type']) {
            case 'inner':
            case 'left':
            case 'right':
                // These join types require an 'on' condition
                if (! isset($join['on'])) {
                    throw new \InvalidArgumentException($join['type'].' join requires an on condition');
                }

                $onCondition = $join['on'];

                // Validate on condition has exactly 3 elements
                if (count($onCondition) !== 3) {
                    throw new \InvalidArgumentException('Join on condition must have exactly 3 elements: [left_column, operator, right_column]');
                }

                [$leftColumn, $operator, $rightColumn] = $onCondition;

                // Apply type casting if column types are available and different
                if (isset($join['left_col_type']) && isset($join['right_col_type'])) {
                    [$leftColumn, $rightColumn] = $this->applyTypeCasting(
                        $leftColumn,
                        $rightColumn,
                        $join['left_col_type'],
                        $join['right_col_type']
                    );
                }

                if ($join['type'] === 'inner') {
                    $query->join($table, DB::raw($leftColumn), $operator, DB::raw($rightColumn));
                } elseif ($join['type'] === 'left') {
                    $query->leftJoin($table, DB::raw($leftColumn), $operator, DB::raw($rightColumn));
                } elseif ($join['type'] === 'right') {
                    $query->rightJoin($table, DB::raw($leftColumn), $operator, DB::raw($rightColumn));
                }
                break;

            case 'cross':
                // Cross join doesn't use on conditions
                $query->crossJoin($table);
                break;

            default:
                throw new \InvalidArgumentException('Invalid join type: '.$join['type']);
        }
    }

    /**
     * Handle pagination following schema specification
     */
    private function handlePagination($query, array $pagination): array
    {
        $startTime = microtime(true);

        if ($pagination['type'] === 'cursor') {
            $results = $query->cursorPaginate(
                $pagination['per_page'],
                '*',
                'cursor',
                $pagination['cursor'] ?? null
            );
        } else {
            $results = $query->paginate(
                $pagination['per_page'],
                ['*'],
                'page',
                $pagination['page']
            );
        }

        $this->lastQuery = $query;

        return $this->formatter->formatPagination($results, $this->lastQuery, $startTime);
    }

    public function getQuery(): ?Builder
    {
        return $this->lastQuery;
    }

    /**
     * Apply type casting to join columns when types don't match
     */
    private function applyTypeCasting(string $leftColumn, string $rightColumn, string $leftColType, string $rightColType): array
    {
        // If types match, no casting needed
        if ($leftColType === $rightColType) {
            return [$leftColumn, $rightColumn];
        }

        // Apply PostgreSQL type casting for common mismatches
        // Most common case: string to uuid
        if ($leftColType === 'string' && $rightColType === 'uuid') {
            $leftColumn = $leftColumn.'::uuid';
        } elseif ($leftColType === 'uuid' && $rightColType === 'string') {
            $rightColumn = $rightColumn.'::uuid';
        }
        // Handle other potential type mismatches
        elseif ($leftColType === 'string' && in_array($rightColType, ['integer', 'id'])) {
            $leftColumn = $leftColumn.'::integer';
        } elseif (in_array($leftColType, ['integer', 'id']) && $rightColType === 'string') {
            $rightColumn = $rightColumn.'::integer';
        }

        return [$leftColumn, $rightColumn];
    }
}
