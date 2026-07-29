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
    case EXISTS = '*';
    case NOT_EXISTS = '#';
    case LIKE = '=~';
    case NOT_LIKE = '!~';

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
            '*' => self::EXISTS,
            '#' => self::NOT_EXISTS,
            '=~' => self::LIKE,
            '!~' => self::NOT_LIKE,
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

    public function isExistence(): bool
    {
        return $this === self::EXISTS || $this === self::NOT_EXISTS;
    }

    public function isLike(): bool
    {
        return $this === self::LIKE || $this === self::NOT_LIKE;
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
            self::EXISTS => $actual !== null,
            self::NOT_EXISTS => $actual === null,
            self::LIKE => $this->evaluateLike($actual, $value),
            self::NOT_LIKE => ! $this->evaluateLike($actual, $value),
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

    /**
     * Evaluates a LIKE pattern against an actual string value.
     *
     * Supports standard SQL LIKE patterns with '%' wildcards:
     * - 'keyword' → contains (case-insensitive)
     * - 'keyword%' → starts with
     * - '%keyword' → ends with
     * - '%keyword%' → contains
     * - '%j%h%n' → contains j, then h, then n in order
     * - 'j%o%' → starts with j, then contains o
     *
     * @param  mixed  $actual  The actual value to test
     * @param  string|null  $value  The LIKE pattern
     * @return bool True if the pattern matches
     */
    private function evaluateLike(mixed $actual, ?string $value): bool
    {
        if (! is_string($actual) || ! is_string($value)) {
            return false;
        }

        $actualLower = strtolower($actual);
        $valueLower = strtolower($value);

        // If pattern doesn't contain %, simple contains check
        if (! str_contains($value, '%')) {
            return str_contains($actualLower, $valueLower);
        }

        // Case: %keyword% → contains (only if it's the only % at start and end)
        if (str_starts_with($value, '%') && str_ends_with($value, '%') && substr_count($value, '%') === 2) {
            $search = substr($value, 1, -1);

            return str_contains($actualLower, strtolower($search));
        }

        // Case: multiple % patterns (e.g., %j%h%n, j%o%, %a%o%, %j%h%n%)
        $parts = explode('%', $value);
        $parts = array_filter($parts, fn ($p) => $p !== '');

        if (count($parts) >= 2) {
            $position = 0;
            foreach ($parts as $part) {
                $partLower = strtolower($part);
                $pos = strpos($actualLower, $partLower, $position);
                if ($pos === false) {
                    return false;
                }
                $position = $pos + strlen($partLower);
            }

            return true;
        }

        // Case: keyword% → starts with
        if (str_ends_with($value, '%') && ! str_starts_with($value, '%')) {
            $search = substr($value, 0, -1);

            return str_starts_with($actualLower, strtolower($search));
        }

        // Case: %keyword → ends with
        if (str_starts_with($value, '%') && ! str_ends_with($value, '%')) {
            $search = substr($value, 1);

            return str_ends_with($actualLower, strtolower($search));
        }

        // Fallback: generic contains
        $search = str_replace('%', '', $value);

        return str_contains($actualLower, strtolower($search));
    }

    public function toSql(): string
    {
        return match ($this) {
            self::EQUAL, self::EQUAL_LOOSE, self::EQUAL_STRICT => '=',
            self::NOT_EQUAL, self::NOT_EQUAL_STRICT => '!=',
            self::LESS_THAN => '<',
            self::LESS_THAN_OR_EQUAL => '<=',
            self::GREATER_THAN => '>',
            self::GREATER_THAN_OR_EQUAL => '>=',
            self::SPACESHIP => '<=>',
            self::EXISTS => 'IS NOT NULL',
            self::NOT_EXISTS => 'IS NULL',
            self::LIKE => 'LIKE',
            self::NOT_LIKE => 'NOT LIKE',
        };
    }
}
