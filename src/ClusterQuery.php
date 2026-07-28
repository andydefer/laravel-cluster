<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster;

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Contracts\ParserInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

final class ClusterQuery
{
    private ParserInterface $parser;

    public function __construct(?ParserInterface $parser = null)
    {
        $this->parser = $parser ?? new Parser;
    }

    public function parse(string $query): NodeInterface
    {
        return $this->parser->parse($query);
    }

    public function filter(ClusterVOCollection $clusters, string $query): ClusterVOCollection
    {
        $ast = $this->parse($query);

        return $clusters->filter(
            fn (ClusterVO $cluster) => $ast->evaluate($cluster)
        );
    }

    public function matches(ClusterVO $cluster, string $query): bool
    {
        $ast = $this->parse($query);

        return $ast->evaluate($cluster);
    }

    public function toSql(string $column, string $query, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        $ast = $this->parse($query);

        return $ast->toSql($column, $driver);
    }

    public function applyToEloquent(Builder $query, string $column, string $clusterQuery, DatabaseDriver $driver = DatabaseDriver::MYSQL): void
    {
        $ast = $this->parse($clusterQuery);
        $ast->toEloquent($query, $column, $driver);
    }
}
