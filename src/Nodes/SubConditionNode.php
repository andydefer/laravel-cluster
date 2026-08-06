<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

final class SubConditionNode extends Node
{
    public function __construct(
        private readonly string $path,
        private readonly NodeInterface $condition
    ) {}

    public function getPath(): string
    {
        return $this->path;
    }

    public function getCondition(): NodeInterface
    {
        return $this->condition;
    }

    public function evaluate(ClusterVO $data): bool
    {
        $originalData = $data->getUnflattened()->toArray();
        $value = $this->navigatePath($originalData, $this->path);

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
                    "JSON_LENGTH(%s, '$.%s') > 0",
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
                    "JSON_LENGTH({$column}, '$.{$this->path}') > 0"
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

    public function getChildren(): array
    {
        return [$this->condition];
    }

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

    // ============================================================
    // MYSQL - Solution avec JSON_SEARCH + JSON_LENGTH
    // ============================================================

    private function buildMySqlSubCondition(string $column): string
    {
        $condition = $this->condition;

        if ($condition instanceof ConditionNode) {
            return $this->buildMySqlConditionNodeSql($column, $condition);
        }

        if ($condition instanceof GroupNode) {
            return $this->buildMySqlGroupNodeSql($column, $condition);
        }

        return '1=0';
    }

    private function buildMySqlConditionNodeSql(string $column, ConditionNode $condition): string
    {
        $key = $condition->getKey();
        $operator = $condition->getOperator();
        $value = $condition->getValue();

        // EXISTS
        if ($operator === ComparisonOperator::EXISTS) {
            return sprintf(
                "JSON_LENGTH(%s, '$.%s') > 0",
                $column,
                $this->path
            );
        }

        // NOT EXISTS
        if ($operator === ComparisonOperator::NOT_EXISTS) {
            return sprintf(
                "JSON_LENGTH(%s, '$.%s') = 0 OR JSON_LENGTH(%s, '$.%s') IS NULL",
                $column,
                $this->path,
                $column,
                $this->path
            );
        }

        // LIKE
        if ($operator === ComparisonOperator::LIKE) {
            $pattern = str_replace('%', '', $value ?? '');

            return sprintf(
                "JSON_SEARCH(%s, 'one', '%%%s%%', NULL, '$.%s[*].%s') IS NOT NULL",
                $column,
                $pattern,
                $this->path,
                $key
            );
        }

        // NOT LIKE
        if ($operator === ComparisonOperator::NOT_LIKE) {
            $pattern = str_replace('%', '', $value ?? '');

            return sprintf(
                "JSON_SEARCH(%s, 'one', '%%%s%%', NULL, '$.%s[*].%s') IS NULL",
                $column,
                $pattern,
                $this->path,
                $key
            );
        }

        // EQUAL
        if ($operator === ComparisonOperator::EQUAL) {
            return sprintf(
                "JSON_SEARCH(%s, 'one', '%s', NULL, '$.%s[*].%s') IS NOT NULL",
                $column,
                $value ?? '',
                $this->path,
                $key
            );
        }

        // NOT_EQUAL
        if ($operator === ComparisonOperator::NOT_EQUAL) {
            return sprintf(
                "JSON_SEARCH(%s, 'one', '%s', NULL, '$.%s[*].%s') IS NULL",
                $column,
                $value ?? '',
                $this->path,
                $key
            );
        }

        // Opérateurs numériques
        if ($operator->isNumeric()) {
            $operatorMap = [
                ComparisonOperator::GREATER_THAN => '>',
                ComparisonOperator::GREATER_THAN_OR_EQUAL => '>=',
                ComparisonOperator::LESS_THAN => '<',
                ComparisonOperator::LESS_THAN_OR_EQUAL => '<=',
            ];
            $op = $operatorMap[$operator] ?? '=';

            return sprintf(
                "EXISTS (SELECT 1 FROM JSON_TABLE(%s, '$.%s' COLUMNS(value JSON PATH '$')) AS jt WHERE JSON_UNQUOTE(JSON_EXTRACT(value, '$.\"%s\"')) %s '%s')",
                $column,
                $this->path,
                $key,
                $op,
                $value ?? '0'
            );
        }

        return '1=0';
    }

    private function buildMySqlGroupNodeSql(string $column, GroupNode $group): string
    {
        $children = $group->getChildren();
        $operator = $group->getOperator();

        if (empty($children)) {
            return $operator === LogicalOperator::AND ? '1=1' : '1=0';
        }

        $parts = [];
        foreach ($children as $child) {
            if ($child instanceof ConditionNode) {
                $parts[] = $this->buildMySqlConditionNodeSql($column, $child);
            } elseif ($child instanceof GroupNode) {
                // Groupe imbriqué - extraire les ConditionNode
                $subChildren = $child->getChildren();
                $subParts = [];
                $subOperator = $child->getOperator();
                foreach ($subChildren as $subChild) {
                    if ($subChild instanceof ConditionNode) {
                        $subParts[] = $this->buildMySqlConditionNodeSql($column, $subChild);
                    }
                }
                if (! empty($subParts)) {
                    $glue = $subOperator === LogicalOperator::AND ? ' AND ' : ' OR ';
                    $parts[] = '('.implode($glue, $subParts).')';
                }
            }
        }

        if (empty($parts)) {
            return '1=0';
        }

        $glue = $operator === LogicalOperator::AND ? ' AND ' : ' OR ';

        return count($parts) > 1 ? '('.implode($glue, $parts).')' : $parts[0];
    }

    private function applyMySqlEloquent(Builder $query, string $column): void
    {
        $sql = $this->buildMySqlSubCondition($column);
        $query->whereRaw($sql);
    }

    // ============================================================
    // POSTGRESQL
    // ============================================================

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
