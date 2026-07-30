<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Fixtures\Functions;

use AndyDefer\LaravelCluster\Functions\AbstractAggregateFunction;

/**
 * Custom function for testing: returns twice the count of elements.
 *
 * @example
 * $function = new DoubleCountFunction();
 * $result = $function->execute(['items' => [1, 2, 3]], ['items']);
 * // Returns 6 (count 3 * 2)
 */
final class DoubleCountFunction extends AbstractAggregateFunction
{
    public function execute(array $data, array $args): mixed
    {
        $path = $args[0] ?? null;
        $value = $this->resolveArg($data, $path);

        return is_array($value) ? count($value) * 2 : 0;
    }

    public function getName(): string
    {
        return 'DOUBLE_COUNT';
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
