<?php

namespace Locospec\LLCS\Database\Handlers;

use Illuminate\Support\Facades\DB;
use Locospec\LLCS\Database\Contracts\OperationHandlerInterface;
use Locospec\LLCS\Database\Query\JsonPathHandler;
use Locospec\LLCS\Database\Query\QueryResultFormatter;
use Locospec\LLCS\Database\Query\WhereExpressionBuilder;

class SelectOperationHandler implements OperationHandlerInterface
{
    private WhereExpressionBuilder $whereBuilder;

    private JsonPathHandler $jsonPathHandler;

    private QueryResultFormatter $formatter;

    private ?string $lastQuery = null;

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

        // Handle pagination
        if (isset($operation['pagination'])) {
            return $this->handlePagination($query, $operation['pagination']);
        }

        // Execute query
        $startTime = microtime(true);
        $results = $query->select()->get();
        $this->lastQuery = $query->toRawSql();

        return $this->formatter->format($results, $this->lastQuery, $startTime);
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

        $this->lastQuery = $query->toRawSql();

        return $this->formatter->formatPagination($results, $this->lastQuery, $startTime);
    }

    public function getQuery(): ?string
    {
        return $this->lastQuery;
    }
}
