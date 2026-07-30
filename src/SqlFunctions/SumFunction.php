<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

/**
 * Calculates the sum of numeric values in a JSON array.
 *
 * @example
 * $sum = new SumFunction();
 * $sum->execute([10, 20, 30]); // 60.0
 * @example
 * // SQL generation for different drivers
 * $sum->toSql('clusters', 'prices', DatabaseDriver::PGSQL);
 * // (clusters->>'prices')::numeric
 */
final class SumFunction extends AbstractSqlFunction
{
    public function getName(): string
    {
        return 'SUM';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "CAST(json_extract(%s, '$.%s') AS NUMERIC)",
                $column,
                $path
            ),
            DatabaseDriver::MYSQL => sprintf(
                "CAST(JSON_EXTRACT(%s, '$.%s') AS DECIMAL(10,2))",
                $column,
                $path
            ),
            DatabaseDriver::PGSQL => sprintf(
                "(%s->>'%s')::numeric",
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

        return array_sum($numbers);
    }
}
