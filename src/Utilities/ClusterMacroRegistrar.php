<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Utilities;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ClusterMacroRegistrar
{
    public static function register(): void
    {
        self::registerBuilderMacro();
        self::registerCollectionMacro();
    }

    private static function registerBuilderMacro(): void
    {
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

            // ✅ TESTER LE SQL GÉNÉRÉ
            $sql = $clusterQuery->toSql($column, $query, $driver);

            $clusterQuery->applyToEloquent($this, $column, $query, $driver);

            return $this;
        });
    }

    private static function registerCollectionMacro(): void
    {
        Collection::macro('whereCluster', function (string $column, string $query) {
            /** @var Collection $this */
            $clusterCollection = new ClusterVOCollection;
            $items = [];
            $keys = [];

            foreach ($this as $key => $item) {
                $data = null;

                // Récupérer les données depuis le modèle
                if (is_object($item) && method_exists($item, 'getAttribute')) {
                    $data = $item->getAttribute($column);
                } elseif (is_array($item)) {
                    $data = $item[$column] ?? null;
                } elseif (is_object($item) && method_exists($item, 'toArray')) {
                    $array = $item->toArray();
                    $data = $array[$column] ?? null;
                }

                // Si c'est déjà un ClusterVO
                if ($data instanceof ClusterVO) {
                    $clusterCollection->add($data);
                    $items[] = $item;
                    $keys[] = $key;

                    continue;
                }

                // Si c'est un tableau non vide
                if (is_array($data) && ! empty($data)) {
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
}
