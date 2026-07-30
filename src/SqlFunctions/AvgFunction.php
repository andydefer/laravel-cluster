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
}
