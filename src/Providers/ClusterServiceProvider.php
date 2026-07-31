<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Providers;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Parser;
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Utilities\ClusterMacroRegistrar;
use AndyDefer\LaravelCluster\Utilities\SqliteFunctionRegistrar;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Laravel Cluster package.
 *
 * Registers the core services and macros.
 *
 * @example
 * // In config/app.php
 * 'providers' => [
 *     // ...
 *     ClusterServiceProvider::class,
 * ];
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
     */
    public function boot(): void
    {
        SqliteFunctionRegistrar::register();
        ClusterMacroRegistrar::register();
    }
}
