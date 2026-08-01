<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

/**
 * Represents a sub-condition node in the query AST.
 *
 * This node handles conditions on nested JSON arrays, allowing queries like
 * `addresses[city=Kinshasa]` by evaluating the condition against each element
 * of the array.
 *
 * @example
 * $condition = new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa');
 * $node = new SubConditionNode('addresses', $condition);
 * $node->evaluate($cluster); // true if any address has city = 'Kinshasa'
 * @example
 * $node = new SubConditionNode('addresses', new ConditionNode('*', ComparisonOperator::EXISTS));
 * $node->toSql('clusters', DatabaseDriver::MYSQL);
 */
final class SubConditionNode extends Node
{
    public function __construct(
        private readonly string $path,
        private readonly NodeInterface $condition
    ) {}

    /**
     * Returns the path of this sub-condition.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the condition of this sub-condition.
     */
    public function getCondition(): NodeInterface
    {
        return $this->condition;
    }

    /**
     * Evaluates the sub-condition against a cluster data object.
     *
     * @param  ClusterVO  $data  The cluster data to evaluate against
     * @return bool True if the sub-condition matches
     */
    public function evaluate(ClusterVO $data): bool
    {
        $originalData = $data->getUnflattened()->toArray();
        $value = $this->navigatePath($originalData, $this->path);

        // ✅ Si c'est une string JSON, la décoder
        if (is_string($value) && str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }

        if ($this->condition instanceof ConditionNode && $this->condition->isEmptyCondition()) {
            return is_array($value) && ! empty($value);
        }

        if ($this->condition instanceof ConditionNode && $this->condition->isWildcardExists()) {
            return is_array($value) && ! empty($value);
        }

        if ($this->condition instanceof ConditionNode &&
            $this->condition->getOperator() === ComparisonOperator::NOT_EXISTS) {
            if (! is_array($value) || empty($value)) {
                return true;
            }

            foreach ($value as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $tempCluster = new ClusterVO($item);
                if ($this->condition->evaluate($tempCluster)) {
                    return true;
                }
            }

            return false;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }
            $tempCluster = new ClusterVO($item);
            $result = $this->condition->evaluate($tempCluster);
            if ($result) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generates the SQL expression for this sub-condition.
     *
     * @param  string  $column  The database column containing JSON data
     * @param  DatabaseDriver  $driver  The database driver to use
     * @return string The SQL expression
     */
    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        if ($this->condition instanceof ConditionNode && $this->condition->isEmptyCondition()) {
            return match ($driver) {
                DatabaseDriver::SQLITE => sprintf(
                    "json_array_length(%s, '$.%s') > 0",
                    $column,
                    $this->path
                ),
                DatabaseDriver::MYSQL => sprintf(
                    "JSON_LENGTH(%s, '$.%s') > 0",
                    $column,
                    $this->path
                ),
                DatabaseDriver::PGSQL => sprintf(
                    "jsonb_array_length(%s->'%s') > 0",
                    $column,
                    $this->path
                ),
            };
        }

        if ($this->condition instanceof ConditionNode && $this->condition->isWildcardExists()) {
            return match ($driver) {
                DatabaseDriver::SQLITE => sprintf(
                    "EXISTS (SELECT 1 FROM json_each(%s, '$.%s'))",
                    $column,
                    $this->path
                ),
                DatabaseDriver::MYSQL => sprintf(
                    "EXISTS (SELECT 1 FROM JSON_TABLE(%s, '$.%s[*]' COLUMNS(value JSON PATH '$')) AS jt)",
                    $column,
                    $this->path
                ),
                DatabaseDriver::PGSQL => sprintf(
                    "EXISTS (SELECT 1 FROM jsonb_array_elements(%s->'%s') AS value)",
                    $column,
                    $this->path
                ),
            };
        }

        return match ($driver) {
            DatabaseDriver::MYSQL => $this->buildMySqlSubCondition($column),
            DatabaseDriver::PGSQL => $this->buildPostgreSqlSubCondition($column),
            DatabaseDriver::SQLITE => $this->buildSqliteSubCondition($column),
        };
    }

