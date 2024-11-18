<?php

namespace Locospec\LLCS\Database;

use Illuminate\Support\Facades\DB;
use Locospec\LCS\Schema\Schema;

class DatabaseUtils
{
    /**
     * Format operation result with metadata
     */
    public static function formatResult(mixed $result, string $sql, float $startTime): array
    {
        $endTime = microtime(true);

        return [
            'result' => $result,
            'sql' => $sql,
            'timing' => [
                'started_at' => $startTime,
                'ended_at' => $endTime,
                'duration' => $endTime - $startTime,
            ],
        ];
    }

    /**
     * Encode array values as JSON strings based on schema definition
     */
    public static function encodeJsonFields(array $input, Schema $schema): array
    {
        $encodedInput = [];
        $schemaArray = $schema->toArray();

        foreach ($input as $key => $value) {
            if (isset($schemaArray[$key])) {
                $propertySchema = $schemaArray[$key];

                if (self::isJsonField($propertySchema) && is_array($value)) {
                    $encodedInput[$key] = json_encode($value);
                } else {
                    $encodedInput[$key] = $value;
                }
            } else {
                $encodedInput[$key] = $value;
            }
        }

        return $encodedInput;
    }

    /**
     * Decode JSON strings to arrays based on schema definition
     */
    public static function decodeJsonFields(array $input, Schema $schema): array
    {
        $decodedInput = [];
        $schemaArray = $schema->toArray();

        foreach ($input as $key => $value) {
            if (isset($schemaArray[$key])) {
                $propertySchema = $schemaArray[$key];

                if (self::isJsonField($propertySchema) && is_string($value)) {
                    $decodedValue = json_decode($value, true);
                    $decodedInput[$key] = ($decodedValue !== null) ? $decodedValue : $value;
                } else {
                    $decodedInput[$key] = $value;
                }
            } else {
                $decodedInput[$key] = $value;
            }
        }

        return $decodedInput;
    }

    /**
     * Check if a field should be treated as JSON
     */
    private static function isJsonField(array $propertySchema): bool
    {
        return in_array($propertySchema['type'] ?? '', ['object', 'array']) ||
            (($propertySchema['type'] ?? '') === 'string' &&
                ($propertySchema['format'] ?? '') === 'json');
    }

    /**
     * Ensure primary keys are included in sort parameters
     */
    public static function ensurePrimaryKeysInSort(array $sort, array|string $primaryKeys): array
    {
        $primaryKeys = is_array($primaryKeys) ? $primaryKeys : [$primaryKeys];
        $sortAttributes = array_column($sort, 'attribute');

        foreach ($primaryKeys as $primaryKey) {
            if (! in_array($primaryKey, $sortAttributes)) {
                $sort[] = [
                    'attribute' => $primaryKey,
                    'direction' => 'ASC',
                ];
            }
        }

        return $sort;
    }

    /**
     * Handle JSON path queries with optional alias
     */
    public static function handleJsonPathQuery(string $path, ?string $alias = null)
    {
        $attribute = self::buildJsonPathQuery($path);

        if ($alias !== null) {
            $attribute .= ' as '.$alias;
        } elseif (str_contains($path, '->')) {
            $attribute .= ' as '.self::generateJsonPathAlias($path);
        }

        return DB::raw($attribute);
    }

    /**
     * Build a JSON path query
     */
    public static function buildJsonPathQuery(string $path): string
    {
        $parts = explode('->', $path);
        $column = array_shift($parts);
        $lastIndex = count($parts) - 1;

        if (empty($parts)) {
            return $column;
        }

        return $column.'->'.implode('->', array_map(
            fn ($part, $index) => sprintf(
                "'%s'%s",
                $part,
                $index === $lastIndex ? '>' : ''
            ),
            $parts,
            range(0, $lastIndex)
        ));
    }

    /**
     * Generate a readable alias for JSON path
     */
    public static function generateJsonPathAlias(string $path): string
    {
        // Remove JSON operators and clean the path
        $cleaned = preg_replace(
            ['/->/', '/[^a-zA-Z0-9_]/', '/_{2,}/'],
            ['_', '_', '_'],
            $path
        );

        // Trim underscores and convert to lowercase
        return trim(strtolower($cleaned), '_');
    }

    /**
     * Convert query results to arrays
     */
    public static function resultsToArray(mixed $results): array
    {
        if (is_array($results)) {
            return $results;
        }

        return json_decode(json_encode($results), true);
    }

    /**
     * Format pagination metadata
     */
    public static function formatPaginationMetadata(mixed $paginator): array
    {
        if (method_exists($paginator, 'nextCursor')) {
            // Cursor pagination
            return [
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'has_more' => $paginator->hasMorePages(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
            ];
        }

        // Offset pagination
        return [
            'count' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'total_pages' => $paginator->lastPage(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }

    /**
     * Create a raw SQL expression
     */
    public static function raw(string $expression): \Illuminate\Database\Query\Expression
    {
        return DB::raw($expression);
    }

    /**
     * Extract base column from JSON path
     */
    public static function getBaseColumn(string $path): string
    {
        return explode('->', $path)[0];
    }

    /**
     * Add select expressions for JSON paths
     */
    public static function addJsonSelects(array $fields): array
    {
        return array_map(function ($field) {
            if (str_contains($field, '->')) {
                return self::handleJsonPathQuery($field);
            }

            return $field;
        }, $fields);
    }

    /**
     * Format query results for database operation
     */
    public static function formatQueryResults(mixed $results, float $startTime, string $sql): array
    {
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        return [
            'result' => self::resultsToArray($results),
            'timing' => [
                'started_at' => $startTime,
                'ended_at' => $endTime,
                'duration' => $duration,
            ],
            'sql' => $sql,
        ];
    }
}
