<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

/**
 * Calculates the length of a string or the number of elements in an array.
 *
 * This function returns the length of a string (number of characters) or
 * the count of elements in an array. It is used in cluster queries to filter
 * based on string length or array size.
 *
 * @example
 * $length = new LengthFunction();
 * $length->execute('hello'); // 5
 * $length->execute(['a', 'b', 'c']); // 3
 * @example
 * // SQL generation for different drivers
 * $length->toSql('clusters', 'name', DatabaseDriver::SQLITE);
 * // LENGTH(json_extract(clusters, '$.name'))
 *
 * $length->toSql('clusters', 'name', DatabaseDriver::MYSQL);
 * // LENGTH(JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.name')))
 *
 * $length->toSql('clusters', 'name', DatabaseDriver::PGSQL);
 * // LENGTH(clusters->>'name')
 * @example
 * // Usage in a query
 * $filtered = $clusterQuery->filter($clusters, 'LENGTH(name) > 5');
 */
final class LengthFunction extends AbstractSqlFunction
{
    /**
     * Returns the name of the function.
     *
     * This name is used in cluster query expressions (e.g., `LENGTH(name) > 5`).
     *
     * @return string The function name
     */
    public function getName(): string
    {
        return 'LENGTH';
    }

    /**
     * Generates the SQL expression for the Length function.
     *
     * Returns database-specific SQL that calculates the length of a JSON value.
     * For SQLite: uses `LENGTH(json_extract(...))`
     * For MySQL: uses `LENGTH(JSON_UNQUOTE(JSON_EXTRACT(...)))` to handle JSON strings
     * For PostgreSQL: uses `LENGTH(column->>'path')`
     *
     * @param  string  $column  The database column containing JSON data
     * @param  string  $path  The JSON path to the value
     * @param  DatabaseDriver  $driver  The database driver
     * @param  array  $args  Additional arguments (not used for this function)
     * @return string The SQL expression
     */
    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "LENGTH(json_extract(%s, '$.%s'))",
                $column,
                $path
            ),
            DatabaseDriver::MYSQL => sprintf(
                "LENGTH(JSON_UNQUOTE(JSON_EXTRACT(%s, '$.%s')))",
                $column,
                $path
            ),
            DatabaseDriver::PGSQL => sprintf(
                "LENGTH(%s->>'%s')",
                $column,
                $path
            ),
        };
    }

    /**
     * Returns the return type of the function.
     *
     * The Length function returns an integer representing the length
     * of the string or the number of elements in the array.
     *
     * @return string The return type ('int')
     */
    public function getReturnType(): string
    {
        return 'int';
    }

    /**
     * Returns the default value when the result is empty or null.
     *
     * @return int The default value (0)
     */
    public function getDefaultValue(): int
    {
        return 0;
    }

    /**
     * Executes the Length function on a value.
     *
     * For strings: returns the number of characters.
     * For arrays: returns the number of elements.
     * For other types: returns 0.
     *
     * @param  mixed  $value  The value to evaluate (string or array)
     * @param  array  $args  Additional arguments (not used for this function)
     * @return int The length of the string or the count of the array
     */
    public function execute(mixed $value, array $args = []): int
    {
        if (is_string($value)) {
            return strlen($value);
        }

        if (is_array($value)) {
            return count($value);
        }

        return 0;
    }
}
