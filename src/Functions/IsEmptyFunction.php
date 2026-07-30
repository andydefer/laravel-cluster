<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Checks if a value is empty (null, empty array, or empty string).
 */
final class IsEmptyFunction extends AbstractAggregateFunction
{
    public function execute(array $data, array $args): bool
    {
        $path = $args[0] ?? null;
        $value = $this->resolveArg($data, $path);

        if (is_array($value)) {
            return empty($value);
        }

        if (is_string($value)) {
            return $value === '';
        }

        return $value === null;
    }

    public function getName(): string
    {
        return 'IS_EMPTY';
    }

    public function getDefaultValue(): mixed
    {
        return true;
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
