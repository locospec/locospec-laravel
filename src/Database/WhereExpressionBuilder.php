<?php

namespace Locospec\LLCS\Database;

use Illuminate\Database\Query\Builder;
use Locospec\LCS\Query\FilterGroup;

class WhereExpressionBuilder
{
    private array $whereAttributes = [];

    public function build(Builder $query, FilterGroup $filter): Builder
    {
        $type = strtolower($filter->getOperator());

        $query->where(function ($q) use ($filter, $type) {
            foreach ($filter->getConditions() as $condition) {
                $method = $type === 'or' ? 'orWhere' : 'where';

                if ($condition->isCompound()) {
                    $q->$method(function ($subQuery) use ($condition) {
                        $this->build($subQuery, $condition->getNestedConditions());
                    });
                } else {
                    $this->createExpression($q, $condition, $method);
                }
            }
        });

        return $query;
    }

    private function createExpression(Builder $query, $condition, string $method): void
    {
        $attribute = $condition->getAttribute();
        $operator = $condition->getOperator();
        $value = $condition->getValue();

        // Track attribute for column selection
        $this->whereAttributes[] = DatabaseUtils::getBaseColumn($attribute);

        // Handle JSON path in attribute if present
        if (str_contains($attribute, '->')) {
            $attribute = DatabaseUtils::handleJsonPathQuery($attribute);
        }

        switch (strtoupper($operator)) {
            case '=':
            case 'EQ':
                $query->$method($attribute, '=', $value);
                break;
            case '>':
            case 'GT':
                $query->$method($attribute, '>', $value);
                break;
            case '<':
            case 'LT':
                $query->$method($attribute, '<', $value);
                break;
            case 'LIKE':
                $query->$method($attribute, 'LIKE', '%'.$value.'%');
                break;
            case 'IN':
                $query->{"{$method}In"}($attribute, (array) $value);
                break;
            case 'NOT IN':
                $query->{"{$method}NotIn"}($attribute, (array) $value);
                break;
            case 'BETWEEN':
                $query->{"{$method}Between"}($attribute, (array) $value);
                break;
            case 'IS NULL':
                $query->{"{$method}Null"}($attribute);
                break;
            case 'IS NOT NULL':
                $query->{"{$method}NotNull"}($attribute);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported operator: {$operator}");
        }
    }

    public function getWhereAttributes(): array
    {
        return array_unique($this->whereAttributes);
    }
}
