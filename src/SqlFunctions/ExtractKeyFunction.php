<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Contracts\SqlFunctionInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;

/**
 * Function to extract a key value from a JSON object in a cluster.
 *
 * Usage: EXTRACT_KEY(slug, pharmacy) = 'pharma-a'
 * This will extract the 'slug' from the 'pharmacy' object.
 *
 * Supports nested keys with dot notation:
 * EXTRACT_KEY(profile.name, pharmacy) = 'Jean Dupont'
 *
 * SQL generated:
 * - SQLite: json_extract(clusters, '$.pharmacy.slug')
 * - MySQL: JSON_EXTRACT(clusters, '$.pharmacy.slug')
 * - PostgreSQL: clusters->>'pharmacy.slug'
 */
final class ExtractKeyFunction implements SqlFunctionInterface
{
    public function getName(): string
    {
        return 'EXTRACT_KEY';
    }

    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string
    {
        // $args[0] = key to extract (ex: 'slug' or 'profile.name')
        // $args[1] = object path (ex: 'pharmacy')
        $key = $args[0] ?? null;
        $objectPath = $args[1] ?? null;

        if ($key === null || $objectPath === null) {
            return 'NULL';
        }

        // Construction du chemin JSON complet : pharmacy.slug ou pharmacy.profile.name
        $fullPath = $objectPath.'.'.$key;

        return match ($driver) {
            DatabaseDriver::SQLITE => sprintf(
                "json_extract(%s, '$.%s')",
                $column,
                $fullPath
            ),
            DatabaseDriver::MYSQL => sprintf(
                "JSON_EXTRACT(%s, '$.%s')",
                $column,
                $fullPath
            ),
            DatabaseDriver::PGSQL => sprintf(
                "%s->>'%s'",
                $column,
                $fullPath
            ),
        };
    }

    public function execute(mixed $value, array $args = []): mixed
    {
        // $args[0] = key to extract (ex: 'slug' or 'profile.name')
        // $args[1] = object path (ex: 'pharmacy')
        $key = $args[0] ?? null;
        $objectPath = $args[1] ?? null;

        if ($key === null || ! is_array($value)) {
            return null;
        }

        // ✅ Support des chemins imbriqués (ex: 'profile.name')
        $parts = explode('.', $key);

        // Cas 1: La valeur est déjà l'objet extrait (ex: pharmacy)
        if (isset($value[$key])) {
            return $value[$key];
        }

        // Cas 2: La valeur est le tableau complet (ex: ['pharmacy' => [...]])
        if ($objectPath !== null && isset($value[$objectPath]) && is_array($value[$objectPath])) {
            $current = $value[$objectPath];

            foreach ($parts as $part) {
                if (! is_array($current) || ! array_key_exists($part, $current)) {
                    return null;
                }
                $current = $current[$part];
            }

            return $current;
        }

        // Cas 3: La valeur est l'objet et on cherche un chemin imbriqué directement
        $current = $value;
        foreach ($parts as $part) {
            if (! is_array($current) || ! array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }

    public function getDefaultValue(): mixed
    {
        return null;
    }

    public function getReturnType(): string
    {
        return 'string';
    }

    public function validateArgs(array $args): bool
    {
        // EXTRACT_KEY requires exactly 2 arguments: key and object path
        if (count($args) !== 2) {
            return false;
        }

        // Both arguments must be non-empty strings
        if (! is_string($args[0]) || empty($args[0])) {
            return false;
        }

        if (! is_string($args[1]) || empty($args[1])) {
            return false;
        }

        return true;
    }

    public function getMinArgs(): int
    {
        return 2;
    }

    public function getMaxArgs(): int
    {
        return 2;
    }
}
