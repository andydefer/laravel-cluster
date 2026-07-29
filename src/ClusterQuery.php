<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster;

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Contracts\ParserInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

/**
 * Core engine for parsing and evaluating cluster queries.
 *
 * The ClusterQuery class is the central orchestration layer for the cluster
 * query system. It handles:
 * - Parsing query strings into Abstract Syntax Trees (AST)
 * - Filtering collections of clusters
 * - Testing individual clusters against queries
 * - Generating SQL from queries
 * - Applying queries to Eloquent builders
 *
 * @example
 * $clusterQuery = new ClusterQuery();
 *
 * // Parse a query
 * $ast = $clusterQuery->parse('age > 18 AND status = "active"');
 *
 * // Filter a collection
 * $filtered = $clusterQuery->filter($clusters, 'score > 80');
 *
 * // Check if a cluster matches
 * $matches = $clusterQuery->matches($cluster, 'role = "admin"');
 *
 * // Generate SQL
 * $sql = $clusterQuery->toSql('json_column', 'age > 18');
 *
 * // Apply to Eloquent
 * $clusterQuery->applyToEloquent($query, 'json_column', 'age > 18');
 */
final class ClusterQuery
{
    private readonly ParserInterface $parser;

    /**
     * @param  ParserInterface|null  $parser  The parser instance (creates default if null)
     */
    public function __construct(?ParserInterface $parser = null)
    {
        $this->parser = $parser ?? new Parser;
    }

    /**
     * Parses a query string into an Abstract Syntax Tree (AST).
     *
     * The AST is a tree of NodeInterface objects that can be evaluated,
     * converted to SQL, or applied to Eloquent queries.
     *
     * @param  string  $query  The cluster query string to parse
     * @return NodeInterface The root node of the AST
     */
    public function parse(string $query): NodeInterface
    {
        return $this->parser->parse($query);
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
        echo "\n=== ClusterQuery::filter ===\n";
        echo "Query: $query\n";
        echo 'Collection count: '.$clusters->count()."\n";

        $ast = $this->parse($query);

        echo 'AST class: '.get_class($ast)."\n";
        if ($ast instanceof SubConditionNode) {
            echo 'AST path: '.$ast->getPath()."\n";
            echo 'AST condition: '.get_class($ast->getCondition())."\n";
        }

        $result = $clusters->filter(
            fn (ClusterVO $cluster) => $ast->evaluate($cluster)
        );

        echo 'Result count: '.$result->count()."\n";

        return $result;
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
        $ast = $this->parse($query);

        return $ast->evaluate($cluster);
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
        $ast = $this->parse($query);

        return $ast->toSql($column, $driver);
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
        $ast = $this->parse($clusterQuery);
        $ast->toEloquent($query, $column, $driver);
    }
}
