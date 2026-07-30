<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Nodes;

use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Represents a SQL function node in the query AST.
 *
 * This node handles aggregate functions (COUNT, SUM, AVG, MIN, MAX) and
 * JSON functions (JSON_LENGTH) applied to JSON data paths.
 *
 * @example
 * $node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');
 * $node->evaluate($cluster); // true if addresses count > 2
 * @example
 * $node = new FunctionNode('AVG', 'scores', ComparisonOperator::GREATER_THAN_OR_EQUAL, '85');
 * $node->toSql('clusters', DatabaseDriver::MYSQL);
 */
final class FunctionNode extends Node
{
    private string $functionName;

    private array $aggregateFunctions = ['SUM', 'AVG', 'MIN', 'MAX'];

    public function __construct(
        string $functionName,
        private readonly string $path,
        private readonly ComparisonOperator $operator,
        private readonly ?string $value = null
    ) {
        $this->functionName = strtoupper($functionName);
    }

    /**
     * Evaluates the function against a cluster data object.
     *
     * @param  ClusterVO  $cluster  The cluster data to evaluate against
     * @return bool True if the function condition matches
     */
    public function evaluate(ClusterVO $cluster): bool
    {
        $registry = app(SqlFunctionRegistry::class);

        if (! $registry->has($this->functionName)) {
            return false;
        }

        $data = $cluster->getUnflattened()->toArray();
        $actual = $this->extractValue($data, $this->path);

        if ($actual === null) {
            return false;
        }

        $result = $registry->execute($this->functionName, $actual);

        return $this->operator->evaluate($result, $this->value);
    }

    /**
     * Generates the SQL expression for this function.
     *
     * @param  string  $column  The database column containing JSON data
     * @param  DatabaseDriver  $driver  The database driver to use
     * @return string The SQL expression
     *
     * @throws InvalidArgumentException When the operator is unsupported
     */
    public function toSql(string $column, DatabaseDriver $driver = DatabaseDriver::MYSQL): string
    {
        $registry = app(SqlFunctionRegistry::class);

        if (! $registry->has($this->functionName)) {
            return '1=0';
        }

        if ($driver === DatabaseDriver::SQLITE && $this->functionName === 'JSON_LENGTH') {
            return $this->buildSqliteJsonLength($column);
        }

        $sqlExpression = $registry->toSql($this->functionName, $column, $this->path, $driver);

        if ($sqlExpression === null) {
            return '1=0';
        }

        $returnType = $registry->getReturnType($this->functionName);

        if ($this->value === null) {
            return match ($this->operator) {
                ComparisonOperator::EXISTS => sprintf('%s IS NOT NULL', $sqlExpression),
                ComparisonOperator::NOT_EXISTS => sprintf('%s IS NULL', $sqlExpression),
                default => sprintf('%s IS NOT NULL', $sqlExpression),
            };
        }

        $castedValue = $this->castValue($this->value, $returnType);

        return match ($this->operator) {
            ComparisonOperator::EQUAL,
            ComparisonOperator::EQUAL_LOOSE,
            ComparisonOperator::EQUAL_STRICT => sprintf('%s = %s', $sqlExpression, $castedValue),
            ComparisonOperator::NOT_EQUAL,
            ComparisonOperator::NOT_EQUAL_STRICT => sprintf('%s != %s', $sqlExpression, $castedValue),
            ComparisonOperator::GREATER_THAN => sprintf('%s > %s', $sqlExpression, $castedValue),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => sprintf('%s >= %s', $sqlExpression, $castedValue),
            ComparisonOperator::LESS_THAN => sprintf('%s < %s', $sqlExpression, $castedValue),
            ComparisonOperator::LESS_THAN_OR_EQUAL => sprintf('%s <= %s', $sqlExpression, $castedValue),
            ComparisonOperator::EXISTS => sprintf('%s IS NOT NULL', $sqlExpression),
            ComparisonOperator::NOT_EXISTS => sprintf('%s IS NULL', $sqlExpression),
            default => throw new InvalidArgumentException('Unsupported operator for SQL function'),
        };
    }

