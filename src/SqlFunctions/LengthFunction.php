<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Contracts\SqlFunctionInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

final class LengthFunction implements SqlFunctionInterface
{
    public function getName(): string
    {
        return 'LENGTH';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver): string
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

    public function execute(mixed $value): int
    {
        if (is_string($value)) {
            return strlen($value);
        }

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
