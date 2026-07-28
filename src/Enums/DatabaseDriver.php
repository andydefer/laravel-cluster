<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

/**
 * Enumeration of supported database drivers.
 *
 * This enum provides type-safe identification of database systems
 * for generating dialect-specific SQL syntax.
 *
 * @example
 * $driver = DatabaseDriver::MYSQL;
 * if ($driver->isMySql()) {
 *     // Use MySQL-specific syntax
 * }
 */
enum DatabaseDriver: string
{
    case MYSQL = 'mysql';
    case PGSQL = 'pgsql';
    case SQLITE = 'sqlite';

    /**
     * Returns all driver values as an array.
     *
     * @return array<string> Array of driver string values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Creates an enum instance from a string value.
     *
     * @param  string  $value  The driver string value
     * @return ?self The enum instance or null if not found
     */
    public static function fromValue(string $value): ?self
    {
        return match ($value) {
            'mysql' => self::MYSQL,
            'pgsql' => self::PGSQL,
            'sqlite' => self::SQLITE,
            default => null,
        };
    }

    /**
     * Determines if the driver is MySQL.
     *
     * @return bool True if the driver is MySQL
     */
    public function isMySql(): bool
    {
        return $this === self::MYSQL;
    }

    /**
     * Determines if the driver is PostgreSQL.
     *
     * @return bool True if the driver is PostgreSQL
     */
    public function isPostgreSql(): bool
    {
        return $this === self::PGSQL;
    }

    /**
     * Determines if the driver is SQLite.
     *
     * @return bool True if the driver is SQLite
     */
    public function isSqlite(): bool
    {
        return $this === self::SQLITE;
    }

    /**
     * Determines if the driver supports JSON operations.
     */
    public function supportsJson(): bool
    {
        return $this === self::MYSQL || $this === self::PGSQL;
    }

    /**
     * Determines if the driver supports full-text search.
     */
    public function supportsFullText(): bool
    {
        return $this === self::MYSQL || $this === self::PGSQL;
    }
}
