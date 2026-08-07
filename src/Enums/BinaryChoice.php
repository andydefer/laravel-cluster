<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Enums;

enum BinaryChoice: string
{
    case YES = 'yes';
    case NO = 'no';

    /**
     * Get the label for the enum value.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::YES => 'Yes',
            self::NO => 'No',
        };
    }

    /**
     * Check if the value is 'yes'.
     */
    public function isYes(): bool
    {
        return $this === self::YES;
    }

    /**
     * Check if the value is 'no'.
     */
    public function isNo(): bool
    {
        return $this === self::NO;
    }

    /**
     * Convert to boolean.
     */
    public function toBoolean(): bool
    {
        return $this === self::YES;
    }

    /**
     * Create from boolean.
     */
    public static function fromBool(bool $value): self
    {
        return $value ? self::YES : self::NO;
    }

    /**
     * Create from string.
     */
    public static function fromString(string $value): ?self
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'yes', 'y', 'true', '1' => self::YES,
            'no', 'n', 'false', '0' => self::NO,
            default => null,
        };
    }

    /**
     * Get all values as array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all labels as array.
     *
     * @return array<string>
     */
    public static function labels(): array
    {
        return array_map(fn (self $case) => $case->getLabel(), self::cases());
    }

    /**
     * Get all cases as array of key-value pairs.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::YES->value => self::YES->getLabel(),
            self::NO->value => self::NO->getLabel(),
        ];
    }
}
