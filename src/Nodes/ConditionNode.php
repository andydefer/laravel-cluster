<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Represents a leaf node in the AST that evaluates a single condition.
 *
 * A ConditionNode compares a JSON field value against an expected value
 * using a comparison operator. It supports multiple database drivers and
 * provides evaluation, SQL generation, and Eloquent query building.
 *
 * @example
 * $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '18');
 * $result = $node->evaluate($cluster); // bool
 * $sql = $node->toSql('metadata', DatabaseDriver::MYSQL); // JSON_EXTRACT...
 */
final class ConditionNode extends Node
{
    /**
     * Initializes a condition node with the specified parameters.
     *
     * @param  string  $key  The JSON field key to evaluate
     * @param  ComparisonOperator  $operator  The comparison operator to use
     * @param  ?string  $value  The expected value (null for presence/absence checks)
     */
    public function __construct(
        private readonly string $key,
        private readonly ComparisonOperator $operator,
        private readonly ?string $value = null
    ) {}

    /**
     * Evaluates the condition against a cluster instance.
     *
     * @param  ClusterVO  $data  The cluster containing the data to evaluate
     * @return bool True if the condition is satisfied, false otherwise
     */
    public function evaluate(ClusterVO $data): bool
    {
        $dataArray = $data->toArray();

        if (! array_key_exists($this->key, $dataArray)) {
            if ($this->operator === ComparisonOperator::EQUAL && ($this->value === 'false' || $this->value === 'null')) {
                return true;
            }

            return false;
        }

        return (bool) $this->operator->evaluate($dataArray[$this->key], $this->value);
    }

    /**
     * Converts the condition to a SQL expression.
     *
     * @param  string  $column  The JSON column name
     * @param  DatabaseDriver  $driver  The database driver for dialect-specific syntax
     * @return string The SQL expression string
     */
    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        return match ($driver) {
            DatabaseDriver::MYSQL => $this->toMySql($column),
            DatabaseDriver::PGSQL => $this->toPostgreSql($column),
            DatabaseDriver::SQLITE => $this->toSqlite($column),
        };
    }

    /**
     * Applies the condition to an Eloquent query builder.
     *
     * @param  Builder  $query  The Eloquent query builder instance
     * @param  string  $column  The JSON column name
     * @param  DatabaseDriver  $driver  The database driver for dialect-specific syntax
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
     * Retrieves the child nodes of this node (empty for leaf nodes).
     *
     * @return array<int, Node> An empty array (leaf node has no children)
     */
    public function getChildren(): array
    {
        return [];
    }

    /**
     * Builds a JSON path expression for the field key.
     *
     * @return string The JSON path string (e.g., '$."field"')
     */
    private function getJsonPath(): string
    {
        return '$."'.$this->key.'"';
    }

    /**
     * Builds the MySQL JSON extraction expression for the column.
     *
     * @param  string  $column  The JSON column name
     * @return string The MySQL JSON extraction expression
     */
    private function getMySqlColumn(string $column): string
    {
        $path = $this->getJsonPath();
        $sqlColumn = "JSON_EXTRACT({$column}, '{$path}')";

        if ($this->operator->isNumeric()) {
            $sqlColumn = "CAST(JSON_EXTRACT({$column}, '{$path}') AS DECIMAL(10,2))";
        }

        return $sqlColumn;
    }

    /**
     * Builds the PostgreSQL JSON extraction expression for the column.
     *
     * @param  string  $column  The JSON column name
     * @return string The PostgreSQL JSON extraction expression
     */
    private function getPostgreSqlColumn(string $column): string
    {
        if ($this->operator->isNumeric()) {
            return "({$column}->>'{$this->key}')::numeric";
        }

        return "{$column}->>'{$this->key}'";
    }

    /**
     * Builds the SQLite JSON extraction expression for the column.
     *
     * @param  string  $column  The JSON column name
     * @return string The SQLite JSON extraction expression
     */
    private function getSqliteColumn(string $column): string
    {
        if ($this->operator->isNumeric()) {
            return "CAST(json_extract({$column}, '$.{$this->key}') AS INTEGER)";
        }

        return "json_extract({$column}, '$.{$this->key}')";
    }

    /**
     * Generates the comparison SQL for a given column expression.
     *
     * @param  string  $sqlColumn  The SQL column expression
     * @return string The complete comparison SQL
     */
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

    /**
     * Generates the MySQL SQL expression for this condition.
     *
     * @param  string  $column  The JSON column name
     * @return string The MySQL SQL expression
     */
    private function toMySql(string $column): string
    {
        $path = $this->getJsonPath();

        // Pour les valeurs booléennes, on compare directement avec 'true' ou 'false'
        if ($this->operator === ComparisonOperator::EQUAL) {
            return sprintf("JSON_EXTRACT({$column}, '{$path}') = '%s'", $this->value);
        }

        if ($this->operator === ComparisonOperator::NOT_EQUAL) {
            return sprintf("JSON_EXTRACT({$column}, '{$path}') != '%s'", $this->value);
        }

        $sqlColumn = $this->getMySqlColumn($column);

        return $this->getComparisonSql($sqlColumn);
    }

    /**
     * Generates the PostgreSQL SQL expression for this condition.
     *
     * @param  string  $column  The JSON column name
     * @return string The PostgreSQL SQL expression
     */
    private function toPostgreSql(string $column): string
    {
        // Pour les valeurs booléennes, on compare directement avec 'true' ou 'false'
        if ($this->operator === ComparisonOperator::EQUAL) {
            return sprintf("{$column}->>'%s' = '%s'", $this->key, $this->value);
        }

        if ($this->operator === ComparisonOperator::NOT_EQUAL) {
            return sprintf("{$column}->>'%s' != '%s'", $this->key, $this->value);
        }

        $sqlColumn = $this->getPostgreSqlColumn($column);

        return $this->getComparisonSql($sqlColumn);
    }

    /**
     * Generates the SQLite SQL expression for this condition.
     *
     * @param  string  $column  The JSON column name
     * @return string The SQLite SQL expression
     */
    private function toSqlite(string $column): string
    {
        // Pour les valeurs booléennes, on compare directement avec 'true' ou 'false'
        if ($this->operator === ComparisonOperator::EQUAL) {
            return sprintf("json_extract({$column}, '$.%s') = '%s'", $this->key, $this->value);
        }

        if ($this->operator === ComparisonOperator::NOT_EQUAL) {
            return sprintf("json_extract({$column}, '$.%s') != '%s'", $this->key, $this->value);
        }

        $sqlColumn = $this->getSqliteColumn($column);

        return $this->getComparisonSql($sqlColumn);
    }

    /**
     * Applies the condition to a MySQL Eloquent query builder.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $column  The JSON column name
     */
    private function applyMySqlEloquent(Builder $query, string $column): void
    {
        $path = $this->getJsonPath();

        // Pour les valeurs booléennes, on compare directement avec 'true' ou 'false'
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

    /**
     * Applies the condition to a PostgreSQL Eloquent query builder.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $column  The JSON column name
     */
    private function applyPostgreSqlEloquent(Builder $query, string $column): void
    {
        // Pour les valeurs booléennes, on compare directement avec 'true' ou 'false'
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

    /**
     * Applies the condition to a SQLite Eloquent query builder.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $column  The JSON column name
     */
    private function applySqliteEloquent(Builder $query, string $column): void
    {
        // Pour les valeurs booléennes, on compare directement avec 'true' ou 'false'
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

    /**
     * Applies a comparison condition to an Eloquent query builder.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $sqlColumn  The SQL column expression
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
