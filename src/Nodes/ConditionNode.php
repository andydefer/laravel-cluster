<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

final class ConditionNode extends Node
{
    public function __construct(
        private readonly string $key,
        private readonly ComparisonOperator $operator,
        private readonly ?string $value = null
    ) {}

    public function evaluate(ClusterVO $data): bool
    {
        $dataArray = $data->toArray();

        if (! array_key_exists($this->key, $dataArray)) {
            if ($this->operator === ComparisonOperator::ABSENCE) {
                return true;
            }

            return false;
        }

        return (bool) $this->operator->evaluate($dataArray[$this->key], $this->value);
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
        // Pour les comparaisons numériques, on CAST en INTEGER
        if ($this->operator->isNumeric()) {
            return "CAST(json_extract({$column}, '$.{$this->key}') AS INTEGER)";
        }

        return "json_extract({$column}, '$.{$this->key}')";
    }

    private function getComparisonSql(string $sqlColumn): string
    {
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

    private function toMySql(string $column): string
    {
        $path = $this->getJsonPath();

        if ($this->operator->isPresence()) {
            return $this->operator === ComparisonOperator::PRESENCE
                ? sprintf("JSON_EXTRACT({$column}, '{$path}') IS NOT NULL")
                : sprintf("JSON_EXTRACT({$column}, '{$path}') IS NULL");
        }

        $sqlColumn = $this->getMySqlColumn($column);

        return $this->getComparisonSql($sqlColumn);
    }

    private function toPostgreSql(string $column): string
    {
        if ($this->operator->isPresence()) {
            return $this->operator === ComparisonOperator::PRESENCE
                ? sprintf("{$column}->'%s' IS NOT NULL", $this->key)
                : sprintf("{$column}->'%s' IS NULL", $this->key);
        }

        $sqlColumn = $this->getPostgreSqlColumn($column);

        return $this->getComparisonSql($sqlColumn);
    }

    private function toSqlite(string $column): string
    {
        if ($this->operator->isPresence()) {
            return $this->operator === ComparisonOperator::PRESENCE
                ? sprintf("json_extract({$column}, '$.%s') IS NOT NULL", $this->key)
                : sprintf("json_extract({$column}, '$.%s') IS NULL", $this->key);
        }

        $sqlColumn = $this->getSqliteColumn($column);

        return $this->getComparisonSql($sqlColumn);
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
        $this->applyComparisonEloquent($query, $sqlColumn);
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
        $this->applyComparisonEloquent($query, $sqlColumn);
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
        $this->applyComparisonEloquent($query, $sqlColumn);
    }

    private function applyComparisonEloquent(Builder $query, string $sqlColumn): void
    {
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
