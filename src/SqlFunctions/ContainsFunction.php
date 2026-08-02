<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Contracts\SqlFunctionInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

final class ContainsFunction implements SqlFunctionInterface
{
    public function getName(): string
    {
        return 'CONTAINS';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string
    {
        // $args[0] = path (ex: 'languages')
        // $args[1] = search value (ex: 'fr')
        $searchValue = $args[1] ?? '';
        $searchValue = addslashes($searchValue);

        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "EXISTS (SELECT 1 FROM json_each(%s, '$.%s') WHERE value = '%s')",
                $column,
                $path,
                $searchValue
            ),
            DatabaseDriver::MYSQL => sprintf(
                "JSON_SEARCH(%s, 'one', '%s', NULL, '$.\"%s\"') IS NOT NULL",
                $column,
                $searchValue,
                $path
            ),
            DatabaseDriver::PGSQL => sprintf(
                "EXISTS (SELECT 1 FROM json_array_elements_text(%s->'%s') AS elem WHERE elem = '%s')",
                $column,
                $path,
                $searchValue
            ),
        };
    }

    public function execute(mixed $value, array $args = []): mixed
    {
        // $args[0] = path (ex: 'languages')
        // $args[1] = search value (ex: 'fr')
        if (! is_array($value) || count($args) < 2) {
            return false;
        }

        $searchValue = $args[1] ?? null;

        if ($searchValue === null) {
            return false;
        }

        return in_array($searchValue, $value, true);
    }

    public function getDefaultValue(): mixed
    {
        return false;
    }

    public function getReturnType(): string
    {
        return 'bool';
    }

    public function validateArgs(array $args): bool
    {
        // CONTAINS requires exactly 2 arguments: path and value
        return count($args) === 2
            && is_string($args[0]) && ! empty($args[0])
            && is_string($args[1]) && ! empty($args[1]);
    }

    public function getMinArgs(): int
    {
        return 2;
    }

    public function getMaxArgs(): int
    {
        return 2;
    }
}
