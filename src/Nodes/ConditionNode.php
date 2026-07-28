<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use Illuminate\Database\Eloquent\Builder;

final class ConditionNode extends Node implements NodeInterface
{
    public function __construct(
        private readonly string $key,
        private readonly ComparisonOperator $operator,
        private readonly ?string $value = null
    ) {}

    public function evaluate(StrictAssociative $data): bool
    {
        $dataArray = $data->toArray();

        if (! array_key_exists($this->key, $dataArray)) {
            return false;
        }

        $result = $this->operator->evaluate($dataArray[$this->key], $this->value);

        return is_bool($result) ? $result : false;
    }

    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        return match ($driver) {
            DatabaseDriver::MYSQL => $this->toMySql($column),
            DatabaseDriver::PGSQL => $this->toPostgreSql($column),
            DatabaseDriver::SQLITE => $this->toSqlite($column),
        };
    }

    public function toEloquent(Builder $query, string $column, DatabaseDriver $driver): void
    {
        match ($driver) {
            DatabaseDriver::MYSQL => $this->applyMySqlEloquent($query, $column),
            DatabaseDriver::PGSQL => $this->applyPostgreSqlEloquent($query, $column),
            DatabaseDriver::SQLITE => $this->applySqliteEloquent($query, $column),
        };
    }

    private function getJsonPath(): string
    {
        return '$."'.$this->key.'"';
    }

    private function getMySqlColumn(string $column): string
    {
        $path = $this->getJsonPath();
        $sqlColumn = "JSON_EXTRACT({$column}, '{$path}')";

        if ($this->operator->isNumeric()) {
            $sqlColumn = "CAST(JSON_EXTRACT({$column}, '{$path}') AS DECIMAL(10,2))";
        }

        return $sqlColumn;
    }

    private function getPostgreSqlColumn(string $column): string
    {
        if ($this->operator->isNumeric()) {
            return "({$column}->>'{$this->key}')::numeric";
        }

        return "{$column}->>'{$this->key}'";
    }

    private function getSqliteColumn(string $column): string
    {
        return "json_extract({$column}, '$.{$this->key}')";
    }

    private function toMySql(string $column): string
    {
        $path = $this->getJsonPath();
        $sqlColumn = $this->getMySqlColumn($column);

        if ($this->operator->isPresence()) {
            return $this->operator === ComparisonOperator::PRESENCE
                ? sprintf("JSON_EXTRACT({$column}, '{$path}') IS NOT NULL")
                : sprintf("JSON_EXTRACT({$column}, '{$path}') IS NULL");
        }

        return match ($this->operator) {
            ComparisonOperator::EQUAL,
            ComparisonOperator::EQUAL_LOOSE,
            ComparisonOperator::EQUAL_STRICT => sprintf("%s = '%s'", $sqlColumn, $this->value),
            ComparisonOperator::NOT_EQUAL,
            ComparisonOperator::NOT_EQUAL_STRICT => sprintf("%s != '%s'", $sqlColumn, $this->value),
            ComparisonOperator::LESS_THAN => sprintf('%s < %s', $sqlColumn, $this->value),
            ComparisonOperator::LESS_THAN_OR_EQUAL => sprintf('%s <= %s', $sqlColumn, $this->value),
            ComparisonOperator::GREATER_THAN => sprintf('%s > %s', $sqlColumn, $this->value),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => sprintf('%s >= %s', $sqlColumn, $this->value),
            ComparisonOperator::SPACESHIP => sprintf('%s <=> %s', $sqlColumn, $this->value),
            default => '1=1',
        };
    }

    private function toPostgreSql(string $column): string
    {
        $sqlColumn = $this->getPostgreSqlColumn($column);

        if ($this->operator->isPresence()) {
            return $this->operator === ComparisonOperator::PRESENCE
                ? sprintf("{$column}->'%s' IS NOT NULL", $this->key)
                : sprintf("{$column}->'%s' IS NULL", $this->key);
        }

        return match ($this->operator) {
            ComparisonOperator::EQUAL,
            ComparisonOperator::EQUAL_LOOSE,
            ComparisonOperator::EQUAL_STRICT => sprintf("%s = '%s'", $sqlColumn, $this->value),
            ComparisonOperator::NOT_EQUAL,
            ComparisonOperator::NOT_EQUAL_STRICT => sprintf("%s != '%s'", $sqlColumn, $this->value),
            ComparisonOperator::LESS_THAN => sprintf('%s < %s', $sqlColumn, $this->value),
            ComparisonOperator::LESS_THAN_OR_EQUAL => sprintf('%s <= %s', $sqlColumn, $this->value),
            ComparisonOperator::GREATER_THAN => sprintf('%s > %s', $sqlColumn, $this->value),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => sprintf('%s >= %s', $sqlColumn, $this->value),
            ComparisonOperator::SPACESHIP => sprintf('%s <=> %s', $sqlColumn, $this->value),
            default => '1=1',
        };
    }

    private function toSqlite(string $column): string
    {
        $sqlColumn = $this->getSqliteColumn($column);

        if ($this->operator->isPresence()) {
            return $this->operator === ComparisonOperator::PRESENCE
                ? sprintf("json_extract({$column}, '$.%s') IS NOT NULL", $this->key)
                : sprintf("json_extract({$column}, '$.%s') IS NULL", $this->key);
        }

        return match ($this->operator) {
            ComparisonOperator::EQUAL,
            ComparisonOperator::EQUAL_LOOSE,
            ComparisonOperator::EQUAL_STRICT => sprintf("%s = '%s'", $sqlColumn, $this->value),
            ComparisonOperator::NOT_EQUAL,
            ComparisonOperator::NOT_EQUAL_STRICT => sprintf("%s != '%s'", $sqlColumn, $this->value),
            ComparisonOperator::LESS_THAN => sprintf('%s < %s', $sqlColumn, $this->value),
            ComparisonOperator::LESS_THAN_OR_EQUAL => sprintf('%s <= %s', $sqlColumn, $this->value),
            ComparisonOperator::GREATER_THAN => sprintf('%s > %s', $sqlColumn, $this->value),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => sprintf('%s >= %s', $sqlColumn, $this->value),
            ComparisonOperator::SPACESHIP => sprintf('%s <=> %s', $sqlColumn, $this->value),
            default => '1=1',
        };
    }

    private function applyMySqlEloquent(Builder $query, string $column): void
    {
        $path = $this->getJsonPath();

        if ($this->operator->isPresence()) {
            $query->whereRaw(
                $this->operator === ComparisonOperator::PRESENCE
                    ? "JSON_EXTRACT({$column}, '{$path}') IS NOT NULL"
                    : "JSON_EXTRACT({$column}, '{$path}') IS NULL"
            );

            return;
        }

        $sqlColumn = $this->getMySqlColumn($column);

        match ($this->operator) {
            ComparisonOperator::EQUAL,
            ComparisonOperator::EQUAL_LOOSE,
            ComparisonOperator::EQUAL_STRICT => $query->whereRaw("{$sqlColumn} = ?", [$this->value]),
            ComparisonOperator::NOT_EQUAL,
            ComparisonOperator::NOT_EQUAL_STRICT => $query->whereRaw("{$sqlColumn} != ?", [$this->value]),
            ComparisonOperator::LESS_THAN => $query->whereRaw("{$sqlColumn} < ?", [$this->value]),
            ComparisonOperator::LESS_THAN_OR_EQUAL => $query->whereRaw("{$sqlColumn} <= ?", [$this->value]),
            ComparisonOperator::GREATER_THAN => $query->whereRaw("{$sqlColumn} > ?", [$this->value]),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => $query->whereRaw("{$sqlColumn} >= ?", [$this->value]),
            ComparisonOperator::SPACESHIP => $query->whereRaw("{$sqlColumn} <=> ?", [$this->value]),
            default => null,
        };
    }

    private function applyPostgreSqlEloquent(Builder $query, string $column): void
    {
        if ($this->operator->isPresence()) {
            $query->whereRaw(
                $this->operator === ComparisonOperator::PRESENCE
                    ? "{$column}->'{$this->key}' IS NOT NULL"
                    : "{$column}->'{$this->key}' IS NULL"
            );

            return;
        }

        $sqlColumn = $this->getPostgreSqlColumn($column);

        match ($this->operator) {
            ComparisonOperator::EQUAL,
            ComparisonOperator::EQUAL_LOOSE,
            ComparisonOperator::EQUAL_STRICT => $query->whereRaw("{$sqlColumn} = ?", [$this->value]),
            ComparisonOperator::NOT_EQUAL,
            ComparisonOperator::NOT_EQUAL_STRICT => $query->whereRaw("{$sqlColumn} != ?", [$this->value]),
            ComparisonOperator::LESS_THAN => $query->whereRaw("{$sqlColumn} < ?", [$this->value]),
            ComparisonOperator::LESS_THAN_OR_EQUAL => $query->whereRaw("{$sqlColumn} <= ?", [$this->value]),
            ComparisonOperator::GREATER_THAN => $query->whereRaw("{$sqlColumn} > ?", [$this->value]),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => $query->whereRaw("{$sqlColumn} >= ?", [$this->value]),
            ComparisonOperator::SPACESHIP => $query->whereRaw("{$sqlColumn} <=> ?", [$this->value]),
            default => null,
        };
    }

    private function applySqliteEloquent(Builder $query, string $column): void
    {
        if ($this->operator->isPresence()) {
            $query->whereRaw(
                $this->operator === ComparisonOperator::PRESENCE
                    ? "json_extract({$column}, '$.{$this->key}') IS NOT NULL"
                    : "json_extract({$column}, '$.{$this->key}') IS NULL"
            );

            return;
        }

        $sqlColumn = $this->getSqliteColumn($column);

        match ($this->operator) {
            ComparisonOperator::EQUAL,
            ComparisonOperator::EQUAL_LOOSE,
            ComparisonOperator::EQUAL_STRICT => $query->whereRaw("{$sqlColumn} = ?", [$this->value]),
            ComparisonOperator::NOT_EQUAL,
            ComparisonOperator::NOT_EQUAL_STRICT => $query->whereRaw("{$sqlColumn} != ?", [$this->value]),
            ComparisonOperator::LESS_THAN => $query->whereRaw("{$sqlColumn} < ?", [$this->value]),
            ComparisonOperator::LESS_THAN_OR_EQUAL => $query->whereRaw("{$sqlColumn} <= ?", [$this->value]),
            ComparisonOperator::GREATER_THAN => $query->whereRaw("{$sqlColumn} > ?", [$this->value]),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => $query->whereRaw("{$sqlColumn} >= ?", [$this->value]),
            ComparisonOperator::SPACESHIP => $query->whereRaw("{$sqlColumn} <=> ?", [$this->value]),
            default => null,
        };
    }
}
