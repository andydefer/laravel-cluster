<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Contracts;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

interface NodeInterface
{
    public function evaluate(ClusterVO $cluster): bool;

    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string;

    public function toEloquent(Builder $query, string $column, DatabaseDriver $driver): void;

    /**
     * @return array<int, NodeInterface>
     */
    public function getChildren(): array;
}
