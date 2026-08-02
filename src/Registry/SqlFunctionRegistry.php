<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Registry;

use AndyDefer\LaravelCluster\Contracts\SqlFunctionInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\SqlFunctions\AvgFunction;
use AndyDefer\LaravelCluster\SqlFunctions\ContainsFunction;
use AndyDefer\LaravelCluster\SqlFunctions\CountFunction;
use AndyDefer\LaravelCluster\SqlFunctions\JsonLengthFunction;
use AndyDefer\LaravelCluster\SqlFunctions\LengthFunction;
use AndyDefer\LaravelCluster\SqlFunctions\MaxFunction;
use AndyDefer\LaravelCluster\SqlFunctions\MinFunction;
use AndyDefer\LaravelCluster\SqlFunctions\RegexpFunction;
use AndyDefer\LaravelCluster\SqlFunctions\SumFunction;

/**
 * Registry for SQL functions used in database queries.
 *
 * This registry manages SQL functions (COUNT, SUM, AVG, MIN, MAX, LENGTH, JSON_LENGTH, REGEXP, CONTAINS)
 * that can be used in SQL queries across different database drivers.
 * Each function provides driver-specific SQL generation.
 *
 * @example
 * $registry = new SqlFunctionRegistry();
 * $registry->has('COUNT'); // true
 * $sql = $registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::MYSQL);
 * // 'JSON_LENGTH(clusters, '$.addresses')'
 * @example
 * $result = $registry->execute('COUNT', ['a', 'b', 'c']); // 3
 * @example
 * $result = $registry->execute('CONTAINS', ['fr', 'en'], ['fr']); // true
 */
final class SqlFunctionRegistry
{
    private array $functions = [];

    public function __construct()
    {
        $this->registerDefaultFunctions();
    }

    /**
     * Registers an SQL function in the registry.
     *
     * @param  SqlFunctionInterface  $function  The function to register
     * @return self Returns the registry instance for method chaining
     */
    public function register(SqlFunctionInterface $function): self
    {
        $this->functions[strtoupper($function->getName())] = $function;

        return $this;
    }

    /**
     * Checks if a function is registered by name.
     *
     * @param  string  $name  The function name (case-insensitive)
     * @return bool True if the function is registered
     */
    public function has(string $name): bool
    {
        return isset($this->functions[strtoupper($name)]);
    }

    /**
     * Retrieves a registered function by name.
     *
     * @param  string  $name  The function name (case-insensitive)
     * @return SqlFunctionInterface|null The function instance, or null if not found
     */
    public function get(string $name): ?SqlFunctionInterface
    {
        return $this->functions[strtoupper($name)] ?? null;
    }

    /**
     * Generates the SQL expression for a registered function.
     *
     * @param  string  $name  The function name
     * @param  string  $column  The database column containing JSON data
     * @param  string  $path  The JSON path within the column
     * @param  DatabaseDriver  $driver  The database driver to use
     * @param  array  $args  Additional arguments for the function
     * @return string|null The SQL expression, or null if the function is not registered
     */
    public function toSql(string $name, string $column, string $path, DatabaseDriver $driver, array $args = []): ?string
    {
        $function = $this->get($name);

        if ($function === null) {
            return null;
        }

        return $function->toSql($column, $path, $driver, $args);
    }

    /**
     * Executes a registered function on a value (for in-memory evaluation).
     *
     * @param  string  $name  The function name
     * @param  mixed  $value  The value to process
     * @param  array  $args  Additional arguments for the function
     * @return mixed The result of the function, or the original value if not registered
     */
    public function execute(string $name, mixed $value, array $args = []): mixed
    {
        $function = $this->get($name);

        if ($function === null) {
            return $value;
        }

        return $function->execute($value, $args);
    }

    /**
     * Returns the default value for a function.
     *
     * @param  string  $name  The function name
     * @return mixed The default value, or null if the function is not found
     */
    public function getDefaultValue(string $name): mixed
    {
        $function = $this->get($name);

        if ($function === null) {
            return null;
        }

        return $function->getDefaultValue();
    }

    /**
     * Validates arguments for a registered function.
     *
     * @param  string  $name  The function name
     * @param  array<int, mixed>  $args  The arguments to validate
     * @return bool True if the arguments are valid, false otherwise
     */
    public function validateArgs(string $name, array $args): bool
    {
        $function = $this->get($name);

        if ($function === null) {
            return false;
        }

        return $function->validateArgs($args);
    }

    /**
     * Returns the minimum number of arguments required for a function.
     *
     * @param  string  $name  The function name
     * @return int|null The minimum number of arguments, or null if not found
     */
    public function getMinArgs(string $name): ?int
    {
        $function = $this->get($name);

        if ($function === null) {
            return null;
        }

        return $function->getMinArgs();
    }

    /**
     * Returns the maximum number of arguments allowed for a function.
     *
     * @param  string  $name  The function name
     * @return int|null The maximum number of arguments, or null if not found
     */
    public function getMaxArgs(string $name): ?int
    {
        $function = $this->get($name);

        if ($function === null) {
            return null;
        }

        return $function->getMaxArgs();
    }

    /**
     * Returns all registered functions.
     *
     * @return array<string, SqlFunctionInterface> Array of function instances indexed by name
     */
    public function all(): array
    {
        return $this->functions;
    }

    /**
     * Returns the return type for a registered function.
     *
     * @param  string  $name  The function name
     * @return string|null The return type ('int', 'float', 'string', 'bool'), or null if not found
     */
    public function getReturnType(string $name): ?string
    {
        $function = $this->get($name);

        if ($function === null) {
            return null;
        }

        return $function->getReturnType();
    }

    /**
     * Returns the names of all registered functions.
     *
     * @return array<string> Array of function names
     */
    public function getNames(): array
    {
        return array_keys($this->functions);
    }

    /**
     * Registers the default set of SQL functions.
     */
    private function registerDefaultFunctions(): void
    {
        $this->register(new CountFunction);
        $this->register(new SumFunction);
        $this->register(new AvgFunction);
        $this->register(new MinFunction);
        $this->register(new MaxFunction);
        $this->register(new LengthFunction);
        $this->register(new JsonLengthFunction);
        $this->register(new RegexpFunction);
        $this->register(new ContainsFunction);
    }
}