    /**
     * Applies this sub-condition to an Eloquent query builder.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $column  The database column containing JSON data
     * @param  DatabaseDriver  $driver  The database driver to use
     */
    public function toEloquent(Builder $query, string $column, DatabaseDriver $driver): void
    {
        if ($this->condition instanceof ConditionNode && $this->condition->isEmptyCondition()) {
            match ($driver) {
                DatabaseDriver::SQLITE => $query->whereRaw(
                    "json_array_length({$column}, '$.{$this->path}') > 0"
                ),
                DatabaseDriver::MYSQL => $query->whereRaw(
                    "JSON_LENGTH({$column}, '$.{$this->path}') > 0"
                ),
                DatabaseDriver::PGSQL => $query->whereRaw(
                    "jsonb_array_length({$column}->'{$this->path}') > 0"
                ),
            };

            return;
        }

        if ($this->condition instanceof ConditionNode && $this->condition->isWildcardExists()) {
            match ($driver) {
                DatabaseDriver::SQLITE => $query->whereRaw(
                    "EXISTS (SELECT 1 FROM json_each({$column}, '$.{$this->path}'))"
                ),
                DatabaseDriver::MYSQL => $query->whereRaw(
                    "EXISTS (SELECT 1 FROM JSON_TABLE({$column}, '$.{$this->path}[*]' COLUMNS(value JSON PATH '$')) AS jt)"
                ),
                DatabaseDriver::PGSQL => $query->whereRaw(
                    "EXISTS (SELECT 1 FROM jsonb_array_elements({$column}->'{$this->path}') AS value)"
                ),
            };

            return;
        }

        match ($driver) {
            DatabaseDriver::MYSQL => $this->applyMySqlEloquent($query, $column),
            DatabaseDriver::PGSQL => $this->applyPostgreSqlEloquent($query, $column),
            DatabaseDriver::SQLITE => $this->applySqliteEloquent($query, $column),
        };
    }

    /**
     * Returns the children nodes of this sub-condition.
     *
     * @return array<NodeInterface> The condition node
     */
    public function getChildren(): array
    {
        return [$this->condition];
    }

    /**
     * Builds the SQLite SQL condition.
     */
    private function buildSqliteSubCondition(string $column): string
    {
        $subSql = $this->condition->toSql('value', DatabaseDriver::SQLITE);

        $subSql = trim($subSql);
        if (str_starts_with($subSql, '(') && str_ends_with($subSql, ')')) {
            $subSql = substr($subSql, 1, -1);
        }

        if ($this->condition instanceof ConditionNode &&
            $this->condition->getOperator() === ComparisonOperator::NOT_EXISTS) {
            $subSql = str_replace('IS NULL', 'IS NOT NULL', $subSql);

            return sprintf(
                "NOT EXISTS (SELECT 1 FROM json_each(%s, '$.%s') WHERE %s)",
                $column,
                $this->path,
                $subSql
            );
        }

        return sprintf(
            "EXISTS (SELECT 1 FROM json_each(%s, '$.%s') WHERE %s)",
            $column,
            $this->path,
            $subSql
        );
    }

    /**
     * Applies the condition to an Eloquent query for SQLite.
     */
    private function applySqliteEloquent(Builder $query, string $column): void
    {
        $subSql = $this->condition->toSql('value', DatabaseDriver::SQLITE);

        $subSql = trim($subSql);
        if (str_starts_with($subSql, '(') && str_ends_with($subSql, ')')) {
            $subSql = substr($subSql, 1, -1);
        }

        $isNotExists = false;
        if ($this->condition instanceof ConditionNode &&
            $this->condition->getOperator() === ComparisonOperator::NOT_EXISTS) {
            $isNotExists = true;
        }

        if ($isNotExists) {
            $subSql = str_replace('IS NULL', 'IS NOT NULL', $subSql);
            $query->whereRaw(
                "NOT EXISTS (SELECT 1 FROM json_each({$column}, '$.{$this->path}') WHERE {$subSql})"
            );

            return;
        }

        $query->whereRaw(
            "EXISTS (SELECT 1 FROM json_each({$column}, '$.{$this->path}') WHERE {$subSql})"
        );
    }

