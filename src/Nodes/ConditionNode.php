<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Represents a condition node in a cluster query tree.
 *
 * This node evaluates a single condition against a JSON data structure,
 * comparing a specific key's value using a specified operator.
 * Supports multiple database drivers for SQL generation and Eloquent query building.
 *
 * @example
 * $condition = new ConditionNode('user.age', ComparisonOperator::GREATER_THAN, '18');
 * $isValid = $condition->evaluate($clusterData);
 * $sql = $condition->toSql('json_column', DatabaseDriver::MYSQL);
 */
final class ConditionNode extends Node
{
    /**
     * @param  string  $key  The JSON path key to evaluate
     * @param  ComparisonOperator  $operator  The comparison operator to apply
     * @param  string|null  $value  The value to compare against (null for existence checks)
     */
    public function __construct(
        private readonly string $key,
        private readonly ComparisonOperator $operator,
        private readonly ?string $value = null
    ) {}

    /**
     * Evaluates this condition against the provided cluster data.
     *
     * Handles special cases for existence checks and treats 'false' and 'null'
     * string values as actual false/null values for equality checks.
     *
     * @param  ClusterVO  $data  The data cluster to evaluate against
     * @return bool True if the condition is satisfied, false otherwise
     */
    public function evaluate(ClusterVO $data): bool
    {
        $dataArray = $data->toArray();
        $keyExists = array_key_exists($this->key, $dataArray);

        if (! $keyExists) {
            return $this->evaluateMissingKey();
        }

        if ($this->operator === ComparisonOperator::EXISTS || $this->operator === ComparisonOperator::NOT_EXISTS) {
            return $this->operator === ComparisonOperator::EXISTS;
        }

        return (bool) $this->operator->evaluate($dataArray[$this->key], $this->value);
    }

    /**
     * Generates a SQL condition string for the specified database driver.
     *
     * @param  string  $column  The JSON column name in the database
     * @param  DatabaseDriver  $driver  The database driver to generate SQL for
     * @return string The SQL condition string
     *
     * @throws InvalidArgumentException If an unsupported operator is encountered
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
     * Uses parameter binding for all values to prevent SQL injection.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $column  The JSON column name
     * @param  DatabaseDriver  $driver  The database driver
     *
     * @throws InvalidArgumentException If an unsupported operator is encountered
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
     * Returns an empty array as this is a leaf node in the condition tree.
     *
     * @return array<int, Node> Empty array
     */
    public function getChildren(): array
    {
        return [];
    }

    /**
     * Evaluates the condition when the key is missing from the data.
     *
     * Special cases:
     * - EXISTS operator: key missing means condition fails
     * - NOT_EXISTS operator: key missing means condition passes
     * - EQUAL operator with 'false' or 'null': key missing means condition passes
     * - All other operators: key missing means condition fails
     *
     * @return bool The evaluation result
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
     * Validates and returns the JSON path for SQL operations.
     *
     * @return string The JSON path (e.g., '$."key"')
     *
     * @throws InvalidArgumentException If the key contains invalid characters
     */
    private function getJsonPath(): string
    {
        if (! preg_match('/^[a-zA-Z0-9_\-]+$/', $this->key)) {
            throw new InvalidArgumentException("Invalid JSON key: {$this->key}");
        }

        return '$."'.$this->key.'"';
    }

    /**
     * Builds a MySQL JSON column expression with proper casting.
     *
     * @param  string  $column  The JSON column name
     * @return string The SQL expression
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
     * Builds a PostgreSQL JSON column expression.
     *
     * @param  string  $column  The JSON column name
     * @return string The SQL expression
     */
    private function buildPostgreSqlJsonExpression(string $column): string
    {
        if ($this->operator->isNumeric()) {
            return "({$column}->>'{$this->key}')::numeric";
        }

        return "{$column}->>'{$this->key}'";
    }

