<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Providers;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Parser;
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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

    public function boot(): void
    {
        // ✅ Macro sur Builder - whereCluster
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

        // ✅ Macro sur Collection - whereCluster
        Collection::macro('whereCluster', function (string $column, string $query) {
            /** @var Collection $this */

            // 1️⃣ Construire la ClusterVOCollection avec conservation des clés
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

            // 2️⃣ Filtrer avec gestion des erreurs
            try {
                $filteredClusters = $clusterCollection->whereQuery($query);
            } catch (\Throwable $e) {
                return new static([]);
            }

            // 3️⃣ Récupérer les éléments correspondants avec leurs clés originales
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
}
