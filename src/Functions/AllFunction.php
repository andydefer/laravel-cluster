<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Determines whether every element in a collection satisfies a key-value condition.
 *
 * The ALL function evaluates each item in the target array and returns true
 * only if all items contain the specified key with the expected value.
 * If the collection is empty or not an array, it returns false.
 *
 * @example
 * $function = new AllFunction();
 * $result = $function->execute(
 *     ['items' => [['status' => 'active'], ['status' => 'active']]],
 *     ['items', 'status', 'active']
 * );
 * // Returns: true
 *
 * $result = $function->execute(
 *     ['items' => [['status' => 'active'], ['status' => 'inactive']]],
 *     ['items', 'status', 'active']
 * );
 * // Returns: false
 */
final class AllFunction extends AbstractAggregateFunction
{
    public function execute(array $data, array $args): bool
    {
        $path = $args[0] ?? null;
        $key = $args[1] ?? null;
        $expectedValue = $args[2] ?? null;

        $items = $this->resolvePath($data, $path);

        if (! is_array($items) || empty($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (! $this->itemMatchesCondition($item, $key, $expectedValue)) {
                return false;
            }
        }

        return true;
    }

    public function getName(): string
    {
        return 'ALL';
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

    /**
     * Checks if an item matches a key-value condition.
     *
     * @param  array|string  $item  The item to check
     * @param  string|null  $key  The key to look for
     * @param  mixed  $expectedValue  The expected value
     * @return bool True if the item matches the condition
     */
    private function itemMatchesCondition(array|string $item, ?string $key, mixed $expectedValue): bool
    {
        if (is_string($item)) {
            return $item == $expectedValue;
        }

        if (is_array($item)) {
            if ($key === null) {
                return $item == $expectedValue;
            }

            return array_key_exists($key, $item) && $item[$key] == $expectedValue;
        }

        return false;
    }
}
