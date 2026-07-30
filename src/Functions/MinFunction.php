<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Finds the minimum numeric value within an array extracted from data.
 *
 * This function extracts an array from the data, filters out non-numeric values,
 * and returns the minimum value found. If no numeric values are found,
 * returns 0.
 *
 * @example
 * $min = new MinFunction();
 * $data = ['scores' => [80, 90, 70]];
 * $min->execute($data, ['scores']); // 70
 * @example
 * $data = ['prices' => [50, 75, 30]];
 * $min->execute($data, ['prices']); // 30
 * @example
 * $data = ['empty' => []];
 * $min->execute($data, ['empty']); // 0
 */
final class MinFunction extends AbstractAggregateFunction
{
    /**
     * Executes the minimum value calculation on the provided data.
     *
     * @param  array<string, mixed>  $data  The source data to extract values from
     * @param  array<int, string>  $args  Path arguments to locate the target array
     * @return float|int The minimum numeric value found, or 0 if none found
     */
    public function execute(array $data, array $args): mixed
    {
        $path = $args[0] ?? null;
        $value = $this->resolveArg($data, $path);

        if (! is_array($value) || empty($value)) {
            return 0;
        }

        $numbers = $this->extractNumbers($value);

        return ! empty($numbers) ? min($numbers) : 0;
    }

    /**
     * Returns the function name as used in query expressions.
     *
     * @return string The uppercase function name
     */
    public function getName(): string
    {
        return 'MIN';
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
     * Returns the PHP type returned by the execution.
     *
     * @return string The return type name
     */
    public function getReturnType(): string
    {
        return 'float';
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
