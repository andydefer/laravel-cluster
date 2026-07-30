<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Contracts\SqlFunctionInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

final class AvgFunction implements SqlFunctionInterface
{
    public function getName(): string
    {
        return 'AVG';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "AVG(CAST(json_extract(%s, '$.%s') AS NUMERIC))",
                $column,
                $path
            ),
            DatabaseDriver::MYSQL => sprintf(
                "AVG(CAST(JSON_EXTRACT(%s, '$.%s') AS DECIMAL(10,2)))",
                $column,
                $path
            ),
            DatabaseDriver::PGSQL => sprintf(
                "AVG((%s->>'%s')::numeric)",
                $column,
                $path
            ),
        };
    }

    public function getReturnType(): string
    {
        return 'float';
    }

    public function execute(mixed $value): float
    {
        if (! is_array($value) || empty($value)) {
            return 0.0;
        }

        $numbers = $this->extractNumbers($value);
        $count = count($numbers);

        return $count > 0 ? array_sum($numbers) / $count : 0.0;
    }

    private function extractNumbers(array $array): array
    {
        $numbers = [];

        foreach ($array as $item) {
            if (is_array($item)) {
                $numbers = array_merge($numbers, $this->extractNumbers($item));
            } elseif (is_numeric($item)) {
                $numbers[] = (float) $item;
            }
        }

        return $numbers;
    }

    public function validateArgs(array $args): bool
    {
        return count($args) === 1;
    }

    public function getDefaultValue(): mixed
    {
        return 0.0;
    }
}
