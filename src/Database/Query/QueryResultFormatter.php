<?php

namespace LCSLaravel\Database\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

class QueryResultFormatter
{
    /**
     * Format query results with metadata
     */
    public function format(mixed $results, Builder $query, float $startTime): array
    {
        $endTime = microtime(true);
        $data = $this->formatResults($results);

        // dump($data);
        return [
            'result' => $data,
            'raw_sql' => $query->toRawSql(),
            'sql' => $query->toSql(),
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
    public function formatPagination(mixed $results, Builder $query, float $startTime): array
    {
        $results->through(function ($item) {
            return (array) $item;
        });

        $formatted = $this->format($results->items(), $query, $startTime);
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

        if (is_object($results)) {
            $data = $this->collectionDeepToArray($results);

            return $data;
            // return $this->collectionDeepToArray($results);
            // return json_decode(json_encode($results), true);
            // dd($results);
            // dd($this->measureMemoryUsage($results));
            // dd($results, $results->toArray(), json_decode(json_encode($results), true), $this->measureMemoryUsage($results));
        }

        return [];
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

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }

    private function measureMemoryUsage($results)
    {
        $initialMemory = memory_get_usage();

        // First encode
        $jsonString = json_encode($results);
        $afterEncode = memory_get_usage();

        // Then decode
        $decoded = json_decode($jsonString);
        $afterDecode = memory_get_usage();

        return [
            'initial' => $initialMemory,
            'after_encode' => $afterEncode,
            'after_decode' => $afterDecode,
            'json_size' => strlen($jsonString),
            'initial_memory_usage' => $this->formatBytes($initialMemory),
            'after_encode_memory_usage' => $this->formatBytes($afterEncode),
            'after_decode_memory_usage' => $this->formatBytes($afterDecode),
            'total_memory_difference' => $this->formatBytes($afterDecode - $initialMemory),
            'json_string_size' => $this->formatBytes(strlen($jsonString)),
        ];
    }

    private function collectionDeepToArray($data)
    {
        if (is_array($data) || $data instanceof \Illuminate\Support\Collection) {
            return collect($data)->map(function ($value) {
                return $this->collectionDeepToArray($value);
            })->toArray();
        }

        if (is_object($data)) {
            return $this->collectionDeepToArray(get_object_vars($data));
        }

        return $data;
    }

    private function deepToArray($data)
    {
        if (is_array($data)) {
            return array_map('deepToArray', $data); // Recursively map each element
        }

        if (is_object($data)) {
            return $this->deepToArray(get_object_vars($data)); // Convert object to array and recurse
        }

        return $data; // Return primitive values as-is
    }
}
