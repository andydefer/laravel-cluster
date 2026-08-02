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
 * $sql = $regexp->toSql('clusters', 'name', DatabaseDriver::MYSQL, ['name', '^John.*']);
 * // "JSON_EXTRACT(clusters, '$.name') REGEXP '^John.*'"
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

    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string
    {
        // $args[0] = path (ex: 'name')
        // $args[1] = pattern (ex: '^John.*')
        $pattern = $args[1] ?? '';
        $pattern = addslashes($pattern);

        $valueExtract = match ($driver) {
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

        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "%s REGEXP '%s'",
                $valueExtract,
                $pattern
            ),
            DatabaseDriver::MYSQL => sprintf(
                "%s REGEXP '%s'",
                $valueExtract,
                $pattern
            ),
            DatabaseDriver::PGSQL => sprintf(
                "%s ~ '%s'",
                $valueExtract,
                $pattern
            ),
        };
    }

    public function getReturnType(): string
    {
        return 'bool';
    }

    public function execute(mixed $value, array $args = []): mixed
    {
        if (! is_string($value) || count($args) < 2) {
            return false;
        }

        $pattern = $args[1] ?? '';
        if (empty($pattern)) {
            return false;
        }

        // Utiliser preg_match pour l'évaluation en mémoire
        $pattern = '/'.str_replace('/', '\/', $pattern).'/';

        return preg_match($pattern, $value) === 1;
    }

    /**
     * Validates that exactly two arguments are provided (path and pattern).
     *
     * @param  array<mixed>  $args  The arguments to validate
     * @return bool True if exactly two arguments are provided
     */
    public function validateArgs(array $args): bool
    {
        return count($args) === 2
            && is_string($args[0]) && ! empty($args[0])
            && is_string($args[1]) && ! empty($args[1]);
    }

    /**
     * Returns the default value when the function cannot be executed.
     *
     * @return bool Default fallback value
     */
    public function getDefaultValue(): mixed
    {
        return false;
    }

    /**
     * Get the minimum number of arguments required for this function.
     */
    public function getMinArgs(): int
    {
        return 2;
    }

    /**
     * Get the maximum number of arguments allowed for this function.
     */
    public function getMaxArgs(): int
    {
        return 2;
    }
}
