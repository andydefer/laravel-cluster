<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Contracts;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

/**
 * Interface for SQL functions that can be used in cluster queries.
 * Supports both in-memory (execute) and database (toSql) evaluation.
 *
 * @example
 * class CountFunction implements SqlFunctionInterface
 * {
 *     public function getName(): string { return 'COUNT'; }
 *     public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string { ... }
 *     public function execute(mixed $value, array $args = []): mixed { ... }
 *     public function getReturnType(): string { return 'int'; }
 *     public function validateArgs(array $args): bool { return count($args) === 1; }
 *     public function getDefaultValue(): mixed { return 0; }
 *     public function getMinArgs(): int { return 1; }
 *     public function getMaxArgs(): int { return PHP_INT_MAX; }
 * }
 */
interface SqlFunctionInterface
{
    /**
     * Get the name of the function (e.g., 'COUNT', 'SUM', 'AVG').
     */
    public function getName(): string;

    /**
     * Generate the SQL expression for this function.
     *
     * @param  string  $column  The JSON column name
     * @param  string  $path  The path within the JSON
     * @param  DatabaseDriver  $driver  The database driver
     * @param  array  $args  Additional arguments for the function
     * @return string The SQL expression
     */
    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string;

    /**
     * Get the return type of this function ('int', 'float', 'string', 'bool').
     */
    public function getReturnType(): string;

    /**
     * Execute the function on a value (for in-memory evaluation).
     *
     * @param  mixed  $value  The value to process
     * @param  array  $args  Additional arguments for the function
     * @return mixed The result of the function
     */
    public function execute(mixed $value, array $args = []): mixed;

    /**
     * Validate the arguments for this function.
     *
     * @param  array  $args  The arguments passed to the function
     * @return bool True if the arguments are valid
     */
    public function validateArgs(array $args): bool;

    /**
     * Get the default value when the function cannot be executed.
     */
    public function getDefaultValue(): mixed;

    /**
     * Get the minimum number of arguments required for this function.
     *
     * @return int The minimum number of arguments
     */
    public function getMinArgs(): int;

    /**
     * Get the maximum number of arguments allowed for this function.
     * Return PHP_INT_MAX for no limit.
     *
     * @return int The maximum number of arguments
     */
    public function getMaxArgs(): int;
}