    /**
     * Builds an SQLite JSON column expression.
     *
     * @param  string  $column  The JSON column name
     * @return string The SQL expression
     */
    private function buildSqliteJsonExpression(string $column): string
    {
        if ($this->operator->isNumeric()) {
            return "CAST(json_extract({$column}, '$.{$this->key}') AS INTEGER)";
        }

        return "json_extract({$column}, '$.{$this->key}')";
    }

    /**
     * Builds a generic comparison SQL string.
     *
     * @param  string  $sqlColumn  The SQL column expression
     * @return string The comparison SQL string
     *
     * @throws InvalidArgumentException If the operator doesn't support direct comparison
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
     *
     * @param  string  $value  The value to escape
     * @return string The escaped string
     */
    private function escapeSqlString(string $value): string
    {
        return addslashes($value);
    }

    /**
     * Escapes special characters in a LIKE pattern.
     *
     * @param  string  $pattern  The pattern to escape
     * @return string The escaped pattern
     */
    private function escapeLikePattern(string $pattern): string
    {
        $search = ['%', '_', '\\'];
        $replace = ['\\%', '\\_', '\\\\'];

        return str_replace($search, $replace, $pattern);
    }

    /**
     * Converts a value to a LIKE pattern.
     *
     * If the value already contains '%', returns it as-is.
     * Otherwise, wraps it with '%' for partial matching.
     *
     * @param  string|null  $value  The value to convert
     * @return string The LIKE pattern
     */
    private function convertToLikePattern(?string $value): string
    {
        if ($value === null) {
            return '%';
        }

        // Si la valeur contient déjà des %, on la retourne telle quelle
        if (str_contains($value, '%')) {
            return $value;
        }

        return '%'.$this->escapeLikePattern($value).'%';
    }

