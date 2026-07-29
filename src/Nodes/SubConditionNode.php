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

    public function evaluate(ClusterVO $data): bool
    {
        $originalData = $data->getUnflattened()->toArray();
        $value = $this->navigatePath($originalData, $this->path);

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            $tempCluster = new ClusterVO($item);
            if ($this->condition->evaluate($tempCluster)) {
                return true;
            }
        }

        return false;
    }

    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        return match ($driver) {
            DatabaseDriver::MYSQL => $this->buildMySqlSubCondition($column),
            DatabaseDriver::PGSQL => $this->buildPostgreSqlSubCondition($column),
            DatabaseDriver::SQLITE => $this->buildSqliteSubCondition($column),
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
        return [$this->condition];
    }

    private function buildSqliteSubCondition(string $column): string
    {
        $subSql = $this->condition->toSql('value', DatabaseDriver::SQLITE);

        // Enlever les parenthèses extérieures si présentes
        $subSql = trim($subSql);
        if (str_starts_with($subSql, '(') && str_ends_with($subSql, ')')) {
            $subSql = substr($subSql, 1, -1);
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

        // Enlever les parenthèses extérieures si présentes
        $subSql = trim($subSql);
        if (str_starts_with($subSql, '(') && str_ends_with($subSql, ')')) {
            $subSql = substr($subSql, 1, -1);
        }

        // Vérifier si c'est un NOT_EXISTS
        $isNotExists = false;
        if ($this->condition instanceof ConditionNode &&
            $this->condition->getOperator() === ComparisonOperator::NOT_EXISTS) {
            $isNotExists = true;
        }

        // Si c'est un chemin avec des points (objet imbriqué)
        if (strpos($this->path, '.') !== false) {
            $subSql = str_replace('value', "json_extract({$column}, '$.{$this->path}')", $subSql);
            $query->whereRaw($subSql);

            return;
        }

        // Pour NOT_EXISTS, on utilise NOT EXISTS
        if ($isNotExists) {
            $sql = "NOT EXISTS (SELECT 1 FROM json_each({$column}, '$.{$this->path}') WHERE {$subSql})";
        } else {
            $sql = "EXISTS (SELECT 1 FROM json_each({$column}, '$.{$this->path}') WHERE {$subSql})";
        }

        $query->whereRaw($sql);
    }

    private function buildMySqlSubCondition(string $column): string
    {
        $subSql = $this->condition->toSql('value', DatabaseDriver::MYSQL);

        $subSql = trim($subSql);
        if (str_starts_with($subSql, '(') && str_ends_with($subSql, ')')) {
            $subSql = substr($subSql, 1, -1);
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
