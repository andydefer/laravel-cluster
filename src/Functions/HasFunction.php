<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Checks if any element in an array has a specific key-value pair.
 */
final class HasFunction extends AbstractAggregateFunction
{
    public function execute(array $data, array $args): bool
    {
        $path = $args[0] ?? null;
        $key = $args[1] ?? null;
        $value = $args[2] ?? null;

        $items = $this->resolveArg($data, $path);

        if (! is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (is_array($item) && isset($item[$key]) && $item[$key] == $value) {
                return true;
            }
        }

        return false;
    }

    public function getName(): string
    {
        return 'HAS';
    }

    public function getDefaultValue(): mixed
    {
        return false;
    }

    public function getReturnType(): string
    {
        return 'bool';
    }

    public function returnsBoolean(): bool
    {
        return true;
    }

    public function getMinArgs(): int
    {
        return 3;
    }

    public function getMaxArgs(): int
    {
        return 3;
    }

    public function validateArgs(array $args): bool
    {
        return count($args) === 3;
    }
}
