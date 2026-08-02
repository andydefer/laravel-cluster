<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Contracts\SqlFunctionInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

final class ContainsFunction implements SqlFunctionInterface
{
    public function getName(): string
    {
        return 'CONTAINS';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string
    {
        // $args[0] = path (ex: 'languages')
        // $args[1...] = search values (ex: 'fr', 'en')
        // Toutes les valeurs doivent être présentes (ET logique)
        $searchValues = array_slice($args, 1);
        $searchValues = array_map('addslashes', $searchValues);

        if (empty($searchValues)) {
            return '1=0';
        }

        // Si une seule valeur, on fait une simple recherche
        if (count($searchValues) === 1) {
            $searchValue = $searchValues[0];

            return match ($driver) {
                DatabaseDriver::SQLITE => sprintf(
                    "EXISTS (SELECT 1 FROM json_each(%s, '$.%s') WHERE value = '%s')",
                    $column,
                    $path,
                    $searchValue
                ),
                DatabaseDriver::MYSQL => sprintf(
                    "JSON_SEARCH(%s, 'one', '%s', NULL, '$.\"%s\"') IS NOT NULL",
                    $column,
                    $searchValue,
                    $path
                ),
                DatabaseDriver::PGSQL => sprintf(
                    "EXISTS (SELECT 1 FROM json_array_elements_text(%s->'%s') AS elem WHERE elem = '%s')",
                    $column,
                    $path,
                    $searchValue
                ),
            };
        }

        // Pour plusieurs valeurs, on utilise AND (toutes doivent être présentes)
        $conditions = [];
        foreach ($searchValues as $searchValue) {
            $conditions[] = match ($driver) {
                DatabaseDriver::SQLITE => sprintf(
                    "EXISTS (SELECT 1 FROM json_each(%s, '$.%s') WHERE value = '%s')",
                    $column,
                    $path,
                    $searchValue
                ),
                DatabaseDriver::MYSQL => sprintf(
                    "JSON_SEARCH(%s, 'one', '%s', NULL, '$.\"%s\"') IS NOT NULL",
                    $column,
                    $searchValue,
                    $path
                ),
                DatabaseDriver::PGSQL => sprintf(
                    "EXISTS (SELECT 1 FROM json_array_elements_text(%s->'%s') AS elem WHERE elem = '%s')",
                    $column,
                    $path,
                    $searchValue
                ),
            };
        }

        return '('.implode(' AND ', $conditions).')';
    }

    public function execute(mixed $value, array $args = []): mixed
    {
        // $args[0] = path (ex: 'languages')
        // $args[1...] = search values (ex: 'fr', 'en')
        // Toutes les valeurs doivent être présentes (ET logique)
        if (! is_array($value) || count($args) < 2) {
            return false;
        }

        $searchValues = array_slice($args, 1);

        if (empty($searchValues)) {
            return false;
        }

        // Vérifier que toutes les valeurs sont présentes
        foreach ($searchValues as $searchValue) {
            if (! in_array($searchValue, $value, true)) {
                return false;
            }
        }

        return true;
    }

    public function getDefaultValue(): mixed
    {
        return false;
    }

    public function getReturnType(): string
    {
        return 'bool';
    }

    public function validateArgs(array $args): bool
    {
        // CONTAINS requires at least 2 arguments: path and at least one value
        if (count($args) < 2) {
            return false;
        }

        // Path must be a non-empty string
        if (! is_string($args[0]) || empty($args[0])) {
            return false;
        }

        // All other arguments must be non-empty strings
        for ($i = 1; $i < count($args); $i++) {
            if (! is_string($args[$i]) || empty($args[$i])) {
                return false;
            }
        }

        return true;
    }

    public function getMinArgs(): int
    {
        return 2; // path + at least one value
    }

    public function getMaxArgs(): int
    {
        return PHP_INT_MAX; // unlimited values
    }
}
