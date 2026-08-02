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

    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "(SELECT SUM(json_extract(value, '$')) FROM json_each(%s, '$.%s'))",
                $column,
                $path
            ),
            DatabaseDriver::MYSQL => sprintf(
                "(SELECT SUM(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(%s, '$.\"%s\"[*]' COLUMNS(value JSON PATH '$')) AS jt)",
                $column,
                $path
            ),
            DatabaseDriver::PGSQL => sprintf(
                "(SELECT SUM((value->>'$')::numeric) FROM json_array_elements(%s->'%s') AS value)",
                $column,
                $path
            ),
        };
    }

    public function getReturnType(): string
    {
        return 'float';
    }

    public function execute(mixed $value, array $args = []): float
    {
        if (! is_array($value) || empty($value)) {
            return 0.0;
        }

        $numbers = $this->extractNumbers($value);

        return array_sum($numbers);
    }
}
