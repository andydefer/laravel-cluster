<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Contracts\SqlFunctionInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

final class JsonLengthFunction implements SqlFunctionInterface
{
    public function getName(): string
    {
        return 'JSON_LENGTH';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "json_array_length(%s, '$.%s')",  // ✅ SQLite
                $column,
                $path
            ),
            DatabaseDriver::MYSQL => sprintf(
                "JSON_LENGTH(%s, '$.%s')",        // ✅ MySQL
                $column,
                $path
            ),
            DatabaseDriver::PGSQL => sprintf(
                "jsonb_array_length(%s->'%s')",   // ✅ PostgreSQL
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

        return 0;
    }

    public function validateArgs(array $args): bool
    {
        return count($args) === 1;
    }

    public function getDefaultValue(): mixed
    {
        return 0;
    }
}
