<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Computes the arithmetic mean of numeric values extracted from a data structure.
 *
 * This function extracts values from the provided data using a path expression,
 * filters out non-numeric values, and calculates their average.
 *
 * @example
 * $avg = new AvgFunction();
 * $data = ['scores' => [80, 90, 100]];
 * $avg->execute($data, ['scores']); // 90.0
 * @example
 * $data = ['empty' => []];
 * $avg->execute($data, ['empty']); // 0.0
 */
final class AvgFunction extends AbstractAggregateFunction
{
    /**
     * Executes the average calculation on the provided data.
     *
     * @param  array<string, mixed>  $data  The source data to extract values from
     * @param  array<int, string>  $args  Path arguments to locate the target values
     * @return float The average of all numeric values found, or 0.0 if none found
     */
    public function execute(array $data, array $args): float
    {
        $path = $args[0] ?? null;
        $value = $this->resolveArg($data, $path);

        if (! is_array($value) || $value === []) {
            return 0.0;
        }

        $numbers = $this->extractNumbers($value);
        $count = count($numbers);

        return $count > 0 ? array_sum($numbers) / $count : 0.0;
    }

    /**
     * Returns the function name as used in query expressions.
     *
     * @return string The uppercase function name
     */
    public function getName(): string
    {
        return 'AVG';
    }

    /**
     * Returns the default value when the function cannot be executed.
     *
     * @return float Default fallback value
     */
    public function getDefaultValue(): float
    {
        return 0.0;
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
