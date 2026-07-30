<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Fixtures\Functions;

use AndyDefer\LaravelCluster\Functions\AbstractAggregateFunction;

/**
 * Custom function for testing: computes weighted average.
 *
 * @example
 * $function = new WeightedAvgFunction();
 * $result = $function->execute(
 *     ['scores' => [80, 90, 85], 'weights' => [1, 2, 1]],
 *     ['scores', 'weights']
 * );
 * // Returns 86.25
 */
final class WeightedAvgFunction extends AbstractAggregateFunction
{
    public function execute(array $data, array $args): mixed
    {
        $values = $this->resolveArg($data, $args[0] ?? []);
        $weights = $this->resolveArg($data, $args[1] ?? []);

        if (! is_array($values) || ! is_array($weights) || count($values) !== count($weights)) {
            return 0.0;
        }

        $sumWeighted = 0;
        $sumWeights = 0;

        foreach ($values as $i => $value) {
            $weight = $weights[$i] ?? 1;
            $sumWeighted += $value * $weight;
            $sumWeights += $weight;
        }

        return $sumWeights > 0 ? $sumWeighted / $sumWeights : 0.0;
    }

    public function getName(): string
    {
        return 'WEIGHTED_AVG';
    }

    public function getDefaultValue(): mixed
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
        return 2;
    }

    public function getMaxArgs(): int
    {
        return 2;
    }

    public function validateArgs(array $args): bool
    {
        return count($args) === 2;
    }
}
