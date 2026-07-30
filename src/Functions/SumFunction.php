<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Calculates the sum of all numeric values within an array extracted from data.
 *
 * This function extracts an array from the data, filters out non-numeric values,
 * and returns the sum of all remaining numeric values. If no numeric values are
 * found, returns 0.0.
 *
 * @example
 * $sum = new SumFunction();
 * $data = ['prices' => [100, 200, 300]];
 * $sum->execute($data, ['prices']); // 600.0
 * @example
 * $data = ['scores' => [80, 90, 85]];
 * $sum->execute($data, ['scores']); // 255.0
 * @example
 * $data = ['empty' => []];
 * $sum->execute($data, ['empty']); // 0.0
 */
final class SumFunction extends AbstractAggregateFunction
{
    /**
     * Executes the sum calculation on the provided data.
     *
     * @param  array<string, mixed>  $data  The source data to extract values from
     * @param  array<int, string>  $args  Path arguments to locate the target array
     * @return float The sum of all numeric values found, or 0.0 if none found
     */
    public function execute(array $data, array $args): float
    {
        $path = $args[0] ?? null;
        $value = $this->resolveArg($data, $path);

        if (! is_array($value) || empty($value)) {
            return 0.0;
        }

        return array_sum($this->extractNumbers($value));
    }

    /**
     * Returns the function name as used in query expressions.
     *
     * @return string The uppercase function name
     */
    public function getName(): string
    {
        return 'SUM';
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
