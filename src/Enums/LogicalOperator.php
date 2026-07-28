<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

/**
 * Enumeration of logical operators supported by the query system.
 *
 * This enum provides boolean operators (AND, OR, NOT) with evaluation
 * and SQL generation capabilities.
 *
 * @example
 * $op = LogicalOperator::AND;
 * $result = $op->evaluate(true, true); // true
 * $sqlGlue = $op->getSqlGlue(); // ' AND '
 */
enum LogicalOperator: string
{
    case AND = 'AND';
    case OR = 'OR';
    case NOT = 'NOT';

    /**
     * Returns all operator values as an array.
     *
     * @return array<string> Array of operator string values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Creates an enum instance from a string value.
     *
     * @param  string  $value  The operator string value
     * @return ?self The enum instance or null if not found
     */
    public static function fromValue(string $value): ?self
    {
        return match ($value) {
            'AND' => self::AND,
            'OR' => self::OR,
            'NOT' => self::NOT,
            default => null,
        };
    }

    /**
     * Determines if the operator is AND.
     *
     * @return bool True if the operator is AND
     */
    public function isAnd(): bool
    {
        return $this === self::AND;
    }

    /**
     * Determines if the operator is OR.
     *
     * @return bool True if the operator is OR
     */
    public function isOr(): bool
    {
        return $this === self::OR;
    }

    /**
     * Determines if the operator is NOT.
     *
     * @return bool True if the operator is NOT
     */
    public function isNot(): bool
    {
        return $this === self::NOT;
    }

    /**
     * Determines if the operator is binary (AND or OR).
     *
     * @return bool True if the operator is binary
     */
    public function isBinary(): bool
    {
        return $this === self::AND || $this === self::OR;
    }

    /**
     * Determines if the operator is unary (NOT).
     *
     * @return bool True if the operator is unary
     */
    public function isUnary(): bool
    {
        return $this === self::NOT;
    }

    /**
     * Returns the corresponding Eloquent query builder method name.
     *
     * @return string The Eloquent method name (e.g., 'where', 'orWhere')
     */
    public function getEloquentMethod(): string
    {
        return match ($this) {
            self::AND => 'where',
            self::OR => 'orWhere',
            self::NOT => 'whereNot',
        };
    }

    /**
     * Returns the SQL syntax for joining conditions.
     *
     * @return string The SQL glue string (e.g., ' AND ')
     */
    public function getSqlGlue(): string
    {
        return match ($this) {
            self::AND => ' AND ',
            self::OR => ' OR ',
            self::NOT => ' NOT ',
        };
    }

    /**
     * Evaluates the logical operator against boolean values.
     *
     * @param  bool  $left  The left operand
     * @param  ?bool  $right  The right operand (required for binary operators)
     * @return bool The result of the logical operation
     */
    public function evaluate(bool $left, ?bool $right = null): bool
    {
        return match ($this) {
            self::AND => $left && $right,
            self::OR => $left || $right,
            self::NOT => ! $left,
        };
    }
}
