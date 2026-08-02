<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

/**
 * Calculates the average of numeric values in a JSON array.
 *
 * @example
 * $avg = new AvgFunction();
 * $avg->execute([10, 20, 30]); // 20.0
 * @example
 * // SQL generation for different drivers
 * $avg->toSql('clusters', 'scores', DatabaseDriver::MYSQL);
 * // AVG(CAST(JSON_EXTRACT(clusters, '$.scores') AS DECIMAL(10,2)))
 */
final class AvgFunction extends AbstractSqlFunction
{
    public function getName(): string
    {
        return 'AVG';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "(SELECT AVG(json_extract(value, '$')) FROM json_each(%s, '$.%s'))",
                $column,
                $path
            ),
            DatabaseDriver::MYSQL => sprintf(
                "(SELECT AVG(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(%s, '$.\"%s\"[*]' COLUMNS(value JSON PATH '$')) AS jt)",
                $column,
                $path
            ),
            DatabaseDriver::PGSQL => sprintf(
                "(SELECT AVG((value->>'$')::numeric) FROM json_array_elements(%s->'%s') AS value)",
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
        $count = count($numbers);

        return $count > 0 ? array_sum($numbers) / $count : 0.0;
    }
}
