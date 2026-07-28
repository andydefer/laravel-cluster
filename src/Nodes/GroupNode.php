<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

/**
 * Represents a composite node that groups multiple conditions with a logical operator.
 *
 * A GroupNode combines multiple child nodes using AND, OR, or NOT logical
 * operators. It supports both binary (AND, OR) and unary (NOT) operations.
 *
 * @example
 * // (age > 18 AND status = 'active')
 * $group = new GroupNode(
 *     LogicalOperator::AND,
 *     new ConditionNode('age', ComparisonOperator::GREATER_THAN, '18'),
 *     new ConditionNode('status', ComparisonOperator::EQUAL, 'active')
 * );
 */
final class GroupNode extends Node
{
    /**
     * The child nodes grouped by this logical operation.
     *
     * @var array<int, NodeInterface>
     */
    private array $children = [];

    /**
     * Initializes a group node with a logical operator and children.
     *
     * @param  LogicalOperator  $operator  The logical operator to apply (AND, OR, NOT)
     * @param  NodeInterface  ...$children  The child nodes to group
     */
    public function __construct(
        private readonly LogicalOperator $operator,
        NodeInterface ...$children
    ) {
        $this->children = $children;
    }

    /**
     * Evaluates the group condition against a cluster instance.
     *
     * For AND/OR operators, evaluates children sequentially.
     * For NOT operator, applies logical negation.
     *
     * @param  ClusterVO  $data  The cluster containing the data to evaluate
     * @return bool True if the group condition is satisfied, false otherwise
     */
    public function evaluate(ClusterVO $data): bool
    {
        if (empty($this->children)) {
            return $this->operator === LogicalOperator::AND;
        }

        // Handle NOT operator as unary
        if ($this->operator === LogicalOperator::NOT) {
            return ! $this->children[0]->evaluate($data);
        }

        // Handle binary operators (AND, OR)
        $result = $this->children[0]->evaluate($data);

        for ($i = 1; $i < count($this->children); $i++) {
            $result = $this->operator->evaluate($result, $this->children[$i]->evaluate($data));
        }

        return $result;
    }

    /**
     * Converts the group condition to a SQL expression.
     *
     * @param  string  $column  The JSON column name
     * @param  DatabaseDriver  $driver  The database driver for dialect-specific syntax
     * @return string The SQL expression string
     */
    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        if (empty($this->children)) {
            return '1=1';
        }

        if ($this->operator === LogicalOperator::NOT) {
            return 'NOT ('.$this->children[0]->toSql($column, $driver).')';
        }

        $parts = array_map(
            fn (NodeInterface $child) => $child->toSql($column, $driver),
            $this->children
        );

        $glue = $this->operator->getSqlGlue();

        return count($parts) > 1 ? '('.implode($glue, $parts).')' : $parts[0];
    }

    /**
     * Applies the group condition to an Eloquent query builder.
     *
     * @param  Builder  $query  The Eloquent query builder instance
     * @param  string  $column  The JSON column name
     * @param  DatabaseDriver  $driver  The database driver for dialect-specific syntax
     */
    public function toEloquent(Builder $query, string $column, DatabaseDriver $driver): void
    {
        if (empty($this->children)) {
            return;
        }

        $query->where(function (Builder $subQuery) use ($column, $driver) {
            foreach ($this->children as $index => $child) {
                if ($index === 0) {
                    $child->toEloquent($subQuery, $column, $driver);
                } else {
                    if ($this->operator === LogicalOperator::OR) {
                        $subQuery->orWhere(function (Builder $orSub) use ($child, $column, $driver) {
                            $child->toEloquent($orSub, $column, $driver);
                        });
                    } else {
                        $child->toEloquent($subQuery, $column, $driver);
                    }
                }
            }
        });
    }

    /**
     * Returns the child nodes of this group.
     *
     * @return array<int, NodeInterface> An array of child nodes
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Applies a binary logical operation to an Eloquent query builder.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $method  The Eloquent method name (where, orWhere, etc.)
     * @param  string  $column  The JSON column name
     * @param  DatabaseDriver  $driver  The database driver
     */
    private function applyBinaryOperation(Builder $query, string $method, string $column, DatabaseDriver $driver): void
    {
        $query->$method(function (Builder $subQuery) use ($column, $driver) {
            $first = true;
            foreach ($this->children as $child) {
                if ($first) {
                    $child->toEloquent($subQuery, $column, $driver);
                    $first = false;
                } else {
                    $this->applySubsequentCondition($subQuery, $child, $column, $driver);
                }
            }
        });
    }

    /**
     * Applies subsequent conditions to an Eloquent query builder.
     *
     * @param  Builder  $subQuery  The Eloquent sub-query builder
     * @param  NodeInterface  $child  The child node to apply
     * @param  string  $column  The JSON column name
     * @param  DatabaseDriver  $driver  The database driver
     */
    private function applySubsequentCondition(Builder $subQuery, NodeInterface $child, string $column, DatabaseDriver $driver): void
    {
        if ($this->operator === LogicalOperator::OR) {
            $subQuery->orWhere(function (Builder $orSub) use ($child, $column, $driver) {
                $child->toEloquent($orSub, $column, $driver);
            });
        } else {
            $child->toEloquent($subQuery, $column, $driver);
        }
    }
}
