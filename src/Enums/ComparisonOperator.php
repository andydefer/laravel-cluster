<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

enum ComparisonOperator: string
{
    case EQUAL = '=';
    case EQUAL_LOOSE = '==';
    case EQUAL_STRICT = '===';
    case NOT_EQUAL = '!=';
    case NOT_EQUAL_STRICT = '!==';
    case LESS_THAN = '<';
    case LESS_THAN_OR_EQUAL = '<=';
    case GREATER_THAN = '>';
    case GREATER_THAN_OR_EQUAL = '>=';
    case SPACESHIP = '<=>';
    case PRESENCE = 'PRESENCE';
    case ABSENCE = 'ABSENCE';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromValue(string $value): ?self
    {
        return match ($value) {
            '=' => self::EQUAL,
            '==' => self::EQUAL_LOOSE,
            '===' => self::EQUAL_STRICT,
            '!=' => self::NOT_EQUAL,
            '!==' => self::NOT_EQUAL_STRICT,
            '<' => self::LESS_THAN,
            '<=' => self::LESS_THAN_OR_EQUAL,
            '>' => self::GREATER_THAN,
            '>=' => self::GREATER_THAN_OR_EQUAL,
            '<=>' => self::SPACESHIP,
            'PRESENCE' => self::PRESENCE,
            'ABSENCE' => self::ABSENCE,
            default => null,
        };
    }

    public function isComparison(): bool
    {
        return in_array($this, [
            self::EQUAL,
            self::EQUAL_LOOSE,
            self::EQUAL_STRICT,
            self::NOT_EQUAL,
            self::NOT_EQUAL_STRICT,
            self::LESS_THAN,
            self::LESS_THAN_OR_EQUAL,
            self::GREATER_THAN,
            self::GREATER_THAN_OR_EQUAL,
            self::SPACESHIP,
        ], true);
    }

    public function isStrict(): bool
    {
        return in_array($this, [
            self::EQUAL_STRICT,
            self::NOT_EQUAL_STRICT,
        ], true);
    }

    public function isNumeric(): bool
    {
        return in_array($this, [
            self::LESS_THAN,
            self::LESS_THAN_OR_EQUAL,
            self::GREATER_THAN,
            self::GREATER_THAN_OR_EQUAL,
            self::SPACESHIP,
        ], true);
    }

    public function isPresence(): bool
    {
        return $this === self::PRESENCE || $this === self::ABSENCE;
    }

    public function evaluate(mixed $actual, ?string $value): bool|int
    {
        return match ($this) {
            self::EQUAL => (string) $actual === (string) $value,
            self::EQUAL_LOOSE => (string) $actual == (string) $value,
            self::EQUAL_STRICT => $actual === $value,
            self::NOT_EQUAL => (string) $actual !== (string) $value,
            self::NOT_EQUAL_STRICT => $actual !== $value,
            self::LESS_THAN => $this->compareLess($actual, $value),
            self::LESS_THAN_OR_EQUAL => $this->compareLessOrEqual($actual, $value),
            self::GREATER_THAN => $this->compareGreater($actual, $value),
            self::GREATER_THAN_OR_EQUAL => $this->compareGreaterOrEqual($actual, $value),
            self::SPACESHIP => $this->compareSpaceship($actual, $value),
            self::PRESENCE => $actual !== null && $actual !== false && $actual !== 'false' && $actual !== '0',
            self::ABSENCE => $actual === null || $actual === false || $actual === 'false' || $actual === '0' || $actual === '',
        };
    }

    private function compareLess(mixed $actual, ?string $value): bool
    {
        if (is_numeric($actual) && is_numeric($value)) {
            return (float) $actual < (float) $value;
        }

        return (string) $actual < (string) $value;
    }

    private function compareLessOrEqual(mixed $actual, ?string $value): bool
    {
        if (is_numeric($actual) && is_numeric($value)) {
            return (float) $actual <= (float) $value;
        }

        return (string) $actual <= (string) $value;
    }

    private function compareGreater(mixed $actual, ?string $value): bool
    {
        if (is_numeric($actual) && is_numeric($value)) {
            return (float) $actual > (float) $value;
        }

        return (string) $actual > (string) $value;
    }

    private function compareGreaterOrEqual(mixed $actual, ?string $value): bool
    {
        if (is_numeric($actual) && is_numeric($value)) {
            return (float) $actual >= (float) $value;
        }

        return (string) $actual >= (string) $value;
    }

    private function compareSpaceship(mixed $actual, ?string $value): int
    {
        if (is_numeric($actual) && is_numeric($value)) {
            return (float) $actual <=> (float) $value;
        }

        return (string) $actual <=> (string) $value;
    }
}
