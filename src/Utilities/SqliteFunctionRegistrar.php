<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Utilities;

use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Registers custom SQLite functions for JSON operations.
 *
 * This class provides SQLite with JSON functions that are natively
 * available in MySQL and PostgreSQL.
 *
 * @example
 * SqliteFunctionRegistrar::register();
 */
final class SqliteFunctionRegistrar
{
    /**
     * Register all SQLite JSON functions.
     */
    public static function register(): void
    {
        try {
            $connection = DB::connection();

            if ($connection->getDriverName() !== 'sqlite') {
                return;
            }

            $pdo = $connection->getPdo();

            self::registerJsonLength($pdo);
            self::registerJsonAvg($pdo);
            self::registerJsonSum($pdo);
            self::registerJsonMin($pdo);
            self::registerJsonMax($pdo);

        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Register JSON_LENGTH function.
     */
    private static function registerJsonLength(PDO $pdo): void
    {
        $pdo->sqliteCreateFunction('JSON_LENGTH', function ($json, $path = null) {
            if ($json === null) {
                return null;
            }

            $data = json_decode($json, true);

            if ($path === null || $path === '' || $path === '$') {
                return is_array($data) ? count($data) : null;
            }

            $path = str_replace('$.', '', $path);
            $parts = explode('.', $path);
            $current = $data;

            foreach ($parts as $part) {
                if (! isset($current[$part])) {
                    return null;
                }
                $current = $current[$part];
            }

            return is_array($current) ? count($current) : null;
        });
    }

    /**
     * Register JSON_AVG function.
     */
    private static function registerJsonAvg(PDO $pdo): void
    {
        $pdo->sqliteCreateFunction('JSON_AVG', function ($json, $path) {
            if ($json === null) {
                return null;
            }

            $data = json_decode($json, true);
            $path = str_replace('$.', '', $path);
            $parts = explode('.', $path);
            $current = $data;

            foreach ($parts as $part) {
                if (! isset($current[$part])) {
                    return null;
                }
                $current = $current[$part];
            }

            if (! is_array($current) || empty($current)) {
                return null;
            }

            $numbers = array_filter($current, 'is_numeric');
            $count = count($numbers);

            return $count > 0 ? array_sum($numbers) / $count : null;
        });
    }

    /**
     * Register JSON_SUM function.
     */
    private static function registerJsonSum(PDO $pdo): void
    {
        $pdo->sqliteCreateFunction('JSON_SUM', function ($json, $path) {
            if ($json === null) {
                return null;
            }

            $data = json_decode($json, true);
            $path = str_replace('$.', '', $path);
            $parts = explode('.', $path);
            $current = $data;

            foreach ($parts as $part) {
                if (! isset($current[$part])) {
                    return null;
                }
                $current = $current[$part];
            }

            if (! is_array($current) || empty($current)) {
                return null;
            }

            $numbers = array_filter($current, 'is_numeric');

            return array_sum($numbers);
        });
    }

    /**
     * Register JSON_MIN function.
     */
    private static function registerJsonMin(PDO $pdo): void
    {
        $pdo->sqliteCreateFunction('JSON_MIN', function ($json, $path) {
            if ($json === null) {
                return null;
            }

            $data = json_decode($json, true);
            $path = str_replace('$.', '', $path);
            $parts = explode('.', $path);
            $current = $data;

            foreach ($parts as $part) {
                if (! isset($current[$part])) {
                    return null;
                }
                $current = $current[$part];
            }

            if (! is_array($current) || empty($current)) {
                return null;
            }

            $numbers = array_filter($current, 'is_numeric');

            return ! empty($numbers) ? min($numbers) : null;
        });
    }

    /**
     * Register JSON_MAX function.
     */
    private static function registerJsonMax(PDO $pdo): void
    {
        $pdo->sqliteCreateFunction('JSON_MAX', function ($json, $path) {
            if ($json === null) {
                return null;
            }

            $data = json_decode($json, true);
            $path = str_replace('$.', '', $path);
            $parts = explode('.', $path);
            $current = $data;

            foreach ($parts as $part) {
                if (! isset($current[$part])) {
                    return null;
                }
                $current = $current[$part];
            }

            if (! is_array($current) || empty($current)) {
                return null;
            }

            $numbers = array_filter($current, 'is_numeric');

            return ! empty($numbers) ? max($numbers) : null;
        });
    }
}
