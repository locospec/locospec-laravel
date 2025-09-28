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

        // Handle range date presets specially
        if (is_string($value) && $this->isRangeDatePreset($value)) {
            $this->buildRangeDateCondition($query, $attribute, $value, $method);

            return;
        }

        // Resolve date presets to actual dates
        $value = $this->resolveDatePresets($value);

        // Process the attribute for SQL generation
        $attribute = $this->processAttribute($attribute, $operator);

        // Handle date comparisons specially for "is" and "is_not" operators
        if ($this->isDateString($value) && in_array($operator, ['is', 'is_not'])) {
            $dateAttribute = $this->wrapWithDateFunction($attribute);
            match ($operator) {
                'is' => $query->$method($dateAttribute, '=', $value),
                'is_not' => $query->$method($dateAttribute, '!=', $value),
            };

            return;
        }

        // Handle date arrays with "is" operator (treat as range)
        if (is_array($value) && count($value) === 2 && in_array($operator, ['is', 'is_not'])) {
            [$startDate, $endDate] = $value;
            if ($this->isDateString($startDate) && $this->isDateString($endDate)) {
                $processedAttribute = $this->processAttribute($attribute);

                if ($operator === 'is') {
                    // "is" with date range means "between these dates"
                    $query->$method(function ($q) use ($processedAttribute, $startDate, $endDate) {
                        $q->where($processedAttribute, '>=', $startDate)
                            ->where($processedAttribute, '<=', $endDate);
                    });
                } else {
                    // "is_not" with date range means "not between these dates"
                    $query->$method(function ($q) use ($processedAttribute, $startDate, $endDate) {
                        $q->where($processedAttribute, '<', $startDate)
                            ->orWhere($processedAttribute, '>', $endDate);
                    });
                }

                return;
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

    private function processAttribute($attribute, ?string $operator = null)
    {
        if ($attribute instanceof \Illuminate\Database\Query\Expression) {
            return $attribute;
        }

        // Check if it's already a Query Expression (from DB::raw)
        if (! ($attribute instanceof \Illuminate\Database\Query\Expression)) {
            if ($operator && ($operator === 'contains' || $operator === 'not_contains')) {
                return DB::raw("LOWER({$attribute})");
            }

            return $attribute;
        }

        // Check if this is a SQL expression FIRST (before JSON path check)
        if (is_string($attribute) && preg_match('/^(CASE|CAST|COALESCE|CONCAT|NULLIF|IFNULL|IF)\s+/i', $attribute)) {
            if ($operator && ($operator === 'contains' || $operator === 'not_contains')) {
                return DB::raw("LOWER({$attribute})");
            } else {
                return DB::raw($attribute);
            }
        }
        // Only process JSON paths if it's not a SQL expression
        elseif (str_contains($attribute, '->')) {
            // $attribute = $this->jsonPathHandler->handle($attribute);

            if ($operator && ($operator === 'contains' || $operator === 'not_contains')) {
                return DB::raw("LOWER({$attribute})");
            } else {
                return DB::raw($attribute);
            }
        }

        // For regular column names, return as-is
        return $attribute;
    }

    private function isRangeDatePreset(string $value): bool
    {
        $rangePresets = [
            'next_7_days',
            'last_7_days',
            'this_week',
            'next_week',
            'last_week',
            'last_month',
            'this_month',
            'next_month',
            'today_and_earlier',
            'last_quarter',
            'this_quarter',
            'next_quarter',
            'overdue',
            'later_than_today',
        ];

        return in_array($value, $rangePresets);
    }

    private function buildRangeDateCondition(Builder $query, $attribute, string $preset, string $method): void
    {
        $dateRange = $this->getRangeDatesForPreset($preset);

        if (count($dateRange) === 2) {
            [$startDate, $endDate] = $dateRange;

            // Process the attribute the same way as in buildCondition for consistency
            $processedAttribute = $this->processAttribute($attribute);

            // Use a nested where group to ensure proper AND logic for the range
            $query->$method(function ($q) use ($processedAttribute, $startDate, $endDate) {
                $q->where($processedAttribute, '>=', $startDate)
                    ->where($processedAttribute, '<=', $endDate);
            });
        }
    }

    private function getRangeDatesForPreset(string $preset): array
    {
        return match ($preset) {
            'next_7_days' => [now()->format('Y-m-d'), now()->addDays(7)->format('Y-m-d')],
            'last_7_days' => [now()->subDays(7)->format('Y-m-d'), now()->format('Y-m-d')],
            'this_week' => [now()->startOfWeek()->format('Y-m-d'), now()->endOfWeek()->format('Y-m-d')],
            'next_week' => [
                now()->addWeek()->startOfWeek()->format('Y-m-d'),
                now()->addWeek()->endOfWeek()->format('Y-m-d'),
            ],
            'last_week' => [
                now()->subWeek()->startOfWeek()->format('Y-m-d'),
                now()->subWeek()->endOfWeek()->format('Y-m-d'),
            ],
            'last_month' => [
                now()->subMonth()->startOfMonth()->format('Y-m-d'),
                now()->subMonth()->endOfMonth()->format('Y-m-d'),
            ],
            'this_month' => [now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d')],
            'next_month' => [
                now()->addMonth()->startOfMonth()->format('Y-m-d'),
                now()->addMonth()->endOfMonth()->format('Y-m-d'),
            ],
            'today_and_earlier' => ['1900-01-01', now()->format('Y-m-d')],
            'last_quarter' => [
                now()->subQuarter()->startOfQuarter()->format('Y-m-d'),
                now()->subQuarter()->endOfQuarter()->format('Y-m-d'),
            ],
            'this_quarter' => [now()->startOfQuarter()->format('Y-m-d'), now()->endOfQuarter()->format('Y-m-d')],
            'next_quarter' => [
                now()->addQuarter()->startOfQuarter()->format('Y-m-d'),
                now()->addQuarter()->endOfQuarter()->format('Y-m-d'),
            ],
            'overdue' => ['1900-01-01', now()->subDay()->format('Y-m-d')],
            'later_than_today' => [now()->addDay()->format('Y-m-d'), '2100-12-31'],
            default => []
        };
    }

    private function resolveDatePresets($value)
    {
        // If value is not a string, return as-is
        if (! is_string($value)) {
            return $value;
        }

        // Check if it's already a date string (YYYY-MM-DD format)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Resolve single date presets only (range presets handled separately)
        return match ($value) {
            'today' => now()->format('Y-m-d'),
            'yesterday' => now()->subDay()->format('Y-m-d'),
            'tomorrow' => now()->addDay()->format('Y-m-d'),
            default => $value // Return original value if not a recognized preset
        };
    }

    private function isDateString($value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    private function wrapWithDateFunction($attribute)
    {
        // Check if it's already a Query Expression (from DB::raw)
        if ($attribute instanceof \Illuminate\Database\Query\Expression) {
            return DB::raw("DATE({$attribute})");
        }

        // Check if this is a SQL expression FIRST (before JSON path check)
        if (is_string($attribute) && preg_match('/^(CASE|CAST|COALESCE|CONCAT|NULLIF|IFNULL|IF)\s+/i', $attribute)) {
            return DB::raw("DATE({$attribute})");
        }
        // Handle JSON paths
        elseif (str_contains($attribute, '->')) {
            return DB::raw("DATE({$attribute})");
        }

        // For regular column names
        return DB::raw("DATE({$attribute})");
    }

    private function normalizeOperator(string $operator): string
    {
        return strtolower($operator);
    }
}
