<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Searches for a value within an array or array of items extracted from data.
 *
 * This function supports two usage patterns:
 * - With 2 arguments: Searches for a scalar value within an array of values
 * - With 3 arguments: Searches for a key-value pair within an array of objects/arrays
 *
 * @example
 * // Search for a value in an array
 * $has = new HasFunction();
 * $data = ['tags' => ['php', 'js', 'css']];
 * $has->execute($data, ['tags', 'php']); // true
 * @example
 * // Search for a key-value pair in an array of objects
 * $data = ['addresses' => [['city' => 'Kinshasa'], ['city' => 'Paris']]];
 * $has->execute($data, ['addresses', 'city', 'Kinshasa']); // true
 * @example
 * // Value not found
 * $data = ['tags' => ['php', 'js']];
 * $has->execute($data, ['tags', 'python']); // false
 */
final class HasFunction extends AbstractAggregateFunction
{
    /**
     * Executes the search operation on the provided data.
     *
     * @param  array<string, mixed>  $data  The source data to extract values from
     * @param  array<int, string>  $args  Arguments: [path, key, value] (value is optional)
     * @return bool True if the value or key-value pair is found
     */
    public function execute(array $data, array $args): bool
    {
        $path = $args[0] ?? null;
        $key = $args[1] ?? null;
        $value = $args[2] ?? null;

        $items = $this->extractValue($data, $path);

        if (! is_array($items)) {
            return false;
        }

        if ($value === null) {
            foreach ($items as $item) {
                if ($item == $key) {
                    return true;
                }
            }

            return false;
        }

        foreach ($items as $item) {
            if (is_array($item) && isset($item[$key]) && $item[$key] == $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the function name as used in query expressions.
     *
     * @return string The uppercase function name
     */
    public function getName(): string
    {
        return 'HAS';
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
     * Returns the PHP type returned by the execution.
     *
     * @return string The return type name
     */
    public function getReturnType(): string
    {
        return 'bool';
    }

    /**
     * Indicates whether this function returns a boolean result.
     *
     * @return true This function always returns a boolean
     */
    public function returnsBoolean(): bool
    {
        return true;
    }

    /**
     * Returns the minimum number of arguments required.
     *
     * @return int Minimum arguments count
     */
    public function getMinArgs(): int
    {
        return 2;
    }

    /**
     * Returns the maximum number of arguments allowed.
     *
     * @return int Maximum arguments count
     */
    public function getMaxArgs(): int
    {
        return 3;
    }

    /**
     * Validates the provided arguments for this function.
     *
     * @param  array<int, mixed>  $args  The arguments to validate
     * @return bool True if arguments are valid
     */
    public function validateArgs(array $args): bool
    {
        $count = count($args);

        return $count >= 2 && $count <= 3;
    }
}
