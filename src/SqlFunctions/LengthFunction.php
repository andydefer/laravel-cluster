<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

/**
 * Calculates the length of a string or the number of elements in an array.
 *
 * @example
 * $length = new LengthFunction();
 * $length->execute('hello'); // 5
 * $length->execute(['a', 'b', 'c']); // 3
 * @example
 * // SQL generation for different drivers
 * $length->toSql('clusters', 'name', DatabaseDriver::PGSQL);
 * // LENGTH(clusters->>'name')
 */
final class LengthFunction extends AbstractSqlFunction
{
    public function getName(): string
    {
        return 'LENGTH';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "LENGTH(json_extract(%s, '$.%s'))",
                $column,
                $path
            ),
            DatabaseDriver::MYSQL => sprintf(
                "LENGTH(JSON_EXTRACT(%s, '$.%s'))",
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

    public function getReturnType(): string
    {
        return 'int';
    }

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
