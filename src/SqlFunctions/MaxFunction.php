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

    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string
    {
        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "(SELECT MAX(json_extract(value, '$')) FROM json_each(%s, '$.%s'))",
                $column,
                $path
            ),
            DatabaseDriver::MYSQL => sprintf(
                "(SELECT MAX(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(%s, '$.\"%s\"[*]' COLUMNS(value JSON PATH '$')) AS jt)",
                $column,
                $path
            ),
            DatabaseDriver::PGSQL => sprintf(
                "(SELECT MAX((value->>'$')::numeric) FROM json_array_elements(%s->'%s') AS value)",
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

        return ! empty($numbers) ? max($numbers) : 0;
    }
}
