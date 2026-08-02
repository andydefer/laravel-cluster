<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

/**
 * Calculates the length of a JSON array.
 *
 * This function is similar to COUNT but specifically for JSON arrays.
 * It provides compatibility across different database drivers.
 *
 * @example
 * $jsonLength = new JsonLengthFunction();
 * $jsonLength->execute(['a', 'b', 'c']); // 3
 * @example
 * // SQL generation for different drivers
 * $jsonLength->toSql('clusters', 'addresses', DatabaseDriver::MYSQL);
 * // JSON_LENGTH(clusters, '$.addresses')
 */
final class JsonLengthFunction extends AbstractSqlFunction
{
    public function getName(): string
    {
        return 'JSON_LENGTH';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string
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

    public function execute(mixed $value, array $args = []): int
    {
        if (is_array($value)) {
            return count($value);
        }

        return 0;
    }
}
