<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Determines whether a value extracted from data is considered empty.
 *
 * This function checks emptiness according to the following rules:
 * - Arrays: empty if they contain no elements
 * - Strings: empty if they are an empty string ('')
 * - Other types: empty only if null
 *
 * Note: This differs from PHP's empty() function which also considers 0, false, etc.
 *
 * @example
 * $isEmpty = new IsEmptyFunction();
 * $data = ['tags' => []];
 * $isEmpty->execute($data, ['tags']); // true
 * @example
 * $data = ['name' => ''];
 * $isEmpty->execute($data, ['name']); // true
 * @example
 * $data = ['score' => 0];
 * $isEmpty->execute($data, ['score']); // false (0 is not empty)
 */
final class IsEmptyFunction extends AbstractAggregateFunction
{
    /**
     * Executes the emptiness check on the provided data.
     *
     * @param  array<string, mixed>  $data  The source data to extract values from
     * @param  array<int, string>  $args  Path arguments to locate the target value
     * @return bool True if the value is empty according to the rules
     */
    public function execute(array $data, array $args): bool
    {
        $path = $args[0] ?? null;
        $value = $this->resolveArg($data, $path);

        if (is_array($value)) {
            return empty($value);
        }

        if (is_string($value)) {
            return $value === '';
        }

        return $value === null;
    }

    /**
     * Returns the function name as used in query expressions.
     *
     * @return string The uppercase function name
     */
    public function getName(): string
    {
        return 'IS_EMPTY';
    }

    /**
     * Returns the default value when the function cannot be executed.
     *
     * @return bool Default fallback value
     */
    public function getDefaultValue(): mixed
    {
        return true;
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
        return 1;
    }

    /**
     * Returns the maximum number of arguments allowed.
     *
     * @return int Maximum arguments count
     */
    public function getMaxArgs(): int
    {
        return 1;
    }

    /**
     * Validates the provided arguments for this function.
     *
     * @param  array<int, mixed>  $args  The arguments to validate
     * @return bool True if arguments are valid
     */
    public function validateArgs(array $args): bool
    {
        return count($args) === 1;
    }
}
