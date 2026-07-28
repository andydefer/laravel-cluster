<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

final class NotNode extends Node
{
    public function __construct(
        private readonly NodeInterface $node
    ) {}

    public function evaluate(ClusterVO $data): bool
    {
        return ! $this->node->evaluate($data);
    }

    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        return 'NOT ('.$this->node->toSql($column, $driver).')';
    }

    public function toEloquent(Builder $query, string $column, DatabaseDriver $driver): void
    {
        $query->whereNot(function ($subQuery) use ($column, $driver) {
            $this->node->toEloquent($subQuery, $column, $driver);
        });
    }

    /**
     * @return array<int, NodeInterface>
     */
    public function getChildren(): array
    {
        return [$this->node];
    }
}
