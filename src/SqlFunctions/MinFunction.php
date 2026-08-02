<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

/**
 * Finds the minimum numeric value in a JSON array.
 *
 * @example
 * $min = new MinFunction();
 * $min->execute([10, 30, 20]); // 10.0
 * @example
 * // SQL generation for different drivers
 * $min->toSql('clusters', 'scores', DatabaseDriver::MYSQL);
 * // MIN(CAST(JSON_EXTRACT(clusters, '$.scores') AS DECIMAL(10,2)))
 */
final class MinFunction extends AbstractSqlFunction
{
    public function getName(): string
    {
        return 'MIN';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "(SELECT MIN(json_extract(value, '$')) FROM json_each(%s, '$.%s'))",
                $column,
                $path
            ),
            DatabaseDriver::MYSQL => sprintf(
                "(SELECT MIN(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(%s, '$.\"%s\"[*]' COLUMNS(value JSON PATH '$')) AS jt)",
                $column,
                $path
            ),
            DatabaseDriver::PGSQL => sprintf(
                "(SELECT MIN((value->>'$')::numeric) FROM json_array_elements(%s->'%s') AS value)",
                $column,
                $path
            ),
        };
    }

    public function getReturnType(): string
    {
        return 'float';
    }

    public function execute(mixed $value, array $args = []): mixed
    {
        if (! is_array($value) || empty($value)) {
            return 0;
        }

        $numbers = $this->extractNumbers($value);

        return ! empty($numbers) ? min($numbers) : 0;
    }
}
