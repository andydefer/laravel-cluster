<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

final class GroupNode extends Node
{
    /**
     * @var array<int, NodeInterface>
     */
    private array $children = [];

    public function __construct(
        private readonly LogicalOperator $operator,
        NodeInterface ...$children
    ) {
        $this->children = $children;
    }

    public function evaluate(ClusterVO $data): bool
    {
        if (empty($this->children)) {
            return true;
        }

        $result = $this->children[0]->evaluate($data);

        for ($i = 1; $i < count($this->children); $i++) {
            $result = $this->operator->evaluate($result, $this->children[$i]->evaluate($data));
        }

        return $result;
    }

    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        $parts = array_map(
            fn (NodeInterface $child) => $child->toSql($column, $driver),
            $this->children
        );

        $glue = $this->operator->getSqlGlue();

        return count($parts) > 1 ? '('.implode($glue, $parts).')' : $parts[0];
    }

    public function toEloquent(Builder $query, string $column, DatabaseDriver $driver): void
    {
        if (empty($this->children)) {
            return;
        }

        $method = $this->operator->getEloquentMethod();

        $query->$method(function (Builder $subQuery) use ($column, $driver) {
            $first = true;
            foreach ($this->children as $child) {
                if ($first) {
                    $child->toEloquent($subQuery, $column, $driver);
                    $first = false;
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
     * @return array<int, NodeInterface>
     */
    public function getChildren(): array
    {
        return $this->children;
    }
}
