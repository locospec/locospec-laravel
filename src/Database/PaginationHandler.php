<?php

namespace Locospec\LLCS\Database;

use Illuminate\Database\Query\Builder;
use Locospec\LCS\Query\CursorPagination;
use Locospec\LCS\Query\Pagination;

class PaginationHandler
{
    public function paginate(Builder $query, Pagination $pagination): array
    {
        $result = $query->paginate(
            $pagination->getPerPage(),
            ['*'],
            'page',
            $pagination->getPage()
        );

        return [
            'data' => collect($result->items())->map(function ($item) {
                return DatabaseUtils::resultsToArray($item);
            })->all(),
            'pagination' => DatabaseUtils::formatPaginationMetadata($result),
        ];
    }

    public function cursorPaginate(Builder $query, CursorPagination $cursor): array
    {
        $result = $query->cursorPaginate(
            $cursor->getLimit(),
            ['*'],
            $cursor->getCursorColumn(),
            $cursor->getCursor()
        );

        return [
            'data' => collect($result->items())->map(function ($item) {
                return DatabaseUtils::resultsToArray($item);
            })->all(),
            'pagination' => DatabaseUtils::formatPaginationMetadata($result),
        ];
    }

    public function ensurePrimaryKeySort(Builder $query, string $primaryKey): void
    {
        $orders = collect($query->getQuery()->orders ?? []);

        // Check if primary key is already in sort
        if (! $orders->contains('column', $primaryKey)) {
            $query->orderBy($primaryKey);
        }
    }
}
