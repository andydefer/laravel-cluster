<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Providers;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Parser;
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Laravel Cluster package.
 *
 * Registers the core services, binds interfaces to implementations,
 * and provides macros for Eloquent Builder and Laravel Collections.
 *
 * @example
 * // In config/app.php
 * 'providers' => [
 *     // ...
 *     ClusterServiceProvider::class,
 * ];
 * @example
 * // Usage in Eloquent
 * User::whereCluster('clusters', 'status=active')->get();
 * @example
 * // Usage in Collections
 * $collection->whereCluster('clusters', 'status=active');
 */
final class ClusterServiceProvider extends ServiceProvider
{
    /**
     * Registers the package services in the container.
     */
    public function register(): void
    {
        $this->app->singleton(ClusterQuery::class, function (): ClusterQuery {
            return new ClusterQuery(new Parser);
        });

        $this->app->singleton(ClusterService::class, function ($app): ClusterService {
            return new ClusterService($app->make(ClusterQuery::class));
        });

        $this->app->singleton(SqlFunctionRegistry::class, function (): SqlFunctionRegistry {
            return new SqlFunctionRegistry;
        });

        $this->app->alias(ClusterQuery::class, 'cluster.query');
        $this->app->alias(ClusterService::class, 'cluster.service');
        $this->app->alias(SqlFunctionRegistry::class, 'cluster.sql_functions');
    }

    /**
     * Bootstraps the package services.
     *
     * Registers custom SQLite functions and adds the `whereCluster` macro
     * to Eloquent Builder and Laravel Collections.
     */
    public function boot(): void
    {
        $this->registerSqliteFunctionsIfNeeded();

        Builder::macro('whereCluster', function (string $column, string $query) {
            /** @var Builder $this */
            $connection = $this->getConnection();
            $driverName = $connection->getDriverName();

            $driver = match ($driverName) {
                'mysql' => DatabaseDriver::MYSQL,
                'pgsql' => DatabaseDriver::PGSQL,
                'sqlite' => DatabaseDriver::SQLITE,
                default => DatabaseDriver::SQLITE,
            };

            $clusterQuery = app(ClusterQuery::class);
            $clusterQuery->applyToEloquent($this, $column, $query, $driver);

            return $this;
        });

        Collection::macro('whereCluster', function (string $column, string $query) {
            /** @var Collection $this */
            $clusterCollection = new ClusterVOCollection;
            $items = [];
            $keys = [];

            foreach ($this as $key => $item) {
                $normalized = action_normalizer_chain()->normalize($item);
                $data = $normalized[$column] ?? null;

                if (is_array($data)) {
                    $clusterCollection->add(new ClusterVO($data));
                    $items[] = $item;
                    $keys[] = $key;
                }
            }

            try {
                $filteredClusters = $clusterCollection->whereQuery($query);
            } catch (\Throwable $e) {
                return new static([]);
            }

            $result = [];
            $clusterArray = $clusterCollection->toArray();

            foreach ($filteredClusters as $filteredCluster) {
                $index = array_search($filteredCluster, $clusterArray, true);
                if ($index !== false && isset($items[$index])) {
                    $originalKey = $keys[$index];
                    $result[$originalKey] = $items[$index];
                }
            }

            return new static($result);
        });
    }

    /**
     * Registers custom SQL functions for SQLite databases only.
     *
     * MySQL and PostgreSQL already have these functions natively.
     * For SQLite, we register JSON_* functions as custom PDO functions.
     *
     * @throws \Throwable If registration fails, the error is reported but not thrown
     */
    private function registerSqliteFunctionsIfNeeded(): void
    {
        try {
            $connection = DB::connection();
            $driverName = $connection->getDriverName();

            if ($driverName !== 'sqlite') {
                return;
            }

            $pdo = $connection->getPdo();

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

        } catch (\Throwable $e) {
            report($e);
        }
    }
}
