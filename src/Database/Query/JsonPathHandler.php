<?php

namespace Locospec\LLCS\Database\Query;

use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

class JsonPathHandler
{
    /**
     * Handle JSON path expression with optional alias
     */
    public function handle(string $path, ?string $alias = null): Expression
    {
        $expression = $this->buildJsonPath($path);

        if ($alias !== null) {
            $expression .= ' as ' . $alias;
        } elseif (str_contains($path, '->')) {
            $expression .= ' as ' . $this->generateAlias($path);
        }

        return DB::raw($expression);
    }

    /**
     * Build JSON path expression
     */
    private function buildJsonPath(string $path): string
    {
        $parts = explode('->', $path);
        $column = array_shift($parts);

        if (empty($parts)) {
            return $column;
        }

        return $column . '->' . implode('->', array_map(
            fn($part) => "'$part'",
            $parts
        )) . '>>';
    }

    /**
     * Generate readable alias from JSON path
     */
    private function generateAlias(string $path): string
    {
        // Remove JSON operators and clean the path
        $cleaned = preg_replace(
            ['/->/', '/[^a-zA-Z0-9_]/', '/_{2,}/'],
            ['_', '_', '_'],
            $path
        );

        return trim(strtolower($cleaned), '_');
    }

    /**
     * Get base column name from JSON path
     */
    public function getBaseColumn(string $path): string
    {
        return explode('->', $path)[0];
    }
}
