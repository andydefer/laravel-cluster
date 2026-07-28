<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Contracts;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

/**
 * Defines the contract for AST nodes in the query expression tree.
 *
 * Each node represents a part of the parsed query (comparison, logical operation,
 * grouping, etc.) and provides methods for evaluation and SQL generation.
 *
 * @example
 * // A comparison node: age > 25
 * $node = new ComparisonNode('age', '>', 25);
 * $result = $node->evaluate($cluster); // bool
 * $sql = $node->toSql('age', DatabaseDriver::MYSQL); // 'age > 25'
 */
interface NodeInterface
{
    /**
     * Evaluates the node against a cluster instance.
     *
     * @param  ClusterVO  $cluster  The cluster to evaluate against
     * @return bool True if the node condition is satisfied, false otherwise
     */
    public function evaluate(ClusterVO $cluster): bool;

    /**
     * Converts the node to its SQL representation.
     *
     * @param  string  $column  The column name to use in the SQL expression
     * @param  DatabaseDriver  $driver  The database driver for dialect-specific syntax
     * @return string The SQL expression string
     */
    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string;

    /**
     * Applies the node condition to an Eloquent query builder.
     *
     * @param  Builder  $query  The Eloquent query builder instance
     * @param  string  $column  The column name to apply the condition to
     * @param  DatabaseDriver  $driver  The database driver for dialect-specific syntax
     */
    public function toEloquent(Builder $query, string $column, DatabaseDriver $driver): void;

    /**
     * Returns the child nodes of this node (for tree traversal).
     *
     * @return array<int, NodeInterface> An array of child nodes
     */
    public function getChildren(): array;
}
