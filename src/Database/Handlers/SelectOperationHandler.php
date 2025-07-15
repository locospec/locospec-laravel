<?php

namespace LCSLaravel\Database\Handlers;

use Illuminate\Support\Facades\DB;
use LCSLaravel\Database\Contracts\OperationHandlerInterface;
use LCSLaravel\Database\Query\JsonPathHandler;
use LCSLaravel\Database\Query\QueryResultFormatter;
use LCSLaravel\Database\Query\WhereExpressionBuilder;
use Illuminate\Database\Query\Builder;

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
                return str_contains($attr, '->')
                    ? $this->jsonPathHandler->handle($attr)
                    : $attr;
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

        // Handle pagination
        if (isset($operation['pagination'])) {
            return $this->handlePagination($query, $operation['pagination']);
        }

        // Execute query
        $startTime = microtime(true);
        $results = $query->get();
        $this->lastQuery = $query;
        // if (isset($operation['joins'])) {
        //     dd($results);
        // }

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
            $table = $table . ' as ' . $join['alias'];
        }

        // Apply the appropriate join type
        switch ($join['type']) {
            case 'inner':
            case 'left':
            case 'right':
                // These join types require an 'on' condition
                if (! isset($join['on'])) {
                    throw new \InvalidArgumentException($join['type'] . ' join requires an on condition');
                }

                $onCondition = $join['on'];

                // Validate on condition has exactly 3 elements
                if (count($onCondition) !== 3) {
                    throw new \InvalidArgumentException('Join on condition must have exactly 3 elements: [left_column, operator, right_column]');
                }

                [$leftColumn, $operator, $rightColumn] = $onCondition;

                if ($join['type'] === 'inner') {
                    $query->join($table, $leftColumn, $operator, $rightColumn);
                } elseif ($join['type'] === 'left') {
                    $query->leftJoin($table, $leftColumn, $operator, $rightColumn);
                } elseif ($join['type'] === 'right') {
                    $query->rightJoin($table, $leftColumn, $operator, $rightColumn);
                }
                break;

            case 'cross':
                // Cross join doesn't use on conditions
                $query->crossJoin($table);
                break;

            default:
                throw new \InvalidArgumentException('Invalid join type: ' . $join['type']);
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
                ['*'],
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
}
