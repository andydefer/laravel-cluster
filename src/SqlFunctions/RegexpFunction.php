<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

/**
 * Checks if a string matches a regular expression pattern.
 *
 * This function provides regex matching capabilities across different database drivers:
 * - SQLite: uses REGEXP operator (requires REGEXP extension)
 * - MySQL: uses REGEXP operator
 * - PostgreSQL: uses ~ (tilde) operator
 *
 * @example
 * $regexp = new RegexpFunction();
 * $sql = $regexp->toSql('clusters', 'name', DatabaseDriver::MYSQL);
 * // "clusters->>'name' REGEXP '^John.*'"
 * @example
 * // In a query
 * $users = User::whereCluster('clusters', 'REGEXP(name, "^John.*")')->get();
 */
final class RegexpFunction extends AbstractSqlFunction
{
    public function getName(): string
    {
        return 'REGEXP';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "json_extract(%s, '$.%s')",
                $column,
                $path
            ),
            DatabaseDriver::MYSQL => sprintf(
                "JSON_EXTRACT(%s, '$.%s')",
                $column,
                $path
            ),
            DatabaseDriver::PGSQL => sprintf(
                "%s->>'%s'",
                $column,
                $path
            ),
        };
    }

    public function getReturnType(): string
    {
        return 'int';
    }

    public function execute(mixed $value): mixed
    {
        if (! is_string($value)) {
            return 0;
        }

        return $value;
    }

    /**
     * Validates that exactly two arguments are provided (path and pattern).
     *
     * @param  array<mixed>  $args  The arguments to validate
     * @return bool True if exactly two arguments are provided
     */
    public function validateArgs(array $args): bool
    {
        return count($args) === 2;
    }

    /**
     * Returns the default value when the function cannot be executed.
     *
     * @return int Default fallback value
     */
    public function getDefaultValue(): mixed
    {
        return 0;
    }
}
