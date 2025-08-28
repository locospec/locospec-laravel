<?php

namespace LCSLaravel\Database\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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

        // Check if it's already a Query Expression (from DB::raw)
        if (! ($attribute instanceof \Illuminate\Database\Query\Expression)) {
            // Check if this is a SQL expression FIRST (before JSON path check)
            if (is_string($attribute) && preg_match('/^(CASE|CAST|COALESCE|CONCAT|NULLIF|IFNULL|IF)\s+/i', $attribute)) {
                if ($operator === 'contains' || $operator === 'not_contains') {
                    $attribute = DB::raw("LOWER({$attribute})");
                } else {
                    $attribute = DB::raw($attribute);
                }
            }
            // Only process JSON paths if it's not a SQL expression
            elseif (str_contains($attribute, '->')) {
                // $attribute = $this->jsonPathHandler->handle($attribute);

                if ($operator === 'contains' || $operator === 'not_contains') {
                    $attribute = DB::raw("LOWER({$attribute})");
                } else {
                    $attribute = DB::raw($attribute);
                }
            }
        } else {
            if ($operator === 'contains' || $operator === 'not_contains') {
                $attribute = DB::raw("LOWER({$attribute})");
            }
        }

        match ($operator) {
            'is' => $query->$method($attribute, '=', $value),
            'is_not' => $query->$method($attribute, '!=', $value),
            'greater_than' => $query->$method($attribute, '>', $value),
            'less_than' => $query->$method($attribute, '<', $value),
            'greater_than_or_equal' => $query->$method($attribute, '>=', $value),
            'less_than_or_equal' => $query->$method($attribute, '<=', $value),
            // 'contains' => $query->$method($attribute, 'ILIKE', "%$value%"),
            // 'not_contains' => $query->$method($attribute, 'NOT ILIKE', "%$value%"),
            'contains' => $query->{$method}($attribute, 'LIKE', '%'.strtolower($value).'%'),
            'not_contains' => $query->{$method}($attribute, 'NOT LIKE', '%'.strtolower($value).'%'),
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
