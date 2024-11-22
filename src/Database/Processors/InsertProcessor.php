<?php

namespace Locospec\LLCS\Database\Processors;

use Illuminate\Support\Facades\DB;
use Locospec\LCS\Database\Operations\DatabaseOperationInterface;
use Locospec\LCS\Database\Operations\InsertOperation;
use Locospec\LCS\Exceptions\InvalidArgumentException;
use Locospec\LLCS\Database\DatabaseUtils;

class InsertProcessor implements DatabaseProcessorInterface
{
    /**
     * Process an insert operation
     */
    public function process(DatabaseOperationInterface $operation): array
    {
        if (!$operation instanceof InsertOperation) {
            throw new InvalidArgumentException('InsertProcessor can only process InsertOperation');
        }

        $startTime = microtime(true);

        try {
            $table = $operation->getTable();
            $data = $operation->getData();

            // Get query builder instance
            $query = DB::table($table);

            // For getting the SQL that will be executed
            $sql = $this->constructSql($table, $data);
            $bindings = $this->extractBindings($data);

            // Execute the insert
            if ($operation->isBulkInsert()) {
                $result = $query->insert($data);
            } else {
                $result = $query->insert($data);
                // Get last insert ID if available
                if ($result) {
                    $result = DB::getPdo()->lastInsertId();
                }
            }

            $endTime = microtime(true);

            return [
                'result' => $result,
                'sql' => $sql,
                'bindings' => $bindings,
                'timing' => [
                    'started_at' => $startTime,
                    'ended_at' => $endTime,
                    'duration' => $endTime - $startTime
                ]
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Insert operation failed for table {$table}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Construct the SQL string for the insert operation
     *
     * @param string $table
     * @param array $data
     * @return string
     */
    private function constructSql(string $table, array $data): string
    {
        if (empty($data)) {
            throw new InvalidArgumentException('No data provided for insert');
        }

        // Determine if this is a bulk insert
        $isBulk = isset($data[0]) && is_array($data[0]);

        if ($isBulk) {
            $columns = array_keys($data[0]);
            $placeholders = [];

            // Create placeholder groups for each row
            for ($i = 0; $i < count($data); $i++) {
                $placeholders[] = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
            }

            $sql = sprintf(
                'insert into %s (%s) values %s',
                $this->quoteIdentifier($table),
                implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
                implode(', ', $placeholders)
            );
        } else {
            $columns = array_keys($data);

            $sql = sprintf(
                'insert into %s (%s) values (%s)',
                $this->quoteIdentifier($table),
                implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
                implode(', ', array_fill(0, count($columns), '?'))
            );
        }

        return $sql;
    }

    /**
     * Extract all bindings from the data
     */
    private function extractBindings(array $data): array
    {
        $bindings = [];

        if (isset($data[0]) && is_array($data[0])) {
            // Bulk insert
            foreach ($data as $row) {
                $bindings = array_merge($bindings, array_values($row));
            }
        } else {
            // Single insert
            $bindings = array_values($data);
        }

        return $bindings;
    }

    /**
     * Quote an identifier (table or column name)
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
