<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Services;

use InvalidArgumentException;
use Throwable;

/**
 * Transforms nested arrays between flat and nested structures.
 *
 * This service provides bidirectional conversion between nested arrays and
 * flat dot-notated structures. It handles:
 * - Flattening: Nested arrays → Dot notation + expanded indexed arrays
 * - Unflattening: Dot notation → Nested arrays
 *
 * The flattened format is designed for use in data structures where keys need
 * to be unique and values need to be simple (scalars or null).
 *
 * @example
 * // Flatten a nested array
 * $flat = $service->flatten([
 *     'user' => [
 *         'name' => 'John',
 *         'age' => 30
 *     ]
 * ]);
 * // ['user.name' => 'John', 'user.age' => 30]
 *
 * // Flatten with indexed arrays
 * $flat = $service->flatten(['roles' => ['admin', 'user']]);
 * // ['roles_admin' => 'yes', 'roles_user' => 'yes']
 *
 * // Unflatten back to nested structure
 * $nested = $service->unflatten(['user.name' => 'John']);
 * // ['user' => ['name' => 'John']]
 */
final class FlatArrayService
{
    /**
     * Flattens a nested array into a flat structure with dot notation.
     *
     * - Associative arrays: keys become dot-notated paths
     * - Indexed arrays: expanded into separate keys with "_value" suffix
     * - Nested arrays are JSON encoded to preserve structure
     * - Scalars: kept as-is (string, int, float)
     * - Null: kept as null
     * - Booleans: NOT ALLOWED (will throw exception)
     *
     * @param  array<string, mixed>  $array  The array to flatten
     * @param  string  $prefix  The key prefix for recursion (internal use)
     * @return array<string, int|float|string|null> The flattened array
     *
     * @throws InvalidArgumentException If a boolean is encountered
     */
    public function flatten(array $array, string $prefix = ''): array
    {
        $normalized = $this->normalizeArray($array);
        $result = [];

        foreach ($normalized as $key => $value) {
            $newKey = $this->buildPrefixedKey($prefix, $key);

            if (is_array($value)) {
                $flattened = $this->flattenArrayValue($value, $newKey);
                $result = array_merge($result, $flattened);

                continue;
            }

            $result[$newKey] = $this->normalizeScalarValue($value, $newKey);
        }

        return $result;
    }

    /**
     * Reconstructs a nested array from a flat dot-notated structure.
     *
     * @param  array<string, int|float|string|null>  $flat  The flat array
     * @return array<string, mixed> The reconstructed nested array
     */
    public function unflatten(array $flat): array
    {
        $result = [];
        $expandedArrays = [];
        $processedKeys = [];

        foreach ($flat as $key => $value) {
            if (str_contains($key, '_')) {
                $parts = explode('_', $key);
                $baseKey = $parts[0];

                if (count($parts) === 2 && ! isset($flat[$baseKey])) {
                    $pattern = '/^'.preg_quote($baseKey, '/').'_/';
                    $count = 0;
                    $suffixes = [];

                    foreach ($flat as $k => $v) {
                        if (preg_match($pattern, $k) && ($v === 'yes' || $v === 'true' || $v === true)) {
                            $count++;
                            $suffix = substr($k, strlen($baseKey) + 1);
                            $suffixes[] = $suffix;
                        }
                    }

                    if ($count >= 2) {
                        if (! isset($expandedArrays[$baseKey])) {
                            $expandedArrays[$baseKey] = [];
                        }
                        $expandedArrays[$baseKey] = array_merge($expandedArrays[$baseKey], $suffixes);
                        $processedKeys[] = $key;
                    }
                }
            }
        }

        foreach ($flat as $key => $value) {
            $isExpanded = false;
            foreach ($expandedArrays as $baseKey => $values) {
                if (str_starts_with($key, $baseKey.'_')) {
                    $isExpanded = true;
                    break;
                }
            }

            if (! $isExpanded) {
                $this->setNestedValue($result, $key, $value);
            }
        }

        foreach ($expandedArrays as $baseKey => $values) {
            $uniqueValues = array_values(array_unique($values));
            $this->setNestedValue($result, $baseKey, $uniqueValues);
        }

        return $this->normalizeArray($result);
    }