    /**
     * Applies this function to an Eloquent query builder.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $column  The database column containing JSON data
     * @param  DatabaseDriver  $driver  The database driver to use
     */
    public function toEloquent(Builder $query, string $column, DatabaseDriver $driver): void
    {
        if ($this->isAggregateFunction()) {
            $sql = $this->buildAggregateSql($column, $driver);
            $query->whereRaw($sql);

            return;
        }

        $sql = $this->toSql($column, $driver);
        $query->whereRaw($sql);
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
     * Determines if the current function is an aggregate function.
     */
    private function isAggregateFunction(): bool
    {
        return in_array($this->functionName, $this->aggregateFunctions, true);
    }

    /**
     * Builds a SQL subquery for aggregate functions.
     */
    private function buildAggregateSql(string $column, DatabaseDriver $driver): string
    {
        $registry = app(SqlFunctionRegistry::class);
        $returnType = $registry->getReturnType($this->functionName);
        $function = strtolower($this->functionName);

        if ($driver === DatabaseDriver::SQLITE) {
            $castedValue = $this->castValue($this->value ?? '0', $returnType);
            $operatorMap = [
                '=' => '=',
                '!=' => '!=',
                '>' => '>',
                '>=' => '>=',
                '<' => '<',
                '<=' => '<=',
            ];
            $sqlOperator = $operatorMap[$this->operator->value] ?? '=';

            return sprintf(
                "(SELECT %s(json_extract(value, '$')) FROM json_each(%s, '$.%s')) %s %s",
                $function,
                $column,
                $this->path,
                $sqlOperator,
                $castedValue
            );
        }

        $sqlExpression = $registry->toSql($this->functionName, $column, $this->path, $driver);

        if ($sqlExpression === null) {
            return '1=0';
        }

        $castedValue = $this->castValue($this->value ?? '0', $returnType);

        return match ($this->operator) {
            ComparisonOperator::EQUAL,
            ComparisonOperator::EQUAL_LOOSE,
            ComparisonOperator::EQUAL_STRICT => sprintf('%s = %s', $sqlExpression, $castedValue),
            ComparisonOperator::NOT_EQUAL,
            ComparisonOperator::NOT_EQUAL_STRICT => sprintf('%s != %s', $sqlExpression, $castedValue),
            ComparisonOperator::GREATER_THAN => sprintf('%s > %s', $sqlExpression, $castedValue),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => sprintf('%s >= %s', $sqlExpression, $castedValue),
            ComparisonOperator::LESS_THAN => sprintf('%s < %s', $sqlExpression, $castedValue),
            ComparisonOperator::LESS_THAN_OR_EQUAL => sprintf('%s <= %s', $sqlExpression, $castedValue),
            default => sprintf('%s IS NOT NULL', $sqlExpression),
        };
    }

    /**
     * Builds a SQLite JSON_LENGTH expression.
     */
    private function buildSqliteJsonLength(string $column): string
    {
        $castedValue = $this->castValue($this->value ?? '0', 'int');

        $sql = sprintf(
            "json_array_length(%s, '$.%s')",
            $column,
            $this->path
        );

        return match ($this->operator) {
            ComparisonOperator::EQUAL,
            ComparisonOperator::EQUAL_LOOSE,
            ComparisonOperator::EQUAL_STRICT => sprintf('%s = %s', $sql, $castedValue),
            ComparisonOperator::NOT_EQUAL,
            ComparisonOperator::NOT_EQUAL_STRICT => sprintf('%s != %s', $sql, $castedValue),
            ComparisonOperator::GREATER_THAN => sprintf('%s > %s', $sql, $castedValue),
            ComparisonOperator::GREATER_THAN_OR_EQUAL => sprintf('%s >= %s', $sql, $castedValue),
            ComparisonOperator::LESS_THAN => sprintf('%s < %s', $sql, $castedValue),
            ComparisonOperator::LESS_THAN_OR_EQUAL => sprintf('%s <= %s', $sql, $castedValue),
            default => sprintf('%s IS NOT NULL', $sql),
        };
    }

    /**
     * Extracts a value from the data array using a dot-notation path.
     *
     * @param  array<string, mixed>  $data  The source data
     * @param  string  $path  The dot-notation path to extract
     * @return mixed The extracted value, or null if not found
     */
    private function extractValue(array $data, string $path): mixed
    {
        if (empty($path)) {
            return $data;
        }

        $parts = explode('.', $path);
        $current = $data;

        foreach ($parts as $part) {
            if (! is_array($current)) {
                return null;
            }

            if (! array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }

    /**
     * Casts a value to the appropriate SQL type.
     *
     * @param  string  $value  The value to cast
     * @param  string|null  $returnType  The target type ('int', 'float', 'bool')
     * @return string The casted value as a SQL string
     */
    private function castValue(string $value, ?string $returnType): string
    {
        if ($returnType === null) {
            return "'".addslashes($value)."'";
        }

        return match ($returnType) {
            'int' => (string) (int) $value,
            'float' => (string) (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            default => "'".addslashes($value)."'",
        };
    }
}
