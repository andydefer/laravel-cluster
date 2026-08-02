<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Contracts\SqlFunctionInterface;

/**
 * Abstract base class for SQL functions.
 *
 * Provides common implementations for:
 * - Argument validation (single argument)
 * - Default value (0)
 * - Number extraction from nested arrays
 * - Minimum arguments (1)
 * - Maximum arguments (no limit)
 *
 * @example
 * final class CustomFunction extends AbstractSqlFunction
 * {
 *     public function getName(): string { return 'CUSTOM'; }
 *     public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string { /* ... * / }
 *     public function execute(mixed $value, array $args = []): mixed { /* ... * / }
 *     public function getReturnType(): string { return 'int'; }
 * }
 */
abstract class AbstractSqlFunction implements SqlFunctionInterface
{
    /**
     * Extracts all numeric values from a nested array structure.
     *
     * @param  array<mixed>  $array  The array to traverse
     * @return array<float> All numeric values found
     */
    protected function extractNumbers(array $array): array
    {
        $numbers = [];

        foreach ($array as $item) {
            if (is_array($item)) {
                $numbers = array_merge($numbers, $this->extractNumbers($item));
            } elseif (is_numeric($item)) {
                $numbers[] = (float) $item;
            }
        }

        return $numbers;
    }

    /**
     * Validates that exactly one argument is provided.
     *
     * @param  array<mixed>  $args  The arguments to validate
     * @return bool True if exactly one argument is provided
     */
    public function validateArgs(array $args): bool
    {
        return count($args) === 1;
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

    /**
     * Get the minimum number of arguments required for this function.
     * By default, all functions require at least 1 argument.
     *
     * @return int The minimum number of arguments
     */
    public function getMinArgs(): int
    {
        return 1;
    }

    /**
     * Get the maximum number of arguments allowed for this function.
     * By default, there is no limit (PHP_INT_MAX).
     *
     * @return int The maximum number of arguments
     */
    public function getMaxArgs(): int
    {
        return PHP_INT_MAX;
    }
}
