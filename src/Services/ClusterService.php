<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Services;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

/**
 * Primary service for interacting with the cluster query system.
 *
 * This service acts as a facade to the underlying ClusterQuery engine,
 * providing a clean API for parsing, filtering, matching, and generating
 * SQL from cluster query expressions.
 *
 * @example
 * $service = new ClusterService(new ClusterQuery());
 *
 * // Parse a query string into an AST
 * $node = $service->parse('age > 18 AND status = "active"');
 *
 * // Filter a collection of clusters
 * $filtered = $service->filter($clusters, 'score > 80');
 *
 * // Check if a single cluster matches
 * $matches = $service->matches($cluster, 'role = "admin"');
 *
 * // Generate SQL for database queries
 * $sql = $service->toSql('json_column', 'age > 18');
 */
final class ClusterService
{
    /**
     * @param  ClusterQuery  $clusterQuery  The underlying query engine
     */
    public function __construct(
        private readonly ClusterQuery $clusterQuery
    ) {}

    /**
     * Parses a query string into an abstract syntax tree (AST).
     *
     * @param  string  $query  The cluster query string to parse
     * @return NodeInterface The root node of the parsed AST
     */
    public function parse(string $query): NodeInterface
    {
        return $this->clusterQuery->parse($query);
    }

    /**
     * Filters a collection of clusters using a query expression.
     *
     * Returns a new collection containing only the clusters that satisfy
     * the given query conditions.
     *
     * @param  ClusterVOCollection  $clusters  The collection to filter
     * @param  string  $query  The filter query expression
     * @return ClusterVOCollection The filtered collection
     */
    public function filter(ClusterVOCollection $clusters, string $query): ClusterVOCollection
    {
        return $this->clusterQuery->filter($clusters, $query);
    }

    /**
     * Determines if a single cluster matches a query expression.
     *
     * @param  ClusterVO  $cluster  The cluster to evaluate
     * @param  string  $query  The query expression to test
     * @return bool True if the cluster matches the query
     */
    public function matches(ClusterVO $cluster, string $query): bool
    {
        return $this->clusterQuery->matches($cluster, $query);
    }

    /**
     * Converts a cluster query to a SQL WHERE clause.
     *
     * Generates database-specific SQL for the query expression,
     * suitable for use in JSON column queries.
     *
     * @param  string  $column  The JSON column name in the database
     * @param  string  $query  The cluster query expression
     * @param  DatabaseDriver  $driver  The target database driver (default: MySQL)
     * @return string The generated SQL WHERE clause
     */
    public function toSql(
        string $column,
        string $query,
        DatabaseDriver $driver = DatabaseDriver::MYSQL
    ): string {
        return $this->clusterQuery->toSql($column, $query, $driver);
    }

    /**
     * Applies a cluster query to an Eloquent query builder.
     *
     * Adds WHERE conditions to the Eloquent query for the given JSON column
     * and cluster query expression.
     *
     * @param  Builder  $query  The Eloquent query builder to modify
     * @param  string  $column  The JSON column name
     * @param  string  $clusterQuery  The cluster query expression
     * @param  DatabaseDriver  $driver  The target database driver (default: MySQL)
     */
    public function applyToEloquent(
        Builder $query,
        string $column,
        string $clusterQuery,
        DatabaseDriver $driver = DatabaseDriver::MYSQL
    ): void {
        $this->clusterQuery->applyToEloquent($query, $column, $clusterQuery, $driver);
    }
}
