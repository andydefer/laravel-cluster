<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

enum AggregateFunction: string
{
    case COUNT = 'COUNT';
    case SUM = 'SUM';
    case AVG = 'AVG';
    case MIN = 'MIN';
    case MAX = 'MAX';
    case LENGTH = 'LENGTH';

    public static function fromValue(string $value): ?self
    {
        return match (strtoupper($value)) {
            'COUNT' => self::COUNT,
            'SUM' => self::SUM,
            'AVG' => self::AVG,
            'MIN' => self::MIN,
            'MAX' => self::MAX,
            'LENGTH' => self::LENGTH,
            default => null,
        };
    }

    public function execute(mixed $value): mixed
    {
        return match ($this) {
            self::COUNT => $this->count($value),
            self::SUM => $this->sum($value),
            self::AVG => $this->avg($value),
            self::MIN => $this->min($value),
            self::MAX => $this->max($value),
            self::LENGTH => $this->length($value),
        };
    }

    public function getDefaultValue(): mixed
    {
        return match ($this) {
            self::COUNT, self::LENGTH => 0,
            self::SUM, self::AVG, self::MIN, self::MAX => 0.0,
        };
    }

    public function castValue(string $value): mixed
    {
        return match ($this) {
            self::COUNT, self::LENGTH => (int) $value,
            self::SUM, self::AVG, self::MIN, self::MAX => is_numeric($value) ? (float) $value : $value,
        };
    }

    private function count(mixed $value): int
    {
        return is_array($value) ? count($value) : 0;
    }

    private function sum(mixed $value): float
    {
        if (! is_array($value)) {
            return 0;
        }

        return array_sum($this->extractNumbers($value));
    }

    private function avg(mixed $value): float
    {
        if (! is_array($value)) {
            return 0;
        }
        $numbers = $this->extractNumbers($value);

        return ! empty($numbers) ? array_sum($numbers) / count($numbers) : 0;
    }

    private function min(mixed $value): mixed
    {
        if (! is_array($value)) {
            return 0;
        }
        $numbers = $this->extractNumbers($value);

        return ! empty($numbers) ? min($numbers) : 0;
    }

    private function max(mixed $value): mixed
    {
        if (! is_array($value)) {
            return 0;
        }
        $numbers = $this->extractNumbers($value);

        return ! empty($numbers) ? max($numbers) : 0;
    }

    private function length(mixed $value): int
    {
        if (is_string($value)) {
            return strlen($value);
        }
        if (is_array($value)) {
            return count($value);
        }

        return 0;
    }

    private function extractNumbers(array $array): array
    {
        $numbers = [];
        foreach ($array as $item) {
            if (is_array($item)) {
                $numbers = array_merge($numbers, $this->extractNumbers($item));
            } elseif (is_numeric($item)) {
                $numbers[] = (float) $item;
            }
        }

        return $numbers;
    }
}
