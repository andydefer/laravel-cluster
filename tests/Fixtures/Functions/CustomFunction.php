<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Fixtures\Functions;

use AndyDefer\LaravelCluster\Functions\AbstractAggregateFunction;

/**
 * Custom function for testing purposes.
 *
 * Returns the number of arguments passed to it.
 * Useful for testing argument parsing and validation.
 *
 * @example
 * $function = new CustomFunction();
 * $result = $function->execute([], ['a', 'b', 'c']); // Returns 3
 */
final class CustomFunction extends AbstractAggregateFunction
{
    public function execute(array $data, array $args): mixed
    {
        return count($args);
    }

    public function getName(): string
    {
        return 'CUSTOM';
    }

    public function getDefaultValue(): mixed
    {
        return 0;
    }

    public function getReturnType(): string
    {
        return 'int';
    }

    public function returnsBoolean(): bool
    {
        return false;
    }

    public function getMinArgs(): int
    {
        return 0;
    }

    public function getMaxArgs(): int
    {
        return 0; // 0 means unlimited
    }

    public function validateArgs(array $args): bool
    {
        return true;
    }
}