    /**
     * Normalizes an array using the global normalizer chain.
     *
     * @param  array<string, mixed>  $array  The array to normalize
     * @return array<string, mixed> The normalized array
     *
     * @throws InvalidArgumentException If normalization fails
     */
    private function normalizeArray(array $array): array
    {
        try {
            return normalizer_chain(true)->normalize($array);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Builds a prefixed key for flattened arrays.
     *
     * @param  string  $prefix  The current prefix
     * @param  string|int  $key  The current key
     * @return string The combined key with dot notation
     */
    private function buildPrefixedKey(string $prefix, string|int $key): string
    {
        $key = (string) $key;

        return $prefix !== '' ? $prefix.'.'.$key : $key;
    }

    /**
     * Flattens an array value (associative or indexed).
     *
     * @param  array<mixed>  $value  The array value to flatten
     * @param  string  $newKey  The current key path
     * @return array<string, int|float|string|null> The flattened array
     *
     * @throws InvalidArgumentException If a boolean is encountered
     */
    private function flattenArrayValue(array $value, string $newKey): array
    {
        if ($this->isAssociativeArray($value)) {
            return $this->flatten($value, $newKey);
        }

        if ($this->hasNestedArrays($value)) {
            $this->validateNoBooleans($value);

            return [$newKey => json_encode($value)];
        }

        return $this->expandIndexedArray($value, $newKey);
    }

    /**
     * Normalizes a scalar value for flattening.
     *
     * @param  mixed  $value  The value to normalize
     * @param  string  $key  The key for error messages
     * @return int|float|string|null The normalized value
     *
     * @throws InvalidArgumentException If a boolean is encountered
     */
    private function normalizeScalarValue(mixed $value, string $key): int|float|string|null
    {
        if (is_bool($value)) {
            throw new InvalidArgumentException(
                sprintf('Boolean values are not allowed. Got bool for key "%s"', $key)
            );
        }

        if (is_array($value)) {
            $this->validateNoBooleans($value);

            return json_encode($value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return json_encode($value);
    }

    /**
     * Validates that an array contains no booleans.
     *
     * @param  array<mixed>  $array  The array to validate
     *
     * @throws InvalidArgumentException If a boolean is found
     */
    private function validateNoBooleans(array $array): void
    {
        foreach ($array as $key => $value) {
            if (is_bool($value)) {
                throw new InvalidArgumentException(
                    sprintf('Boolean values are not allowed in arrays. Found bool at key "%s"', $key)
                );
            }
            if (is_array($value)) {
                $this->validateNoBooleans($value);
            }
        }
    }

    /**
     * Sets a nested value in an array using dot notation.
     *
     * @param  array<string, mixed>  $array  The array to modify (by reference)
     * @param  string  $key  The dot-notated key path
     * @param  mixed  $value  The value to set
     */
    private function setNestedValue(array &$array, string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $current = &$array;

        foreach ($parts as $index => $part) {
            $isLastPart = $index === count($parts) - 1;

            if ($isLastPart) {
                if (is_string($value) && $this->isJson($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $current[$part] = $decoded;
                        break;
                    }
                }
                $current[$part] = $value;
                break;
            }

            if (! isset($current[$part]) || ! is_array($current[$part])) {
                $current[$part] = [];
            }

            $current = &$current[$part];
        }
    }

    /**
     * Expands an indexed array into individual keys with "yes" as value.
     *
     * Converts ['admin', 'user'] to ['roles_admin' => 'yes', 'roles_user' => 'yes'].
     *
     * @param  array<int, mixed>  $array  The indexed array
     * @param  string  $baseKey  The base key prefix
     * @return array<string, int|float|string|null> The expanded array
     *
     * @throws InvalidArgumentException If a boolean is encountered in the array
     */
    private function expandIndexedArray(array $array, string $baseKey): array
    {
        if (empty($array)) {
            return [$baseKey => null];
        }

        $result = [];

        foreach ($array as $value) {
            if (is_bool($value)) {
                throw new InvalidArgumentException(
                    sprintf('Boolean values are not allowed in indexed arrays. Found bool in array "%s"', $baseKey)
                );
            }

            if (is_array($value)) {
                $this->validateNoBooleans($value);
                $keySuffix = $this->normalizeValueForKey($value);
                $newKey = $baseKey.'_'.$keySuffix;
                $result[$newKey] = json_encode($value);

                continue;
            }

            $keySuffix = $this->normalizeValueForKey($value);
            $newKey = $baseKey.'_'.$keySuffix;
            $result[$newKey] = 'yes';
        }

        return $result;
    }

    /**
     * Normalizes a value to be used as a key suffix.
     *
     * @param  mixed  $value  The value to normalize
     * @return string The normalized key suffix
     */
    private function normalizeValueForKey(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value) || is_float($value) => (string) $value,
            is_bool($value) => throw new InvalidArgumentException(
                sprintf('Boolean values are not allowed. Got bool for key suffix')
            ),
            is_array($value) => 'array',
            $value === null => 'null',
            default => 'value',
        };
    }

    /**
     * Determines if an array is associative (has non-numeric keys).
     *
     * @param  array<mixed>  $array  The array to check
     * @return bool True if the array is associative
     */
    private function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        foreach (array_keys($array) as $key) {
            if (! is_int($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if an array contains nested arrays.
     *
     * @param  array<mixed>  $array  The array to check
     * @return bool True if the array contains nested arrays
     */
    private function hasNestedArrays(array $array): bool
    {
        foreach ($array as $value) {
            if (is_array($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a string is a valid JSON.
     *
     * @param  string  $string  The string to check
     * @return bool True if the string is valid JSON
     */
    private function isJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
