<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

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
            if ($this->operator === ComparisonOperator::EXISTS) {
                return false;
            }
            if ($this->operator === ComparisonOperator::NOT_EXISTS) {
                return true;
            }
            if ($this->operator === ComparisonOperator::EQUAL && ($this->value === 'false' || $this->value === 'null')) {
                return true;
            }

            return false;
        }

        if ($this->operator === ComparisonOperator::EXISTS) {
            return true;
        }
        if ($this->operator === ComparisonOperator::NOT_EXISTS) {
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

    public function getChildren(): array
    {
        return [];
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
            default => throw new InvalidArgumentException("Unsupported operator: {$this->operator->name}"),
        };
    }

    private function toMySql(string $column): string
    {
        $path = $this->getJsonPath();

        if ($this->operator === ComparisonOperator::EXISTS) {
            return sprintf("JSON_EXTRACT({$column}, '{$path}') IS NOT NULL");
        }

        if ($this->operator === ComparisonOperator::NOT_EXISTS) {
            return sprintf("JSON_EXTRACT({$column}, '{$path}') IS NULL");
        }

        if ($this->operator === ComparisonOperator::EQUAL) {
            return sprintf("JSON_EXTRACT({$column}, '{$path}') = '%s'", $this->value);
        }

        if ($this->operator === ComparisonOperator::NOT_EQUAL) {
            return sprintf("JSON_EXTRACT({$column}, '{$path}') != '%s'", $this->value);
        }

        $sqlColumn = $this->getMySqlColumn($column);

        return $this->getComparisonSql($sqlColumn);
    }

    private function toPostgreSql(string $column): string
    {
        if ($this->operator === ComparisonOperator::EXISTS) {
            return sprintf("{$column}->'%s' IS NOT NULL", $this->key);
        }

        if ($this->operator === ComparisonOperator::NOT_EXISTS) {
            return sprintf("{$column}->'%s' IS NULL", $this->key);
        }

        if ($this->operator === ComparisonOperator::EQUAL) {
            return sprintf("{$column}->>'%s' = '%s'", $this->key, $this->value);
        }

        if ($this->operator === ComparisonOperator::NOT_EQUAL) {
            return sprintf("{$column}->>'%s' != '%s'", $this->key, $this->value);
        }

        $sqlColumn = $this->getPostgreSqlColumn($column);

        return $this->getComparisonSql($sqlColumn);
    }

    private function toSqlite(string $column): string
    {
        if ($this->operator === ComparisonOperator::EXISTS) {
            return sprintf("json_extract({$column}, '$.%s') IS NOT NULL", $this->key);
        }

        if ($this->operator === ComparisonOperator::NOT_EXISTS) {
            return sprintf("json_extract({$column}, '$.%s') IS NULL", $this->key);
        }

        if ($this->operator === ComparisonOperator::EQUAL) {
            return sprintf("json_extract({$column}, '$.%s') = '%s'", $this->key, $this->value);
        }

        if ($this->operator === ComparisonOperator::NOT_EQUAL) {
            return sprintf("json_extract({$column}, '$.%s') != '%s'", $this->key, $this->value);
        }

        $sqlColumn = $this->getSqliteColumn($column);

        return $this->getComparisonSql($sqlColumn);
    }

    private function applyMySqlEloquent(Builder $query, string $column): void
    {
        $path = $this->getJsonPath();

        if ($this->operator === ComparisonOperator::EXISTS) {
            $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') IS NOT NULL");

            return;
        }

        if ($this->operator === ComparisonOperator::NOT_EXISTS) {
            $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') IS NULL");

            return;
        }

        if ($this->operator === ComparisonOperator::EQUAL) {
            $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') = ?", [$this->value]);

            return;
        }

        if ($this->operator === ComparisonOperator::NOT_EQUAL) {
            $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') != ?", [$this->value]);

            return;
        }

        $sqlColumn = $this->getMySqlColumn($column);
        $this->applyComparisonEloquent($query, $sqlColumn);
    }

    private function applyPostgreSqlEloquent(Builder $query, string $column): void
    {
        if ($this->operator === ComparisonOperator::EXISTS) {
            $query->whereRaw("{$column}->'{$this->key}' IS NOT NULL");

            return;
        }

        if ($this->operator === ComparisonOperator::NOT_EXISTS) {
            $query->whereRaw("{$column}->'{$this->key}' IS NULL");

            return;
        }

        if ($this->operator === ComparisonOperator::EQUAL) {
            $query->whereRaw("{$column}->>'{$this->key}' = ?", [$this->value]);

            return;
        }

        if ($this->operator === ComparisonOperator::NOT_EQUAL) {
            $query->whereRaw("{$column}->>'{$this->key}' != ?", [$this->value]);

            return;
        }

        $sqlColumn = $this->getPostgreSqlColumn($column);
        $this->applyComparisonEloquent($query, $sqlColumn);
    }

    private function applySqliteEloquent(Builder $query, string $column): void
    {
        if ($this->operator === ComparisonOperator::EXISTS) {
            $query->whereRaw("json_extract({$column}, '$.{$this->key}') IS NOT NULL");

            return;
        }

        if ($this->operator === ComparisonOperator::NOT_EXISTS) {
            $query->whereRaw("json_extract({$column}, '$.{$this->key}') IS NULL");

            return;
        }

        if ($this->operator === ComparisonOperator::EQUAL) {
            $query->whereRaw("json_extract({$column}, '$.{$this->key}') = ?", [$this->value]);

            return;
        }

        if ($this->operator === ComparisonOperator::NOT_EQUAL) {
            $query->whereRaw("json_extract({$column}, '$.{$this->key}') != ?", [$this->value]);

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
            default => throw new InvalidArgumentException("Unsupported operator: {$this->operator->name}"),
        };
    }
}
