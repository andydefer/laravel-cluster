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
 * // "JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.name')) REGEXP '^John.*'"
 * @example
 * // In a query
 * $users = User::whereCluster('clusters', 'REGEXP(name, "^John.*")')->get();
 */
final class RegexpFunction extends AbstractSqlFunction
{
    /**
     * Returns the name of the function.
     *
     * This name is used in cluster query expressions (e.g., `REGEXP(name, "^John.*")`).
     *
     * @return string The function name
     */
    public function getName(): string
    {
        return 'REGEXP';
    }

    /**
     * Generates the SQL expression for the Regexp function.
     *
     * Returns database-specific SQL that checks if a string matches a regex pattern.
     * For SQLite: uses `json_extract(...) REGEXP 'pattern'`
     * For MySQL: uses `JSON_UNQUOTE(JSON_EXTRACT(...)) REGEXP 'pattern'` to handle JSON strings
     * For PostgreSQL: uses `column->>'path' ~ 'pattern'`
     *
     * @param  string  $column  The database column containing JSON data
     * @param  string  $path  The JSON path to the value
     * @param  DatabaseDriver  $driver  The database driver
     * @param  array  $args  Arguments: [0] = path (ex: 'name'), [1] = pattern (ex: '^John.*')
     * @return string The SQL expression
     */
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
                "JSON_UNQUOTE(JSON_EXTRACT(%s, '$.%s'))",
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

    /**
     * Returns the return type of the function.
     *
     * The Regexp function returns a boolean indicating whether the pattern matches.
     *
     * @return string The return type ('bool')
     */
    public function getReturnType(): string
    {
        return 'bool';
    }

    /**
     * Executes the Regexp function on a value.
     *
     * Uses preg_match to check if the pattern matches the string value.
     *
     * @param  mixed  $value  The value to evaluate (string)
     * @param  array  $args  Arguments: [0] = path, [1] = pattern
     * @return bool True if the pattern matches, false otherwise
     */
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
     * @return bool True if exactly two arguments are provided and non-empty
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
     * @return bool Default fallback value (false)
     */
    public function getDefaultValue(): mixed
    {
        return false;
    }

    /**
     * Get the minimum number of arguments required for this function.
     *
     * @return int Minimum arguments (2)
     */
    public function getMinArgs(): int
    {
        return 2;
    }

    /**
     * Get the maximum number of arguments allowed for this function.
     *
     * @return int Maximum arguments (2)
     */
    public function getMaxArgs(): int
    {
        return 2;
    }
}
