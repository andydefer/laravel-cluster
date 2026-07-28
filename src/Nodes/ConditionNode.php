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
        $keyExists = array_key_exists($this->key, $dataArray);

        return match (true) {
            // Cas où la clé n'existe pas
            ! $keyExists && $this->operator === ComparisonOperator::EXISTS => false,
            ! $keyExists && $this->operator === ComparisonOperator::NOT_EXISTS => true,
            ! $keyExists && $this->operator === ComparisonOperator::EQUAL && ($this->value === 'false' || $this->value === 'null') => true,
            ! $keyExists => false,

            // Cas où la clé existe
            $keyExists && $this->operator === ComparisonOperator::EXISTS => true,
            $keyExists && $this->operator === ComparisonOperator::NOT_EXISTS => false,

            // Cas général
            default => (bool) $this->operator->evaluate($dataArray[$this->key], $this->value)
        };
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

    private function convertToLikePattern(?string $value): string
    {
        if ($value === null) {
            return '%';
        }

        if (str_contains($value, '%')) {
            return $value;
        }

        return '%'.$value.'%';
    }

    private function toMySql(string $column): string
    {
        $path = $this->getJsonPath();

        return match ($this->operator) {
            ComparisonOperator::EXISTS => sprintf("JSON_EXTRACT({$column}, '{$path}') IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => sprintf("JSON_EXTRACT({$column}, '{$path}') IS NULL"),
            ComparisonOperator::LIKE => sprintf("JSON_EXTRACT({$column}, '{$path}') LIKE '%s'", $this->convertToLikePattern($this->value)),
            ComparisonOperator::NOT_LIKE => sprintf("JSON_EXTRACT({$column}, '{$path}') NOT LIKE '%s'", $this->convertToLikePattern($this->value)),
            ComparisonOperator::EQUAL => sprintf("JSON_EXTRACT({$column}, '{$path}') = '%s'", $this->value),
            ComparisonOperator::NOT_EQUAL => sprintf("JSON_EXTRACT({$column}, '{$path}') != '%s'", $this->value),
            default => $this->getComparisonSql($this->getMySqlColumn($column)),
        };
    }

    private function toPostgreSql(string $column): string
    {
        return match ($this->operator) {
            ComparisonOperator::EXISTS => sprintf("{$column}->'%s' IS NOT NULL", $this->key),
            ComparisonOperator::NOT_EXISTS => sprintf("{$column}->'%s' IS NULL", $this->key),
            ComparisonOperator::LIKE => sprintf("{$column}->>'%s' LIKE '%s'", $this->key, $this->convertToLikePattern($this->value)),
            ComparisonOperator::NOT_LIKE => sprintf("{$column}->>'%s' NOT LIKE '%s'", $this->key, $this->convertToLikePattern($this->value)),
            ComparisonOperator::EQUAL => sprintf("{$column}->>'%s' = '%s'", $this->key, $this->value),
            ComparisonOperator::NOT_EQUAL => sprintf("{$column}->>'%s' != '%s'", $this->key, $this->value),
            default => $this->getComparisonSql($this->getPostgreSqlColumn($column)),
        };
    }

    private function toSqlite(string $column): string
    {
        return match ($this->operator) {
            ComparisonOperator::EXISTS => sprintf("json_extract({$column}, '$.%s') IS NOT NULL", $this->key),
            ComparisonOperator::NOT_EXISTS => sprintf("json_extract({$column}, '$.%s') IS NULL", $this->key),
            ComparisonOperator::LIKE => sprintf("json_extract({$column}, '$.%s') LIKE '%s'", $this->key, $this->convertToLikePattern($this->value)),
            ComparisonOperator::NOT_LIKE => sprintf("json_extract({$column}, '$.%s') NOT LIKE '%s'", $this->key, $this->convertToLikePattern($this->value)),
            ComparisonOperator::EQUAL => sprintf("json_extract({$column}, '$.%s') = '%s'", $this->key, $this->value),
            ComparisonOperator::NOT_EQUAL => sprintf("json_extract({$column}, '$.%s') != '%s'", $this->key, $this->value),
            default => $this->getComparisonSql($this->getSqliteColumn($column)),
        };
    }

    private function applyMySqlEloquent(Builder $query, string $column): void
    {
        $path = $this->getJsonPath();

        match ($this->operator) {
            ComparisonOperator::EXISTS => $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') IS NULL"),
            ComparisonOperator::LIKE => $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') LIKE ?", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::NOT_LIKE => $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') NOT LIKE ?", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::EQUAL => $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') = ?", [$this->value]),
            ComparisonOperator::NOT_EQUAL => $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') != ?", [$this->value]),
            default => $this->applyComparisonEloquent($query, $this->getMySqlColumn($column)),
        };
    }

    private function applyPostgreSqlEloquent(Builder $query, string $column): void
    {
        match ($this->operator) {
            ComparisonOperator::EXISTS => $query->whereRaw("{$column}->'{$this->key}' IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => $query->whereRaw("{$column}->'{$this->key}' IS NULL"),
            ComparisonOperator::LIKE => $query->whereRaw("{$column}->>'{$this->key}' LIKE ?", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::NOT_LIKE => $query->whereRaw("{$column}->>'{$this->key}' NOT LIKE ?", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::EQUAL => $query->whereRaw("{$column}->>'{$this->key}' = ?", [$this->value]),
            ComparisonOperator::NOT_EQUAL => $query->whereRaw("{$column}->>'{$this->key}' != ?", [$this->value]),
            default => $this->applyComparisonEloquent($query, $this->getPostgreSqlColumn($column)),
        };
    }

    private function applySqliteEloquent(Builder $query, string $column): void
    {
        match ($this->operator) {
            ComparisonOperator::EXISTS => $query->whereRaw("json_extract({$column}, '$.{$this->key}') IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => $query->whereRaw("json_extract({$column}, '$.{$this->key}') IS NULL"),
            ComparisonOperator::LIKE => $query->whereRaw("json_extract({$column}, '$.{$this->key}') LIKE ?", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::NOT_LIKE => $query->whereRaw("json_extract({$column}, '$.{$this->key}') NOT LIKE ?", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::EQUAL => $query->whereRaw("json_extract({$column}, '$.{$this->key}') = ?", [$this->value]),
            ComparisonOperator::NOT_EQUAL => $query->whereRaw("json_extract({$column}, '$.{$this->key}') != ?", [$this->value]),
            default => $this->applyComparisonEloquent($query, $this->getSqliteColumn($column)),
        };
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
