<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Proxies;

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

/**
 * Proxy that automatically normalizes boolean values to 'yes'/'no'
 * when creating a ClusterVO.
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
        return new ClusterVO(self::normalizeBooleans($data));
    }

    /**
     * Recursively convert boolean values and string booleans to 'yes'/'no'.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function normalizeBooleans(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $result[$key] = $value ? 'yes' : 'no';
            } elseif (is_string($value) && self::isBooleanString($value)) {
                $result[$key] = strtolower($value) === 'true' ? 'yes' : 'no';
            } elseif (is_array($value)) {
                $result[$key] = self::normalizeBooleans($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if a string is a boolean string.
     */
    private static function isBooleanString(string $value): bool
    {
        $lower = strtolower(trim($value));

        return $lower === 'true' || $lower === 'false';
    }
}
