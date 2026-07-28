<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

enum LogicalOperator: string
{
    case AND = 'AND';
    case OR = 'OR';
    case NOT = 'NOT';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromValue(string $value): ?self
    {
        return match ($value) {
            'AND' => self::AND,
            'OR' => self::OR,
            'NOT' => self::NOT,
            default => null,
        };
    }

    public function isAnd(): bool
    {
        return $this === self::AND;
    }

    public function isOr(): bool
    {
        return $this === self::OR;
    }

    public function isNot(): bool
    {
        return $this === self::NOT;
    }

    public function isBinary(): bool
    {
        return $this === self::AND || $this === self::OR;
    }

    public function isUnary(): bool
    {
        return $this === self::NOT;
    }

    public function getEloquentMethod(): string
    {
        return match ($this) {
            self::AND => 'where',
            self::OR => 'orWhere',
            self::NOT => 'whereNot',
        };
    }

    public function getSqlGlue(): string
    {
        return match ($this) {
            self::AND => ' AND ',
            self::OR => ' OR ',
            self::NOT => ' NOT ',
        };
    }

    public function evaluate(bool $left, ?bool $right = null): bool
    {
        return match ($this) {
            self::AND => $left && $right,
            self::OR => $left || $right,
            self::NOT => ! $left,
        };
    }
}