    /**
     * Builds the MySQL SQL condition.
     */
    private function buildMySqlSubCondition(string $column): string
    {
        $subSql = $this->condition->toSql('value', DatabaseDriver::MYSQL);

        $subSql = trim($subSql);
        if (str_starts_with($subSql, '(') && str_ends_with($subSql, ')')) {
            $subSql = substr($subSql, 1, -1);
        }

        if ($this->condition instanceof ConditionNode &&
            $this->condition->getOperator() === ComparisonOperator::NOT_EXISTS) {
            $subSql = str_replace('IS NULL', 'IS NOT NULL', $subSql);

            return sprintf(
                "NOT EXISTS (SELECT 1 FROM JSON_TABLE(%s, '$.%s[*]' COLUMNS(value JSON PATH '$')) AS jt WHERE %s)",
                $column,
                $this->path,
                $subSql
            );
        }

        return sprintf(
            "EXISTS (SELECT 1 FROM JSON_TABLE(%s, '$.%s[*]' COLUMNS(value JSON PATH '$')) AS jt WHERE %s)",
            $column,
            $this->path,
            $subSql
        );
    }

    /**
     * Builds the PostgreSQL SQL condition.
     */
    private function buildPostgreSqlSubCondition(string $column): string
    {
        $subSql = $this->condition->toSql('value', DatabaseDriver::PGSQL);

        $subSql = trim($subSql);
        if (str_starts_with($subSql, '(') && str_ends_with($subSql, ')')) {
            $subSql = substr($subSql, 1, -1);
        }

        if ($this->condition instanceof ConditionNode &&
            $this->condition->getOperator() === ComparisonOperator::NOT_EXISTS) {
            $subSql = str_replace('IS NULL', 'IS NOT NULL', $subSql);

            return sprintf(
                "NOT EXISTS (SELECT 1 FROM jsonb_array_elements(%s->'%s') AS value WHERE %s)",
                $column,
                $this->path,
                $subSql
            );
        }

        return sprintf(
            "EXISTS (SELECT 1 FROM jsonb_array_elements(%s->'%s') AS value WHERE %s)",
            $column,
            $this->path,
            $subSql
        );
    }

    /**
     * Applies the condition to an Eloquent query for MySQL.
     */
    private function applyMySqlEloquent(Builder $query, string $column): void
    {
        $subSql = $this->condition->toSql('value', DatabaseDriver::MYSQL);

        $subSql = trim($subSql);
        if (str_starts_with($subSql, '(') && str_ends_with($subSql, ')')) {
            $subSql = substr($subSql, 1, -1);
        }

        if ($this->condition instanceof ConditionNode &&
            $this->condition->getOperator() === ComparisonOperator::NOT_EXISTS) {
            $subSql = str_replace('IS NULL', 'IS NOT NULL', $subSql);
            $query->whereRaw(
                "NOT EXISTS (SELECT 1 FROM JSON_TABLE({$column}, '$.{$this->path}[*]' COLUMNS(value JSON PATH '$')) AS jt WHERE {$subSql})"
            );

            return;
        }

        $query->whereRaw(
            "EXISTS (SELECT 1 FROM JSON_TABLE({$column}, '$.{$this->path}[*]' COLUMNS(value JSON PATH '$')) AS jt WHERE {$subSql})"
        );
    }

    /**
     * Applies the condition to an Eloquent query for PostgreSQL.
     */
    private function applyPostgreSqlEloquent(Builder $query, string $column): void
    {
        $subSql = $this->condition->toSql('value', DatabaseDriver::PGSQL);

        $subSql = trim($subSql);
        if (str_starts_with($subSql, '(') && str_ends_with($subSql, ')')) {
            $subSql = substr($subSql, 1, -1);
        }

        if ($this->condition instanceof ConditionNode &&
            $this->condition->getOperator() === ComparisonOperator::NOT_EXISTS) {
            $subSql = str_replace('IS NULL', 'IS NOT NULL', $subSql);
            $query->whereRaw(
                "NOT EXISTS (SELECT 1 FROM jsonb_array_elements({$column}->'{$this->path}') AS value WHERE {$subSql})"
            );

            return;
        }

        $query->whereRaw(
            "EXISTS (SELECT 1 FROM jsonb_array_elements({$column}->'{$this->path}') AS value WHERE {$subSql})"
        );
    }

    /**
     * Navigates through a dot-notation path in the data array.
     *
     * @param  array<string, mixed>  $data  The source data
     * @param  string  $path  The dot-notation path to navigate
     * @return mixed The value at the path, or null if not found
     */
    private function navigatePath(array $data, string $path): mixed
    {
        if (empty($path)) {
            return $data;
        }

        $parts = explode('.', $path);
        $current = $data;

        foreach ($parts as $part) {
            if (! is_array($current) || ! array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }
}
