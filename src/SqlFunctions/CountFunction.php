<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

/**
 * Counts elements in a JSON array or characters in a string.
 *
 * @example
 * $count = new CountFunction();
 * $count->execute(['a', 'b', 'c']); // 3
 * $count->execute('hello'); // 5
 * @example
 * // SQL generation for different drivers
 * $count->toSql('clusters', 'addresses', DatabaseDriver::SQLITE);
 * // json_array_length(clusters, '$.addresses')
 */
final class CountFunction extends AbstractSqlFunction
{
    public function getName(): string
    {
        return 'COUNT';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "json_array_length(%s, '$.%s')",
                $column,
                $path
            ),
            DatabaseDriver::MYSQL => sprintf(
                "JSON_LENGTH(%s, '$.%s')",
                $column,
                $path
            ),
            DatabaseDriver::PGSQL => sprintf(
                "jsonb_array_length(%s->'%s')",
                $column,
                $path
            ),
        };
    }

    public function getReturnType(): string
    {
        return 'int';
    }

    public function execute(mixed $value): int
    {
        if (is_array($value)) {
            return count($value);
        }

        if (is_string($value)) {
            return strlen($value);
        }

        return 0;
    }
}
