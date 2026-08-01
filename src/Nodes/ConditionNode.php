<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Represents a single condition node in the query AST.
 *
 * This node handles comparison operations between a JSON path and a value,
 * supporting multiple database drivers (MySQL, PostgreSQL, SQLite).
 *
 * @example
 * $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
 * $node->evaluate($cluster); // true if status is 'active'
 * @example
 * $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '18');
 * $node->toSql('clusters', DatabaseDriver::MYSQL);
 */
final class ConditionNode extends Node
{
    public function __construct(
        private readonly string $key,
        private readonly ComparisonOperator $operator,
        private readonly ?string $value = null
    ) {}

    /**
     * Returns the comparison operator of this condition.
     */
    public function getOperator(): ComparisonOperator
    {
        return $this->operator;
    }

    /**
     * Returns the JSON key/path of this condition.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Returns the comparison value of this condition.
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * Determines if this is an empty condition (used for sub-conditions).
     */
    public function isEmptyCondition(): bool
    {
        return $this->key === '__empty__' && $this->operator === ComparisonOperator::EQUAL;
    }

    /**
     * Determines if this is a wildcard EXISTS condition.
     */
    public function isWildcardExists(): bool
    {
        return $this->key === '*' && $this->operator === ComparisonOperator::EXISTS;
    }

    /**
     * Evaluates the condition against a cluster data object.
     *
     * @param  ClusterVO  $data  The cluster data to evaluate against
     * @return bool True if the condition matches
     */
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

