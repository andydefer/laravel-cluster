<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Services;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

final class ClusterService
{
    public function __construct(
        private readonly ClusterQuery $clusterQuery
    ) {}

    public function parse(string $query): NodeInterface
    {
        return $this->clusterQuery->parse($query);
    }

    public function filter(ClusterVOCollection $clusters, string $query): ClusterVOCollection
    {
        return $this->clusterQuery->filter($clusters, $query);
    }

    public function matches(ClusterVO $cluster, string $query): bool
    {
        return $this->clusterQuery->matches($cluster, $query);
    }

    public function toSql(string $column, string $query, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        return $this->clusterQuery->toSql($column, $query, $driver);
    }

    public function applyToEloquent(Builder $query, string $column, string $clusterQuery, DatabaseDriver $driver = DatabaseDriver::MYSQL): void
    {
        $this->clusterQuery->applyToEloquent($query, $column, $clusterQuery, $driver);
    }
}
