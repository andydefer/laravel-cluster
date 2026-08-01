<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Proxies;

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

/**
 * Proxy that automatically normalizes boolean values to 'yes'/'no'
 * and handles nested structures when creating a ClusterVO.
 */
final class ClusterVOProxy
{
    /**
     * Create a ClusterVO with all boolean values converted to 'yes'/'no'.
     *
     * @param  array<string, mixed>  $data
     */
    public static function make(array $data): ClusterVO
    {
        return new ClusterVO(self::normalize($data));
    }

    /**
     * Recursively normalize values for ClusterVO creation.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function normalize(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $result[$key] = self::normalizeValue($value);
        }

        return $result;
    }

    /**
     * Normalize a single value.
     */
    private static function normalizeValue(mixed $value): mixed
    {
        return match (true) {
            is_null($value) => null,
            is_bool($value) => $value ? 'yes' : 'no',
            is_string($value) && self::isBooleanString($value) => strtolower($value) === 'true' ? 'yes' : 'no',
            is_string($value) && self::isJson($value) => self::normalizeJson($value),
            is_array($value) => self::normalize($value),
            is_object($value) && method_exists($value, 'toArray') => self::normalize($value->toArray()),
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            is_object($value) => self::normalizeObject($value),
            default => $value,
        };
    }

    /**
     * Normalize a JSON string by decoding and re-encoding with normalized values.
     */
    private static function normalizeJson(string $value): string
    {
        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return $value;
        }

        return json_encode(self::normalize($decoded));
    }

    /**
     * Normalize an object by converting it to array.
     *
     * @return array<mixed>|string
     */
    private static function normalizeObject(object $object): array|string
    {
        if ($object instanceof \UnitEnum) {
            return $object->value ?? $object->name;
        }

        if ($object instanceof \BackedEnum) {
            return $object->value;
        }

        if (method_exists($object, 'toArray')) {
            return self::normalize($object->toArray());
        }

        if (method_exists($object, 'getValue')) {
            $value = $object->getValue();

            return is_array($value) ? self::normalize($value) : self::normalizeValue($value);
        }

        if (method_exists($object, '__toString')) {
            return (string) $object;
        }

        return self::normalize(get_object_vars($object));
    }

    /**
     * Check if a string is a boolean string.
     */
    private static function isBooleanString(string $value): bool
    {
        $lower = strtolower(trim($value));

        return $lower === 'true' || $lower === 'false';
    }

    /**
     * Check if a string is valid JSON.
     */
    private static function isJson(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
