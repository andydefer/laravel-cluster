<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Providers;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Parser;
use AndyDefer\LaravelCluster\Services\ClusterService;
use Illuminate\Support\ServiceProvider;

final class ClusterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClusterQuery::class, function (): ClusterQuery {
            return new ClusterQuery(new Parser);
        });

        $this->app->singleton(ClusterService::class, function ($app): ClusterService {
            return new ClusterService($app->make(ClusterQuery::class));
        });

        $this->app->alias(ClusterQuery::class, 'cluster.query');
        $this->app->alias(ClusterService::class, 'cluster.service');
    }

    public function boot(): void {}
}
