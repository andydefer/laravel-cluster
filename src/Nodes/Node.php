<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Contracts\NodeInterface;

/**
 * Abstract base class for all AST nodes in the query expression tree.
 *
 * Provides default implementations for common node operations and serves
 * as the foundation for specific node types (ConditionNode, GroupNode, NotNode).
 *
 * @example
 * // Extend this class to create custom node types
 * class CustomNode extends Node
 * {
 *     public function evaluate(ClusterVO $data): bool { ... }
 *     public function toSql(string $column, DatabaseDriver $driver): string { ... }
 *     public function toEloquent(Builder $query, string $column, DatabaseDriver $driver): void { ... }
 * }
 */
abstract class Node implements NodeInterface
{
    /**
     * Returns the child nodes of this node (empty by default for leaf nodes).
     *
     * Override this method in composite nodes to return their children.
     *
     * @return array<int, NodeInterface> An array of child nodes
     */
    public function getChildren(): array
    {
        return [];
    }
}
