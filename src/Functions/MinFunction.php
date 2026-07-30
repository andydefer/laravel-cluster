<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Finds the minimum value in an array.
 */
final class MinFunction extends AbstractAggregateFunction
{
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

    public function getName(): string
    {
        return 'MIN';
    }

    public function getDefaultValue(): mixed
    {
        return 0;
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
