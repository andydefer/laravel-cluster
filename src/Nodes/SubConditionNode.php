<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

final class SubConditionNode extends Node
{
    private readonly string $parentKey;

    private readonly NodeInterface $condition;

    private readonly array $path;

    public function __construct(string $parentKey, NodeInterface $condition, array $path = [])
    {
        $this->parentKey = $parentKey;
        $this->condition = $condition;
        $this->path = $path;
    }

    public function evaluate(ClusterVO $data): bool
    {
        $nestedData = $data->getNestedData();

        if (! isset($nestedData[$this->parentKey]) || ! is_array($nestedData[$this->parentKey])) {
            return $this->evaluateEmpty();
        }

        $items = $nestedData[$this->parentKey];

        if ($this->isExistsCondition()) {
            return ! empty($items);
        }

        if ($this->isNotExistsCondition()) {
            return empty($items);
        }

        if (! empty($this->path)) {
            return $this->evaluateWithPath($items);
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $subCluster = new ClusterVO($item);
            if ($this->condition->evaluate($subCluster)) {
                return true;
            }
        }

        return false;
    }

    private function evaluateWithPath(array $items): bool
    {
        return $this->traversePath($items, $this->path, 0);
    }

    private function traversePath(array $items, array $path, int $depth): bool
    {
        if ($depth >= count($path)) {
            if (! is_array($items)) {
                return false;
            }
            $subCluster = new ClusterVO($items);

            return $this->condition->evaluate($subCluster);
        }

        $currentPath = $path[$depth];

        if ($currentPath === '*') {
            foreach ($items as $item) {
                if (is_array($item)) {
                    if ($this->traversePath($item, $path, $depth + 1)) {
                        return true;
                    }
                }
            }

            return false;
        }

        $index = is_numeric($currentPath) ? (int) $currentPath : $currentPath;

        if (! isset($items[$index])) {
            return false;
        }

        $item = $items[$index];

        if ($depth + 1 >= count($path)) {
            if (! is_array($item)) {
                return false;
            }
            $subCluster = new ClusterVO($item);

            return $this->condition->evaluate($subCluster);
        }

        if (is_array($item)) {
            return $this->traversePath($item, $path, $depth + 1);
        }

        return false;
    }

    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        return '';
    }

    public function toEloquent(Builder $query, string $column, DatabaseDriver $driver): void
    {
        // Pas d'implémentation SQL pour l'instant
    }

    public function getChildren(): array
    {
        return [$this->condition];
    }

    private function evaluateEmpty(): bool
    {
        if ($this->isExistsCondition()) {
            return false;
        }

        if ($this->isNotExistsCondition()) {
            return true;
        }

        return false;
    }

    private function isExistsCondition(): bool
    {
        if ($this->condition instanceof ConditionNode) {
            $reflection = new \ReflectionClass($this->condition);
            $operatorProperty = $reflection->getProperty('operator');
            $operatorProperty->setAccessible(true);
            $operator = $operatorProperty->getValue($this->condition);

            return $operator->isExistence() && $operator->value === '*';
        }

        return false;
    }

    private function isNotExistsCondition(): bool
    {
        if ($this->condition instanceof ConditionNode) {
            $reflection = new \ReflectionClass($this->condition);
            $operatorProperty = $reflection->getProperty('operator');
            $operatorProperty->setAccessible(true);
            $operator = $operatorProperty->getValue($this->condition);

            return $operator->isExistence() && $operator->value === '#';
        }

        return false;
    }
}
