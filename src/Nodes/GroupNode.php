<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

/**
 * Groups multiple condition nodes with a logical operator.
 *
 * A GroupNode combines child nodes using AND, OR, or NOT logical operators.
 * It supports both binary operations (AND, OR) with multiple children and
 * unary operations (NOT) with a single child.
 *
 * @example
 * // (age > 18 AND status = 'active')
 * $group = new GroupNode(
 *     LogicalOperator::AND,
 *     new ConditionNode('age', ComparisonOperator::GREATER_THAN, '18'),
 *     new ConditionNode('status', ComparisonOperator::EQUAL, 'active')
 * );
 * @example
 * // NOT (age < 18)
 * $group = new GroupNode(
 *     LogicalOperator::NOT,
 *     new ConditionNode('age', ComparisonOperator::LESS_THAN, '18')
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
     * @param  LogicalOperator  $operator  The logical operator (AND, OR, NOT)
     * @param  NodeInterface  ...$children  The child nodes to group
     */
    public function __construct(
        private readonly LogicalOperator $operator,
        NodeInterface ...$children
    ) {
        $this->children = $children;
    }

    /**
     * Evaluates the group condition against cluster data.
     *
     * For AND/OR operators, evaluates all children sequentially.
     * For NOT operator, applies logical negation to the first child.
     * Empty groups evaluate to true for AND, false for OR/NOT.
     *
     * @param  ClusterVO  $data  The cluster data to evaluate
     * @return bool True if the group condition is satisfied
     */
    public function evaluate(ClusterVO $data): bool
    {
        if (empty($this->children)) {
            return $this->operator === LogicalOperator::AND;
        }

        if ($this->operator === LogicalOperator::NOT) {
            return ! $this->children[0]->evaluate($data);
        }

        return $this->evaluateBinaryOperation($data);
    }

    /**
     * Generates a SQL expression for the group condition.
     *
     * @param  string  $column  The JSON column name
     * @param  DatabaseDriver  $driver  The database driver
     * @return string The SQL expression
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
     * Uses nested where clauses to maintain proper operator precedence.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $column  The JSON column name
     * @param  DatabaseDriver  $driver  The database driver
     */
    public function toEloquent(Builder $query, string $column, DatabaseDriver $driver): void
    {
        if (empty($this->children)) {
            return;
        }

        if ($this->operator === LogicalOperator::NOT) {
            $this->applyNotOperator($query, $column, $driver);

            return;
        }

        $query->where(function (Builder $subQuery) use ($column, $driver) {
            $this->applyChildrenToSubQuery($subQuery, $column, $driver);
        });
    }

    /**
     * Returns the child nodes of this group.
     *
     * @return array<int, NodeInterface>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Evaluates binary operations (AND, OR) across all children.
     *
     * Starts with the first child's result and sequentially applies
     * the logical operator with each subsequent child.
     *
     * @param  ClusterVO  $data  The cluster data to evaluate
     * @return bool The combined evaluation result
     */
    private function evaluateBinaryOperation(ClusterVO $data): bool
    {
        $result = $this->children[0]->evaluate($data);

        for ($i = 1; $i < count($this->children); $i++) {
            $result = $this->operator->evaluate(
                $result,
                $this->children[$i]->evaluate($data)
            );
        }

        return $result;
    }

    /**
     * Applies the NOT operator to an Eloquent query.
     *
     * Wraps the child condition in a whereNot clause.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $column  The JSON column name
     * @param  DatabaseDriver  $driver  The database driver
     */
    private function applyNotOperator(Builder $query, string $column, DatabaseDriver $driver): void
    {
        $query->whereNot(function (Builder $subQuery) use ($column, $driver) {
            $this->children[0]->toEloquent($subQuery, $column, $driver);
        });
    }

    /**
     * Applies all children to a sub-query with proper operator handling.
     *
     * @param  Builder  $subQuery  The Eloquent sub-query builder
     * @param  string  $column  The JSON column name
     * @param  DatabaseDriver  $driver  The database driver
     */
    private function applyChildrenToSubQuery(Builder $subQuery, string $column, DatabaseDriver $driver): void
    {
        $firstChild = true;

        foreach ($this->children as $child) {
            if ($firstChild) {
                $child->toEloquent($subQuery, $column, $driver);
                $firstChild = false;
            } else {
                $this->applySubsequentChild($subQuery, $child, $column, $driver);
            }
        }
    }

    /**
     * Applies a subsequent child with the appropriate operator.
     *
     * For OR operator, uses orWhere with a nested sub-query.
     * For AND operator, uses a simple where clause.
     *
     * @param  Builder  $subQuery  The Eloquent sub-query builder
     * @param  NodeInterface  $child  The child node to apply
     * @param  string  $column  The JSON column name
     * @param  DatabaseDriver  $driver  The database driver
     */
    private function applySubsequentChild(
        Builder $subQuery,
        NodeInterface $child,
        string $column,
        DatabaseDriver $driver
    ): void {
        if ($this->operator === LogicalOperator::OR) {
            $subQuery->orWhere(function (Builder $orSub) use ($child, $column, $driver) {
                $child->toEloquent($orSub, $column, $driver);
            });
        } else {
            $child->toEloquent($subQuery, $column, $driver);
        }
    }
}
