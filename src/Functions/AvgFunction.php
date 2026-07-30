<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Calculates the average of numeric values in an array.
 */
final class AvgFunction extends AbstractAggregateFunction
{
    public function execute(array $data, array $args): float
    {
        $path = $args[0] ?? null;
        $value = $this->resolveArg($data, $path);

        if (! is_array($value) || empty($value)) {
            return 0.0;
        }

        $numbers = $this->extractNumbers($value);
        $count = count($numbers);

        return $count > 0 ? array_sum($numbers) / $count : 0.0;
    }

    public function getName(): string
    {
        return 'AVG';
    }

    public function getDefaultValue(): float
    {
        return 0.0;
    }

    public function getReturnType(): string
    {
        return 'float';
    }

    public function returnsBoolean(): bool
    {
        return false;
    }

    public function getMinArgs(): int
    {
        return 1;
    }

    public function getMaxArgs(): int
    {
        return 1;
    }

    public function validateArgs(array $args): bool
    {
        return count($args) === 1;
    }
}
