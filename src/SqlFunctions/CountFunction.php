<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Contracts\SqlFunctionInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

final class CountFunction implements SqlFunctionInterface
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

    public function validateArgs(array $args): bool
    {
        return count($args) === 1;
    }

    public function getDefaultValue(): mixed
    {
        return 0;
    }
}
