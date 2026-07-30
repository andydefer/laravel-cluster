<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

enum AggregateOperator: string
{
    case EQUAL = '=';
    case NOT_EQUAL = '!=';
    case GREATER_THAN = '>';
    case GREATER_THAN_OR_EQUAL = '>=';
    case LESS_THAN = '<';
    case LESS_THAN_OR_EQUAL = '<=';

    public static function fromValue(string $value): ?self
    {
        return match ($value) {
            '=' => self::EQUAL,
            '!=' => self::NOT_EQUAL,
            '>' => self::GREATER_THAN,
            '>=' => self::GREATER_THAN_OR_EQUAL,
            '<' => self::LESS_THAN,
            '<=' => self::LESS_THAN_OR_EQUAL,
            default => null,
        };
    }

    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            $actual = (float) $actual;
            $expected = (float) $expected;
        }

        return match ($this) {
            self::EQUAL => $actual == $expected,
            self::NOT_EQUAL => $actual != $expected,
            self::GREATER_THAN => $actual > $expected,
            self::GREATER_THAN_OR_EQUAL => $actual >= $expected,
            self::LESS_THAN => $actual < $expected,
            self::LESS_THAN_OR_EQUAL => $actual <= $expected,
        };
    }

    public function getSymbol(): string
    {
        return $this->value;
    }
}
