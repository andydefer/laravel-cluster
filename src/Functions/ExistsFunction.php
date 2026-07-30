<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Verifies the existence and non-emptiness of a value at a given path.
 *
 * This function checks whether a path exists in the data structure and
 * contains a non-empty value. Empty values include null, empty arrays,
 * empty strings, 0, false, and other values considered empty by PHP's
 * empty() function.
 *
 * @example
 * $exists = new ExistsFunction();
 * $data = ['user' => ['name' => 'John']];
 * $exists->execute($data, ['user.name']); // true
 * @example
 * $data = ['user' => ['name' => '']];
 * $exists->execute($data, ['user.name']); // false
 * @example
 * $data = ['user' => null];
 * $exists->execute($data, ['user']); // false
 */
final class ExistsFunction extends AbstractAggregateFunction
{
    /**
     * Executes the existence check on the provided data.
     *
     * @param  array<string, mixed>  $data  The source data to extract values from
     * @param  array<int, string>  $args  Path arguments to locate the target value
     * @return bool True if the path exists and contains a non-empty value
     */
    public function execute(array $data, array $args): bool
    {
        $path = $args[0] ?? null;

        if ($path === null) {
            return false;
        }

        $value = $this->resolveArg($data, $path);

        return $value !== null && ! empty($value);
    }

    /**
     * Returns the function name as used in query expressions.
     *
     * @return string The uppercase function name
     */
    public function getName(): string
    {
        return 'EXISTS';
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
