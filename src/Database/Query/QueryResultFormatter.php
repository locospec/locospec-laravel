<?php

namespace Locospec\LLCS\Database\Query;

use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

class QueryResultFormatter
{
    /**
     * Format query results with metadata
     */
    public function format(mixed $results, string $sql, float $startTime): array
    {
        $endTime = microtime(true);

        return [
            'result' => $this->formatResults($results),
            'sql' => $sql,
            'timing' => [
                'started_at' => $startTime,
                'ended_at' => $endTime,
                'duration' => $endTime - $startTime,
            ],
        ];
    }

    /**
     * Format pagination results
     */
    public function formatPagination(mixed $results, string $sql, float $startTime): array
    {
        $formatted = $this->format($results->items(), $sql, $startTime);
        $formatted['pagination'] = $this->getPaginationMetadata($results);

        return $formatted;
    }

    /**
     * Format results into consistent array structure
     */
    private function formatResults(mixed $results): array
    {
        if (is_array($results)) {
            return $results;
        }

        return json_decode(json_encode($results), true);
    }

    /**
     * Get pagination metadata
     */
    private function getPaginationMetadata($paginator): array
    {
        if ($paginator instanceof CursorPaginator) {
            return [
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'has_more' => $paginator->hasMorePages(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
            ];
        }

        if ($paginator instanceof LengthAwarePaginator) {
            return [
                'count' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ];
        }

        throw new \InvalidArgumentException('Unsupported paginator type');
    }
}
