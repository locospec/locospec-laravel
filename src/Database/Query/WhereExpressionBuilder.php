<?php

namespace Locospec\LLCS\Database\Query;

use Illuminate\Database\Query\Builder;
use Locospec\LLCS\Database\Query\JsonPathHandler;

class WhereExpressionBuilder
{
    private JsonPathHandler $jsonPathHandler;

    public function __construct(JsonPathHandler $jsonPathHandler)
    {
        $this->jsonPathHandler = $jsonPathHandler;
    }

    /**
     * Build where conditions on query based on filter specification
     * Follows schema from database-operations/common.json#/definitions/filters
     */
    public function build(Builder $query, array $filters): void
    {
        $this->buildFilterGroup($query, $filters);
    }

    private function buildFilterGroup(Builder $query, array $filterGroup): void
    {
        $operator = strtolower($filterGroup['op']);
        $conditions = $filterGroup['conditions'];

        $query->where(function ($q) use ($conditions, $operator) {
            foreach ($conditions as $condition) {
                $method = $operator === 'or' ? 'orWhere' : 'where';

                // Handle nested filter groups
                if (isset($condition['op']) && isset($condition['conditions'])) {
                    $q->$method(function ($subQuery) use ($condition) {
                        $this->buildFilterGroup($subQuery, $condition);
                    });
                    continue;
                }

                $this->buildCondition($q, $condition, $method);
            }
        });
    }

    private function buildCondition(Builder $query, array $condition, string $method): void
    {
        $attribute = $condition['attribute'];
        $operator = $this->normalizeOperator($condition['op']);
        $value = $condition['value'] ?? null;

        if (str_contains($attribute, '->')) {
            $attribute = $this->jsonPathHandler->handle($attribute);
        }

        match ($operator) {
            'eq' => $query->$method($attribute, '=', $value),
            'neq' => $query->$method($attribute, '!=', $value),
            'gt' => $query->$method($attribute, '>', $value),
            'lt' => $query->$method($attribute, '<', $value),
            'gte' => $query->$method($attribute, '>=', $value),
            'lte' => $query->$method($attribute, '<=', $value),
            'like' => $query->$method($attribute, 'LIKE', "%$value%"),
            'notLike' => $query->$method($attribute, 'NOT LIKE', "%$value%"),
            'in' => $query->{"{$method}In"}($attribute, (array)$value),
            'notIn' => $query->{"{$method}NotIn"}($attribute, (array)$value),
            'isNull' => $query->{"{$method}Null"}($attribute),
            'isNotNull' => $query->{"{$method}NotNull"}($attribute),
            default => throw new \InvalidArgumentException("Unsupported operator: {$operator}")
        };
    }

    private function normalizeOperator(string $operator): string
    {
        return strtolower($operator);
    }
}
