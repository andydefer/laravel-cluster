<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

enum LogicalOperator: string
{
    case AND = 'AND';
    case OR = 'OR';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromValue(string $value): ?self
    {
        return match ($value) {
            'AND' => self::AND,
            'OR' => self::OR,
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

    public function getEloquentMethod(): string
    {
        return $this === self::AND ? 'where' : 'orWhere';
    }

    public function getSqlGlue(): string
    {
        return $this === self::AND ? ' AND ' : ' OR ';
    }

    public function evaluate(bool $left, bool $right): bool
    {
        return $this === self::AND ? ($left && $right) : ($left || $right);
    }
}
