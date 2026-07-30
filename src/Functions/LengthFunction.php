<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Calculates the length of a string or the number of elements in an array.
 */
final class LengthFunction extends AbstractAggregateFunction
{
    public function execute(array $data, array $args): int
    {
        $path = $args[0] ?? null;
        $value = $this->resolveArg($data, $path);

        if (is_string($value)) {
            return strlen($value);
        }

        if (is_array($value)) {
            return count($value);
        }

        return 0;
    }

    public function getName(): string
    {
        return 'LENGTH';
    }

    public function getDefaultValue(): int
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
