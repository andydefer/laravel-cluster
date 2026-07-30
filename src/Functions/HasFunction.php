<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

use AndyDefer\LaravelCluster\Contracts\Filters\AggregateFunctionInterface;

final class HasFunction implements AggregateFunctionInterface
{
    public function execute(array $data, array $args): bool
    {
        $path = $args[0] ?? null;
        $key = $args[1] ?? null;
        $value = $args[2] ?? null;

        // ✅ Extraire directement le chemin
        $items = $this->extractValue($data, $path);

        if (! is_array($items)) {
            return false;
        }

        // ✅ Cas 1: HAS(tags, "php") - 2 arguments
        if ($value === null) {
            foreach ($items as $item) {
                if ($item == $key) {
                    return true;
                }
            }

            return false;
        }

        // ✅ Cas 2: HAS(addresses, city, "Kinshasa") - 3 arguments
        foreach ($items as $item) {
            if (is_array($item) && isset($item[$key]) && $item[$key] == $value) {
                return true;
            }
        }

        return false;
    }

    private function extractValue(array $data, string $path): mixed
    {
        $parts = explode('.', $path);
        $current = $data;

        foreach ($parts as $part) {
            if (! isset($current[$part])) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
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
        return 2;
    }

    public function getMaxArgs(): int
    {
        return 3;
    }

    public function validateArgs(array $args): bool
    {
        $count = count($args);

        return $count >= 2 && $count <= 3;
    }
}
