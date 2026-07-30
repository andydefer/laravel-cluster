<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

/**
 * Finds the maximum numeric value in a JSON array.
 *
 * @example
 * $max = new MaxFunction();
 * $max->execute([10, 30, 20]); // 30.0
 * @example
 * // SQL generation for different drivers
 * $max->toSql('clusters', 'scores', DatabaseDriver::SQLITE);
 * // MAX(CAST(json_extract(clusters, '$.scores') AS NUMERIC))
 */
final class MaxFunction extends AbstractSqlFunction
{
    public function getName(): string
    {
        return 'MAX';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "MAX(CAST(json_extract(%s, '$.%s') AS NUMERIC))",
                $column,
                $path
            ),
            DatabaseDriver::MYSQL => sprintf(
                "MAX(CAST(JSON_EXTRACT(%s, '$.%s') AS DECIMAL(10,2)))",
                $column,
                $path
            ),
            DatabaseDriver::PGSQL => sprintf(
                "MAX((%s->>'%s')::numeric)",
                $column,
                $path
            ),
        };
    }

    public function getReturnType(): string
    {
        return 'float';
    }

    public function execute(mixed $value): mixed
    {
        if (! is_array($value) || empty($value)) {
            return 0;
        }

        $numbers = $this->extractNumbers($value);

        return ! empty($numbers) ? max($numbers) : 0;
    }
}
