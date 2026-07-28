<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

enum DatabaseDriver: string
{
    case MYSQL = 'mysql';
    case PGSQL = 'pgsql';
    case SQLITE = 'sqlite';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromValue(string $value): ?self
    {
        return match ($value) {
            'mysql' => self::MYSQL,
            'pgsql' => self::PGSQL,
            'sqlite' => self::SQLITE,
            default => null,
        };
    }

    public function isMySql(): bool
    {
        return $this === self::MYSQL;
    }

    public function isPostgreSql(): bool
    {
        return $this === self::PGSQL;
    }

    public function isSqlite(): bool
    {
        return $this === self::SQLITE;
    }
}
