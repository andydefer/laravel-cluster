<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
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
        echo "\n=== SubConditionNode::evaluate ===\n";
        echo "Path: {$this->path}\n";

        $originalData = $data->getUnflattened()->toArray();
        echo 'Original data: '.json_encode($originalData)."\n";

        $value = $this->navigatePath($originalData, $this->path);
        echo 'Value at path: '.json_encode($value)."\n";
        echo 'Value type: '.gettype($value)."\n";

        // Cas spécial : condition __empty__ (vérifier que le tableau n'est pas vide)
        if ($this->condition instanceof ConditionNode && $this->condition->isEmptyCondition()) {
            echo "Empty condition detected\n";
            $result = is_array($value) && ! empty($value);
            echo 'Result: '.($result ? 'true' : 'false')."\n";

            return $result;
        }

        // Cas spécial : wildcard EXISTS (addresses[])
        if ($this->condition instanceof ConditionNode && $this->condition->isWildcardExists()) {
            echo "Wildcard EXISTS detected\n";
            $result = is_array($value) && ! empty($value);
            echo 'Result: '.($result ? 'true' : 'false')."\n";

            return $result;
        }

        // Cas spécial : NOT_EXISTS (addresses[#city])
        if ($this->condition instanceof ConditionNode &&
            $this->condition->getOperator() === ComparisonOperator::NOT_EXISTS) {
            echo "NOT_EXISTS detected\n";

            // Si le chemin n'existe pas ou n'est pas un tableau ou est vide
            if (! is_array($value) || empty($value)) {
                echo "No array or empty array -> return true\n";

                return true;
            }

            // Parcourir les éléments du tableau
            foreach ($value as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $tempCluster = new ClusterVO($item);
                // Si la condition NOT_EXISTS est vraie pour un élément (la clé n'existe pas)
                if ($this->condition->evaluate($tempCluster)) {
                    echo "Found element without the key -> return true\n";

                    return true;
                }
            }
            echo "All elements have the key -> return false\n";

            return false;
        }

        if (! is_array($value)) {
            echo "Value is not an array -> return false\n";

            return false;
        }

        foreach ($value as $index => $item) {
            echo "Item $index: ".json_encode($item)."\n";
            if (! is_array($item)) {
                echo "Item is not an array -> skip\n";

                continue;
            }
            $tempCluster = new ClusterVO($item);
            $result = $this->condition->evaluate($tempCluster);
            echo "Condition result for item $index: ".($result ? 'true' : 'false')."\n";
            if ($result) {
                echo "Found match -> return true\n";

                return true;
            }
        }

        echo "No match found -> return false\n";

        return false;
    }

    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        // Cas spécial : condition __empty__ (vérifier que le tableau n'est pas vide)
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

        // Cas spécial : wildcard EXISTS (addresses[])
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

    public function toEloquent(Builder $query, string $column, DatabaseDriver $driver): void
    {
        // Cas spécial : condition __empty__
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

        // Cas spécial : wildcard EXISTS (addresses[])
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

        // Cas NOT_EXISTS - on veut NOT EXISTS avec IS NOT NULL à l'intérieur
        if ($this->condition instanceof ConditionNode &&
            $this->condition->getOperator() === ComparisonOperator::NOT_EXISTS) {
            // Remplacer IS NULL par IS NOT NULL pour NOT_EXISTS
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
            // Remplacer IS NULL par IS NOT NULL pour NOT_EXISTS
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

    private function buildMySqlSubCondition(string $column): string
    {
        $subSql = $this->condition->toSql('value', DatabaseDriver::MYSQL);

        $subSql = trim($subSql);
        if (str_starts_with($subSql, '(') && str_ends_with($subSql, ')')) {
            $subSql = substr($subSql, 1, -1);
        }

        // Cas NOT_EXISTS
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

    private function buildPostgreSqlSubCondition(string $column): string
    {
        $subSql = $this->condition->toSql('value', DatabaseDriver::PGSQL);

        $subSql = trim($subSql);
        if (str_starts_with($subSql, '(') && str_ends_with($subSql, ')')) {
            $subSql = substr($subSql, 1, -1);
        }

        // Cas NOT_EXISTS
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

    private function applyMySqlEloquent(Builder $query, string $column): void
    {
        $subSql = $this->condition->toSql('value', DatabaseDriver::MYSQL);

        $subSql = trim($subSql);
        if (str_starts_with($subSql, '(') && str_ends_with($subSql, ')')) {
            $subSql = substr($subSql, 1, -1);
        }

        // Cas NOT_EXISTS
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

    private function applyPostgreSqlEloquent(Builder $query, string $column): void
    {
        $subSql = $this->condition->toSql('value', DatabaseDriver::PGSQL);

        $subSql = trim($subSql);
        if (str_starts_with($subSql, '(') && str_ends_with($subSql, ')')) {
            $subSql = substr($subSql, 1, -1);
        }

        // Cas NOT_EXISTS
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
        $parts = explode('.', $path);
        $current = $data;

        foreach ($parts as $part) {
            if (! isset($current[$part])) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }
}
