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

    public function getOperator(): ComparisonOperator
    {
        return $this->operator;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function isEmptyCondition(): bool
    {
        return $this->key === '__empty__' && $this->operator === ComparisonOperator::EQUAL;
    }

    public function isWildcardExists(): bool
    {
        return $this->key === '*' && $this->operator === ComparisonOperator::EXISTS;
    }

    public function evaluate(ClusterVO $data): bool
    {
        $dataArray = $data->toArray();
        $keyExists = array_key_exists($this->key, $dataArray);

        if ($this->operator === ComparisonOperator::EXISTS) {
            return $keyExists;
        }

        if ($this->operator === ComparisonOperator::NOT_EXISTS) {
            return ! $keyExists;
        }

        if (! $keyExists) {
            return $this->evaluateMissingKey();
        }

        return (bool) $this->operator->evaluate($dataArray[$this->key], $this->value);
    }

    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        return match ($driver) {
            DatabaseDriver::MYSQL => $this->buildMySqlCondition($column),
            DatabaseDriver::PGSQL => $this->buildPostgreSqlCondition($column),
            DatabaseDriver::SQLITE => $this->buildSqliteCondition($column),
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

    private function evaluateMissingKey(): bool
    {
        return match ($this->operator) {
            ComparisonOperator::EXISTS => false,
            ComparisonOperator::NOT_EXISTS => true,
            ComparisonOperator::EQUAL => $this->value === 'false' || $this->value === 'null',
            default => false,
        };
    }

    private function getJsonPath(): string
    {
        if (! preg_match('/^[a-zA-Z0-9_\-*]+$/', $this->key)) {
            throw new InvalidArgumentException("Invalid JSON key: {$this->key}");
        }

        return '$."'.$this->key.'"';
    }

    private function buildMySqlJsonExpression(string $column): string
    {
        $path = $this->getJsonPath();

        if ($this->operator->isNumeric()) {
            return "CAST(JSON_EXTRACT({$column}, '{$path}') AS DECIMAL(10,2))";
        }

        return "JSON_EXTRACT({$column}, '{$path}')";
    }

    private function buildPostgreSqlJsonExpression(string $column): string
    {
        if ($this->operator->isNumeric()) {
            return "({$column}->>'{$this->key}')::numeric";
        }

        return "{$column}->>'{$this->key}'";
    }

    private function buildSqliteJsonExpression(string $column): string
    {
        if ($this->operator->isNumeric()) {
            return "CAST(json_extract({$column}, '$.{$this->key}') AS INTEGER)";
        }

        return "json_extract({$column}, '$.{$this->key}')";
    }

    private function buildComparisonSql(string $sqlColumn): string
    {
        $escapedValue = $this->escapeSqlString($this->value ?? '');

        return match ($this->operator) {
            ComparisonOperator::EQUAL,
            ComparisonOperator::EQUAL_LOOSE,
            ComparisonOperator::EQUAL_STRICT => sprintf("%s = '%s'", $sqlColumn, $escapedValue),
            ComparisonOperator::NOT_EQUAL,
            ComparisonOperator::NOT_EQUAL_STRICT => sprintf("%s != '%s'", $sqlColumn, $escapedValue),
            ComparisonOperator::LESS_THAN => sprintf('%s < %s', $sqlColumn, $escapedValue),
            ComparisonOperator::LESS_THAN_OR_EQUAL => sprintf('%s <= %s', $sqlColumn, $escapedValue),
            ComparisonOperator::GREATER_THAN => sprintf('%s > %s', $sqlColumn, $escapedValue),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => sprintf('%s >= %s', $sqlColumn, $escapedValue),
            ComparisonOperator::SPACESHIP => sprintf('%s <=> %s', $sqlColumn, $escapedValue),
            default => throw new InvalidArgumentException("Unsupported operator: {$this->operator->name}"),
        };
    }

    private function escapeSqlString(string $value): string
    {
        return addslashes($value);
    }

    private function escapeLikePattern(string $pattern): string
    {
        $search = ['%', '_', '\\'];
        $replace = ['\\%', '\\_', '\\\\'];

        return str_replace($search, $replace, $pattern);
    }

    private function convertToLikePattern(?string $value): string
    {
        if ($value === null) {
            return '%';
        }

        if (str_contains($value, '%')) {
            return $value;
        }

        return '%'.$this->escapeLikePattern($value).'%';
    }

    // ==================== BUILD SQLITE ====================

    private function buildSqliteCondition(string $column): string
    {
        return match ($this->operator) {
            ComparisonOperator::EXISTS => sprintf("json_extract({$column}, '$.%s') IS NOT NULL", $this->key),
            ComparisonOperator::NOT_EXISTS => sprintf("json_extract({$column}, '$.%s') IS NULL", $this->key),
            ComparisonOperator::LIKE => sprintf("LOWER(json_extract({$column}, '$.%s')) LIKE LOWER('%s')", $this->key, $this->convertToLikePattern($this->value)),
            ComparisonOperator::NOT_LIKE => sprintf("LOWER(json_extract({$column}, '$.%s')) NOT LIKE LOWER('%s')", $this->key, $this->convertToLikePattern($this->value)),
            ComparisonOperator::EQUAL => sprintf("LOWER(json_extract({$column}, '$.%s')) = LOWER('%s')", $this->key, $this->escapeSqlString($this->value ?? '')),
            ComparisonOperator::NOT_EQUAL => sprintf("LOWER(json_extract({$column}, '$.%s')) != LOWER('%s')", $this->key, $this->escapeSqlString($this->value ?? '')),
            ComparisonOperator::LESS_THAN => sprintf("CAST(json_extract({$column}, '$.%s') AS NUMERIC) < %s", $this->key, $this->value),
            ComparisonOperator::LESS_THAN_OR_EQUAL => sprintf("CAST(json_extract({$column}, '$.%s') AS NUMERIC) <= %s", $this->key, $this->value),
            ComparisonOperator::GREATER_THAN => sprintf("CAST(json_extract({$column}, '$.%s') AS NUMERIC) > %s", $this->key, $this->value),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => sprintf("CAST(json_extract({$column}, '$.%s') AS NUMERIC) >= %s", $this->key, $this->value),
            ComparisonOperator::SPACESHIP => $this->buildComparisonSql($this->buildSqliteJsonExpression($column)),
            default => $this->buildComparisonSql($this->buildSqliteJsonExpression($column)),
        };
    }

    private function applySqliteEloquent(Builder $query, string $column): void
    {
        $escapedValue = $this->escapeSqlString($this->value ?? '');

        match ($this->operator) {
            ComparisonOperator::EXISTS => $query->whereRaw("json_extract({$column}, '$.{$this->key}') IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => $query->whereRaw("json_extract({$column}, '$.{$this->key}') IS NULL"),
            ComparisonOperator::LIKE => $query->whereRaw("LOWER(json_extract({$column}, '$.{$this->key}')) LIKE LOWER(?)", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::NOT_LIKE => $query->whereRaw("LOWER(json_extract({$column}, '$.{$this->key}')) NOT LIKE LOWER(?)", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::EQUAL => $query->whereRaw("LOWER(json_extract({$column}, '$.{$this->key}')) = LOWER(?)", [$this->value]),
            ComparisonOperator::NOT_EQUAL => $query->whereRaw("LOWER(json_extract({$column}, '$.{$this->key}')) != LOWER(?)", [$this->value]),
            ComparisonOperator::LESS_THAN => $query->whereRaw("CAST(json_extract({$column}, '$.{$this->key}') AS NUMERIC) < ?", [$this->value]),
            ComparisonOperator::LESS_THAN_OR_EQUAL => $query->whereRaw("CAST(json_extract({$column}, '$.{$this->key}') AS NUMERIC) <= ?", [$this->value]),
            ComparisonOperator::GREATER_THAN => $query->whereRaw("CAST(json_extract({$column}, '$.{$this->key}') AS NUMERIC) > ?", [$this->value]),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => $query->whereRaw("CAST(json_extract({$column}, '$.{$this->key}') AS NUMERIC) >= ?", [$this->value]),
            default => $this->applyComparisonEloquent($query, $this->buildSqliteJsonExpression($column)),
        };
    }

    // ==================== BUILD MYSQL ====================

    private function buildMySqlCondition(string $column): string
    {
        $path = $this->getJsonPath();

        return match ($this->operator) {
            ComparisonOperator::EXISTS => sprintf("JSON_EXTRACT({$column}, '{$path}') IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => sprintf("JSON_EXTRACT({$column}, '{$path}') IS NULL"),
            ComparisonOperator::LIKE => sprintf("LOWER(JSON_EXTRACT({$column}, '{$path}')) LIKE LOWER('%s')", $this->convertToLikePattern($this->value)),
            ComparisonOperator::NOT_LIKE => sprintf("LOWER(JSON_EXTRACT({$column}, '{$path}')) NOT LIKE LOWER('%s')", $this->convertToLikePattern($this->value)),
            ComparisonOperator::EQUAL => sprintf("LOWER(JSON_EXTRACT({$column}, '{$path}')) = LOWER('%s')", $this->escapeSqlString($this->value ?? '')),
            ComparisonOperator::NOT_EQUAL => sprintf("LOWER(JSON_EXTRACT({$column}, '{$path}')) != LOWER('%s')", $this->escapeSqlString($this->value ?? '')),
            default => $this->buildComparisonSql($this->buildMySqlJsonExpression($column)),
        };
    }

    private function applyMySqlEloquent(Builder $query, string $column): void
    {
        $path = $this->getJsonPath();

        match ($this->operator) {
            ComparisonOperator::EXISTS => $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') IS NULL"),
            ComparisonOperator::LIKE => $query->whereRaw("LOWER(JSON_EXTRACT({$column}, '{$path}')) LIKE LOWER(?)", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::NOT_LIKE => $query->whereRaw("LOWER(JSON_EXTRACT({$column}, '{$path}')) NOT LIKE LOWER(?)", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::EQUAL => $query->whereRaw("LOWER(JSON_EXTRACT({$column}, '{$path}')) = LOWER(?)", [$this->value]),
            ComparisonOperator::NOT_EQUAL => $query->whereRaw("LOWER(JSON_EXTRACT({$column}, '{$path}')) != LOWER(?)", [$this->value]),
            default => $this->applyComparisonEloquent($query, $this->buildMySqlJsonExpression($column)),
        };
    }

    // ==================== BUILD POSTGRESQL ====================

    private function buildPostgreSqlCondition(string $column): string
    {
        return match ($this->operator) {
            ComparisonOperator::EXISTS => sprintf("{$column}->'%s' IS NOT NULL", $this->key),
            ComparisonOperator::NOT_EXISTS => sprintf("{$column}->'%s' IS NULL", $this->key),
            ComparisonOperator::LIKE => sprintf("LOWER({$column}->>'%s') LIKE LOWER('%s')", $this->key, $this->convertToLikePattern($this->value)),
            ComparisonOperator::NOT_LIKE => sprintf("LOWER({$column}->>'%s') NOT LIKE LOWER('%s')", $this->key, $this->convertToLikePattern($this->value)),
            ComparisonOperator::EQUAL => sprintf("LOWER({$column}->>'%s') = LOWER('%s')", $this->key, $this->escapeSqlString($this->value ?? '')),
            ComparisonOperator::NOT_EQUAL => sprintf("LOWER({$column}->>'%s') != LOWER('%s')", $this->key, $this->escapeSqlString($this->value ?? '')),
            default => $this->buildComparisonSql($this->buildPostgreSqlJsonExpression($column)),
        };
    }

    private function applyPostgreSqlEloquent(Builder $query, string $column): void
    {
        match ($this->operator) {
            ComparisonOperator::EXISTS => $query->whereRaw("{$column}->'{$this->key}' IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => $query->whereRaw("{$column}->'{$this->key}' IS NULL"),
            ComparisonOperator::LIKE => $query->whereRaw("LOWER({$column}->>'{$this->key}') LIKE LOWER(?)", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::NOT_LIKE => $query->whereRaw("LOWER({$column}->>'{$this->key}') NOT LIKE LOWER(?)", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::EQUAL => $query->whereRaw("LOWER({$column}->>'{$this->key}') = LOWER(?)", [$this->value]),
            ComparisonOperator::NOT_EQUAL => $query->whereRaw("LOWER({$column}->>'{$this->key}') != LOWER(?)", [$this->value]),
            default => $this->applyComparisonEloquent($query, $this->buildPostgreSqlJsonExpression($column)),
        };
    }

    // ==================== UTILITAIRES ====================

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
