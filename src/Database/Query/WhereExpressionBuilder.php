<?php

namespace Locospec\LLCS\Database\Query;

use Illuminate\Database\Query\Builder;

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
            'is' => $query->$method($attribute, '=', $value),
            'is_not' => $query->$method($attribute, '!=', $value),
            'greater_than' => $query->$method($attribute, '>', $value),
            'less_than' => $query->$method($attribute, '<', $value),
            'greater_than_or_equal' => $query->$method($attribute, '>=', $value),
            'less_than_or_equal' => $query->$method($attribute, '<=', $value),
            'contains' => $query->$method($attribute, 'LIKE', "%$value%"),
            'not_contains' => $query->$method($attribute, 'NOT LIKE', "%$value%"),
            'is_any_of' => $query->{"{$method}In"}($attribute, (array) $value),
            'is_none_of' => $query->{"{$method}NotIn"}($attribute, (array) $value),
            'is_empty' => $query->{"{$method}Null"}($attribute),
            'is_not_empty' => $query->{"{$method}NotNull"}($attribute),
            default => throw new \InvalidArgumentException("Unsupported operator: {$operator}")
        };
    }

    private function normalizeOperator(string $operator): string
    {
        return strtolower($operator);
    }
}