    /**
     * Generates the SQL expression for this condition.
     *
     * @param  string  $column  The database column containing JSON data
     * @param  DatabaseDriver  $driver  The database driver to use
     * @return string The SQL expression
     */
    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        return match ($driver) {
            DatabaseDriver::MYSQL => $this->buildMySqlCondition($column),
            DatabaseDriver::PGSQL => $this->buildPostgreSqlCondition($column),
            DatabaseDriver::SQLITE => $this->buildSqliteCondition($column),
        };
    }

    /**
     * Applies this condition to an Eloquent query builder.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $column  The database column containing JSON data
     * @param  DatabaseDriver  $driver  The database driver to use
     */
    public function toEloquent(Builder $query, string $column, DatabaseDriver $driver): void
    {
        match ($driver) {
            DatabaseDriver::MYSQL => $this->applyMySqlEloquent($query, $column),
            DatabaseDriver::PGSQL => $this->applyPostgreSqlEloquent($query, $column),
            DatabaseDriver::SQLITE => $this->applySqliteEloquent($query, $column),
        };
    }

    /**
     * Returns the children nodes (empty for leaf nodes).
     *
     * @return array<self> An empty array
     */
    public function getChildren(): array
    {
        return [];
    }

    /**
     * Evaluates the condition when the key is missing from the data.
     *
     * @return bool The evaluation result for missing keys
     */
    private function evaluateMissingKey(): bool
    {
        return match ($this->operator) {
            ComparisonOperator::EXISTS => false,
            ComparisonOperator::NOT_EXISTS => true,
            ComparisonOperator::EQUAL => $this->value === 'false' || $this->value === 'null',
            default => false,
        };
    }

    /**
     * Returns the JSON path expression for the current key.
     *
     *
     * @return string The JSON path expression
     *
     * @throws InvalidArgumentException When the key contains invalid characters
     */
    private function getJsonPath(): string
    {
        if (! preg_match('/^[a-zA-Z0-9_\-*]+$/', $this->key)) {
            throw new InvalidArgumentException("Invalid JSON key: {$this->key}");
        }

        return '$."'.$this->key.'"';
    }

    /**
     * Builds the MySQL JSON extraction expression.
     */
    private function buildMySqlJsonExpression(string $column): string
    {
        $path = $this->getJsonPath();

        if ($this->operator->isNumeric()) {
            return "CAST(JSON_EXTRACT({$column}, '{$path}') AS DECIMAL(10,2))";
        }

        return "JSON_EXTRACT({$column}, '{$path}')";
    }

    /**
     * Builds the PostgreSQL JSON extraction expression.
     */
    private function buildPostgreSqlJsonExpression(string $column): string
    {
        if ($this->operator->isNumeric()) {
            return "({$column}->>'{$this->key}')::numeric";
        }

        return "{$column}->>'{$this->key}'";
    }

    /**
     * Builds the SQLite JSON extraction expression.
     */
    private function buildSqliteJsonExpression(string $column): string
    {
        if ($this->operator->isNumeric()) {
            return "CAST(json_extract({$column}, '$.{$this->key}') AS INTEGER)";
        }

        return "json_extract({$column}, '$.{$this->key}')";
    }

    /**
     * Builds a comparison SQL expression with the given column.
     *
     * @throws InvalidArgumentException When the operator is unsupported
     */
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

    /**
     * Escapes a string for safe SQL usage.
     */
    private function escapeSqlString(string $value): string
    {
        return addslashes($value);
    }

    /**
     * Escapes special characters in a LIKE pattern.
     */
    private function escapeLikePattern(string $pattern): string
    {
        $search = ['%', '_', '\\'];
        $replace = ['\\%', '\\_', '\\\\'];

        return str_replace($search, $replace, $pattern);
    }

    /**
     * Converts a value to a LIKE pattern with wildcards.
     */
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

    /**
     * Builds the SQLite SQL condition.
     */
    private function buildSqliteCondition(string $column): string
    {
        return match ($this->operator) {
            ComparisonOperator::EXISTS => sprintf("json_extract({$column}, '$.%s') IS NOT NULL", $this->key),
            ComparisonOperator::NOT_EXISTS => sprintf("json_extract({$column}, '$.%s') IS NULL", $this->key),
            ComparisonOperator::LIKE => sprintf(
                "LOWER(json_extract({$column}, '$.%s')) LIKE LOWER('%s')",
                $this->key,
                $this->convertToLikePattern($this->value)
            ),
            ComparisonOperator::NOT_LIKE => sprintf(
                "LOWER(json_extract({$column}, '$.%s')) NOT LIKE LOWER('%s')",
                $this->key,
                $this->convertToLikePattern($this->value)
            ),
            ComparisonOperator::EQUAL => sprintf(
                "LOWER(json_extract({$column}, '$.%s')) = LOWER('%s')",
                $this->key,
                $this->escapeSqlString($this->value ?? '')
            ),
            ComparisonOperator::NOT_EQUAL => sprintf(
                "LOWER(json_extract({$column}, '$.%s')) != LOWER('%s')",
                $this->key,
                $this->escapeSqlString($this->value ?? '')
            ),
            ComparisonOperator::LESS_THAN => sprintf(
                "CAST(json_extract({$column}, '$.%s') AS NUMERIC) < %s",
                $this->key,
                $this->value
            ),
            ComparisonOperator::LESS_THAN_OR_EQUAL => sprintf(
                "CAST(json_extract({$column}, '$.%s') AS NUMERIC) <= %s",
                $this->key,
                $this->value
            ),
            ComparisonOperator::GREATER_THAN => sprintf(
                "CAST(json_extract({$column}, '$.%s') AS NUMERIC) > %s",
                $this->key,
                $this->value
            ),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => sprintf(
                "CAST(json_extract({$column}, '$.%s') AS NUMERIC) >= %s",
                $this->key,
                $this->value
            ),
            ComparisonOperator::SPACESHIP => $this->buildComparisonSql($this->buildSqliteJsonExpression($column)),
            default => $this->buildComparisonSql($this->buildSqliteJsonExpression($column)),
        };
    }

    /**
     * Applies the condition to an Eloquent query for SQLite.
     */
    private function applySqliteEloquent(Builder $query, string $column): void
    {
        match ($this->operator) {
            ComparisonOperator::EXISTS => $query->whereRaw("json_extract({$column}, '$.{$this->key}') IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => $query->whereRaw("json_extract({$column}, '$.{$this->key}') IS NULL"),
            ComparisonOperator::LIKE => $query->whereRaw(
                "LOWER(json_extract({$column}, '$.{$this->key}')) LIKE LOWER(?)",
                [$this->convertToLikePattern($this->value)]
            ),
            ComparisonOperator::NOT_LIKE => $query->whereRaw(
                "LOWER(json_extract({$column}, '$.{$this->key}')) NOT LIKE LOWER(?)",
                [$this->convertToLikePattern($this->value)]
            ),
            ComparisonOperator::EQUAL => $query->whereRaw(
                "LOWER(json_extract({$column}, '$.{$this->key}')) = LOWER(?)",
                [$this->value]
            ),
            ComparisonOperator::NOT_EQUAL => $query->whereRaw(
                "LOWER(json_extract({$column}, '$.{$this->key}')) != LOWER(?)",
                [$this->value]
            ),
            ComparisonOperator::LESS_THAN => $query->whereRaw(
                "CAST(json_extract({$column}, '$.{$this->key}') AS NUMERIC) < ?",
                [$this->value]
            ),
            ComparisonOperator::LESS_THAN_OR_EQUAL => $query->whereRaw(
                "CAST(json_extract({$column}, '$.{$this->key}') AS NUMERIC) <= ?",
                [$this->value]
            ),
            ComparisonOperator::GREATER_THAN => $query->whereRaw(
                "CAST(json_extract({$column}, '$.{$this->key}') AS NUMERIC) > ?",
                [$this->value]
            ),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => $query->whereRaw(
                "CAST(json_extract({$column}, '$.{$this->key}') AS NUMERIC) >= ?",
                [$this->value]
            ),
            default => $this->applyComparisonEloquent($query, $this->buildSqliteJsonExpression($column)),
        };
    }

    /**
     * Builds the MySQL SQL condition.
     */
    private function buildMySqlCondition(string $column): string
    {
        $path = $this->getJsonPath();

        return match ($this->operator) {
            ComparisonOperator::EXISTS => sprintf("JSON_EXTRACT({$column}, '{$path}') IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => sprintf("JSON_EXTRACT({$column}, '{$path}') IS NULL"),
            ComparisonOperator::LIKE => sprintf(
                "LOWER(JSON_EXTRACT({$column}, '{$path}')) LIKE LOWER('%s')",
                $this->convertToLikePattern($this->value)
            ),
            ComparisonOperator::NOT_LIKE => sprintf(
                "LOWER(JSON_EXTRACT({$column}, '{$path}')) NOT LIKE LOWER('%s')",
                $this->convertToLikePattern($this->value)
            ),
            ComparisonOperator::EQUAL => sprintf(
                "LOWER(JSON_EXTRACT({$column}, '{$path}')) = LOWER('%s')",
                $this->escapeSqlString($this->value ?? '')
            ),
            ComparisonOperator::NOT_EQUAL => sprintf(
                "LOWER(JSON_EXTRACT({$column}, '{$path}')) != LOWER('%s')",
                $this->escapeSqlString($this->value ?? '')
            ),
            default => $this->buildComparisonSql($this->buildMySqlJsonExpression($column)),
        };
    }

    /**
     * Applies the condition to an Eloquent query for MySQL.
     */
    private function applyMySqlEloquent(Builder $query, string $column): void
    {
        $path = $this->getJsonPath();

        match ($this->operator) {
            ComparisonOperator::EXISTS => $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => $query->whereRaw("JSON_EXTRACT({$column}, '{$path}') IS NULL"),
            ComparisonOperator::LIKE => $query->whereRaw(
                "LOWER(JSON_EXTRACT({$column}, '{$path}')) LIKE LOWER(?)",
                [$this->convertToLikePattern($this->value)]
            ),
            ComparisonOperator::NOT_LIKE => $query->whereRaw(
                "LOWER(JSON_EXTRACT({$column}, '{$path}')) NOT LIKE LOWER(?)",
                [$this->convertToLikePattern($this->value)]
            ),
            ComparisonOperator::EQUAL => $query->whereRaw(
                "LOWER(JSON_EXTRACT({$column}, '{$path}')) = LOWER(?)",
                [$this->value]
            ),
            ComparisonOperator::NOT_EQUAL => $query->whereRaw(
                "LOWER(JSON_EXTRACT({$column}, '{$path}')) != LOWER(?)",
                [$this->value]
            ),
            default => $this->applyComparisonEloquent($query, $this->buildMySqlJsonExpression($column)),
        };
    }

    /**
     * Builds the PostgreSQL SQL condition.
     */
    private function buildPostgreSqlCondition(string $column): string
    {
        return match ($this->operator) {
            ComparisonOperator::EXISTS => sprintf("{$column}->'%s' IS NOT NULL", $this->key),
            ComparisonOperator::NOT_EXISTS => sprintf("{$column}->'%s' IS NULL", $this->key),
            ComparisonOperator::LIKE => sprintf(
                "LOWER({$column}->>'%s') LIKE LOWER('%s')",
                $this->key,
                $this->convertToLikePattern($this->value)
            ),
            ComparisonOperator::NOT_LIKE => sprintf(
                "LOWER({$column}->>'%s') NOT LIKE LOWER('%s')",
                $this->key,
                $this->convertToLikePattern($this->value)
            ),
            ComparisonOperator::EQUAL => sprintf(
                "LOWER({$column}->>'%s') = LOWER('%s')",
                $this->key,
                $this->escapeSqlString($this->value ?? '')
            ),
            ComparisonOperator::NOT_EQUAL => sprintf(
                "LOWER({$column}->>'%s') != LOWER('%s')",
                $this->key,
                $this->escapeSqlString($this->value ?? '')
            ),
            default => $this->buildComparisonSql($this->buildPostgreSqlJsonExpression($column)),
        };
    }

    /**
     * Applies the condition to an Eloquent query for PostgreSQL.
     */
    private function applyPostgreSqlEloquent(Builder $query, string $column): void
    {
        match ($this->operator) {
            ComparisonOperator::EXISTS => $query->whereRaw("{$column}->'{$this->key}' IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => $query->whereRaw("{$column}->'{$this->key}' IS NULL"),
            ComparisonOperator::LIKE => $query->whereRaw(
                "LOWER({$column}->>'{$this->key}') LIKE LOWER(?)",
                [$this->convertToLikePattern($this->value)]
            ),
            ComparisonOperator::NOT_LIKE => $query->whereRaw(
                "LOWER({$column}->>'{$this->key}') NOT LIKE LOWER(?)",
                [$this->convertToLikePattern($this->value)]
            ),
            ComparisonOperator::EQUAL => $query->whereRaw(
                "LOWER({$column}->>'{$this->key}') = LOWER(?)",
                [$this->value]
            ),
            ComparisonOperator::NOT_EQUAL => $query->whereRaw(
                "LOWER({$column}->>'{$this->key}') != LOWER(?)",
                [$this->value]
            ),
            default => $this->applyComparisonEloquent($query, $this->buildPostgreSqlJsonExpression($column)),
        };
    }

    /**
     * Applies a comparison condition to an Eloquent query.
     *
     * @throws InvalidArgumentException When the operator is unsupported
     */
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