    /**
     * Builds a MySQL condition SQL string.
     *
     * @param  string  $column  The JSON column name
     * @return string The MySQL condition SQL
     */
    private function buildMySqlCondition(string $column): string
    {
        $path = $this->getJsonPath();

        return match ($this->operator) {
            ComparisonOperator::EXISTS => sprintf("JSON_EXTRACT({$column}, '{$path}') IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => sprintf("JSON_EXTRACT({$column}, '{$path}') IS NULL"),
            ComparisonOperator::LIKE => sprintf("JSON_EXTRACT({$column}, '{$path}') LIKE '%s'", $this->convertToLikePattern($this->value)),
            ComparisonOperator::NOT_LIKE => sprintf("JSON_EXTRACT({$column}, '{$path}') NOT LIKE '%s'", $this->convertToLikePattern($this->value)),
            ComparisonOperator::EQUAL => sprintf("JSON_EXTRACT({$column}, '{$path}') = '%s'", $this->escapeSqlString($this->value ?? '')),
            ComparisonOperator::NOT_EQUAL => sprintf("JSON_EXTRACT({$column}, '{$path}') != '%s'", $this->escapeSqlString($this->value ?? '')),
            default => $this->buildComparisonSql($this->buildMySqlJsonExpression($column)),
        };
    }

    /**
     * Builds a PostgreSQL condition SQL string.
     *
     * @param  string  $column  The JSON column name
     * @return string The PostgreSQL condition SQL
     */
    private function buildPostgreSqlCondition(string $column): string
    {
        return match ($this->operator) {
            ComparisonOperator::EXISTS => sprintf("{$column}->'%s' IS NOT NULL", $this->key),
            ComparisonOperator::NOT_EXISTS => sprintf("{$column}->'%s' IS NULL", $this->key),
            ComparisonOperator::LIKE => sprintf("{$column}->>'%s' ILIKE '%s'", $this->key, $this->convertToLikePattern($this->value)),
            ComparisonOperator::NOT_LIKE => sprintf("{$column}->>'%s' NOT ILIKE '%s'", $this->key, $this->convertToLikePattern($this->value)),
            ComparisonOperator::EQUAL => sprintf("{$column}->>'%s' = '%s'", $this->key, $this->escapeSqlString($this->value ?? '')),
            ComparisonOperator::NOT_EQUAL => sprintf("{$column}->>'%s' != '%s'", $this->key, $this->escapeSqlString($this->value ?? '')),
            default => $this->buildComparisonSql($this->buildPostgreSqlJsonExpression($column)),
        };
    }

    /**
     * Builds an SQLite condition SQL string.
     *
     * @param  string  $column  The JSON column name
     * @return string The SQLite condition SQL
     */
    private function buildSqliteCondition(string $column): string
    {
        return match ($this->operator) {
            ComparisonOperator::EXISTS => sprintf("json_extract({$column}, '$.%s') IS NOT NULL", $this->key),
            ComparisonOperator::NOT_EXISTS => sprintf("json_extract({$column}, '$.%s') IS NULL", $this->key),
            ComparisonOperator::LIKE => sprintf("json_extract({$column}, '$.%s') LIKE '%s'", $this->key, $this->convertToLikePattern($this->value)),
            ComparisonOperator::NOT_LIKE => sprintf("json_extract({$column}, '$.%s') NOT LIKE '%s'", $this->key, $this->convertToLikePattern($this->value)),
            ComparisonOperator::EQUAL => sprintf("json_extract({$column}, '$.%s') = '%s'", $this->key, $this->escapeSqlString($this->value ?? '')),
            ComparisonOperator::NOT_EQUAL => sprintf("json_extract({$column}, '$.%s') != '%s'", $this->key, $this->escapeSqlString($this->value ?? '')),
            default => $this->buildComparisonSql($this->buildSqliteJsonExpression($column)),
        };
    }

    /**
     * Applies this condition to a MySQL Eloquent query.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $column  The JSON column name
     */
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
            default => $this->applyComparisonEloquent($query, $this->buildMySqlJsonExpression($column)),
        };
    }

    /**
     * Applies this condition to a PostgreSQL Eloquent query.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $column  The JSON column name
     */
    private function applyPostgreSqlEloquent(Builder $query, string $column): void
    {
        match ($this->operator) {
            ComparisonOperator::EXISTS => $query->whereRaw("{$column}->'{$this->key}' IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => $query->whereRaw("{$column}->'{$this->key}' IS NULL"),
            ComparisonOperator::LIKE => $query->whereRaw("{$column}->>'{$this->key}' ILIKE ?", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::NOT_LIKE => $query->whereRaw("{$column}->>'{$this->key}' NOT ILIKE ?", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::EQUAL => $query->whereRaw("{$column}->>'{$this->key}' = ?", [$this->value]),
            ComparisonOperator::NOT_EQUAL => $query->whereRaw("{$column}->>'{$this->key}' != ?", [$this->value]),
            default => $this->applyComparisonEloquent($query, $this->buildPostgreSqlJsonExpression($column)),
        };
    }

    /**
     * Applies this condition to an SQLite Eloquent query.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $column  The JSON column name
     */
    private function applySqliteEloquent(Builder $query, string $column): void
    {
        match ($this->operator) {
            ComparisonOperator::EXISTS => $query->whereRaw("json_extract({$column}, '$.{$this->key}') IS NOT NULL"),
            ComparisonOperator::NOT_EXISTS => $query->whereRaw("json_extract({$column}, '$.{$this->key}') IS NULL"),
            ComparisonOperator::LIKE => $query->whereRaw("json_extract({$column}, '$.{$this->key}') LIKE ?", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::NOT_LIKE => $query->whereRaw("json_extract({$column}, '$.{$this->key}') NOT LIKE ?", [$this->convertToLikePattern($this->value)]),
            ComparisonOperator::EQUAL => $query->whereRaw("json_extract({$column}, '$.{$this->key}') = ?", [$this->value]),
            ComparisonOperator::NOT_EQUAL => $query->whereRaw("json_extract({$column}, '$.{$this->key}') != ?", [$this->value]),
            default => $this->applyComparisonEloquent($query, $this->buildSqliteJsonExpression($column)),
        };
    }

    /**
     * Applies a comparison condition to an Eloquent query using parameter binding.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $sqlColumn  The SQL column expression
     *
     * @throws InvalidArgumentException If the operator doesn't support direct comparison
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
