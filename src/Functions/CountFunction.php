<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Counts elements in arrays or characters in strings extracted from a data structure.
 *
 * This function extracts values from the provided data using a path expression
 * and returns the count of elements if the target is an array, or the string
 * length if the target is a string. Returns 0 for other types.
 *
 * @example
 * $count = new CountFunction();
 * $data = ['tags' => ['php', 'js', 'css']];
 * $count->execute($data, ['tags']); // 3
 * @example
 * $data = ['name' => 'John Doe'];
 * $count->execute($data, ['name']); // 8
 * @example
 * $data = ['empty' => []];
 * $count->execute($data, ['empty']); // 0
 */
final class CountFunction extends AbstractAggregateFunction
{
    /**
     * Executes the count operation on the provided data.
     *
     * @param  array<string, mixed>  $data  The source data to extract values from
     * @param  array<int, string>  $args  Path arguments to locate the target value
     * @return int The count of elements or characters, or 0 for unsupported types
     */
    public function execute(array $data, array $args): int
    {
        $path = $args[0] ?? null;
        $value = $this->resolveArg($data, $path);

        if (is_array($value)) {
            return count($value);
        }

        if (is_string($value)) {
            return strlen($value);
        }

        return 0;
    }

    /**
     * Returns the function name as used in query expressions.
     *
     * @return string The uppercase function name
     */
    public function getName(): string
    {
        return 'COUNT';
    }

    /**
     * Returns the default value when the function cannot be executed.
     *
     * @return int Default fallback value
     */
    public function getDefaultValue(): int
    {
        return 0;
    }

    /**
     * Returns the PHP type returned by the execution.
     *
     * @return string The return type name
     */
    public function getReturnType(): string
    {
        return 'int';
    }

    /**
     * Indicates whether this function returns a boolean result.
     *
     * @return false This function returns a numeric value
     */
    public function returnsBoolean(): bool
    {
        return false;
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
