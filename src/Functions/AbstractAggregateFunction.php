<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

use AndyDefer\LaravelCluster\Contracts\AggregateFunctionInterface;

/**
 * Abstract base class providing common functionality for aggregate functions.
 *
 * This class handles argument resolution, path extraction from nested arrays
 * using dot notation, and JSON decoding. It serves as the foundation for
 * all aggregate functions like COUNT, SUM, AVG, MIN, MAX, HAS, ALL, etc.
 *
 * @example
 * class MyFunction extends AbstractAggregateFunction
 * {
 *     public function execute(array $data, array $args): mixed
 *     {
 *         $value = $this->resolveArg($data, $args[0] ?? null);
 *         // Process the value...
 *         return $result;
 *     }
 * }
 */
abstract class AbstractAggregateFunction implements AggregateFunctionInterface
{
    /**
     * Resolves an argument value from the data array.
     *
     * Supports multiple formats:
     * - Simple values (string, int, float, bool)
     * - Variables: array with 'type' => 'variable' or string starting with '$'
     * - Path resolution using dot notation (e.g., 'user.profile.name')
     * - JSON strings automatically decoded to arrays
     *
     * @param  array<string, mixed>  $data  The data array to extract from
     * @param  mixed  $arg  The argument to resolve
     * @return mixed The resolved value
     */
    protected function resolveArg(array $data, mixed $arg): mixed
    {
        // Array format: ['type' => 'variable', 'value' => 'path']
        if (is_array($arg) && isset($arg['type']) && $arg['type'] === 'variable') {
            return $this->extractAndDecodeValue($data, $arg['value']);
        }

        // String format starting with '$' (e.g., '$weights')
        if (is_string($arg) && str_starts_with($arg, '$')) {
            return $this->extractAndDecodeValue($data, substr($arg, 1));
        }

        // Plain string path (e.g., 'prices')
        if (is_string($arg)) {
            return $this->extractAndDecodeValue($data, $arg);
        }

        return $arg;
    }

    /**
     * Alias for resolveArg() to provide semantic clarity for path resolution.
     *
     * This method is identical to resolveArg() but is named to better express
     * the intent when the argument is specifically a path string.
     *
     * @param  array<string, mixed>  $data  The data array to extract from
     * @param  string  $path  The dot notation path
     * @return mixed The resolved value
     */
    protected function resolvePath(array $data, string $path): mixed
    {
        return $this->resolveArg($data, $path);
    }

    /**
     * Extracts a value from the data array and decodes JSON if applicable.
     *
     * @param  array<string, mixed>  $data  The data array
     * @param  string  $path  The dot notation path
     * @return mixed The extracted and possibly decoded value
     */
    private function extractAndDecodeValue(array $data, string $path): mixed
    {
        $value = $this->extractValue($data, $path);

        if (! is_string($value)) {
            return $value;
        }

        // Try to decode JSON
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Second attempt: clean escaped quotes
        $cleaned = stripslashes($value);
        $decoded = json_decode($cleaned, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return $value;
    }

    /**
     * Extracts a value from the data array using dot notation path.
     *
     * @param  array<string, mixed>  $data  The data array
     * @param  string  $path  The dot notation path (e.g., 'user.profile.name')
     * @return mixed The extracted value, or null if the path doesn't exist
     */
    protected function extractValue(array $data, string $path): mixed
    {
        $parts = explode('.', $path);
        $current = $data;

        foreach ($parts as $part) {
            if (! isset($current[$part])) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }

    /**
     * Extracts all numeric values from a nested array structure.
     *
     * @param  array<mixed>  $array  The array to extract numbers from
     * @return array<float> Array of numeric values
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
     * Determines if a string is valid JSON.
     *
     * @param  string  $string  The string to check
     * @return bool True if the string is valid JSON
     */
    protected function isJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Gets the default value for this function.
     */
    abstract public function getDefaultValue(): mixed;

    /**
     * Gets the return type for this function.
     */
    abstract public function getReturnType(): string;

    /**
     * Determines whether this function returns a boolean value.
     */
    abstract public function returnsBoolean(): bool;

    /**
     * Gets the minimum number of arguments required.
     */
    abstract public function getMinArgs(): int;

    /**
     * Gets the maximum number of arguments allowed.
     * Return 0 for unlimited.
     */
    abstract public function getMaxArgs(): int;

    /**
     * Validates the arguments passed to the function.
     */
    abstract public function validateArgs(array $args): bool;
}
