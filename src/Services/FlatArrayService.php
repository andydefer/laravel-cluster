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
 * // ['roles_admin' => 'true', 'roles_user' => 'true']
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
     * - Booleans: converted to 'true' or 'false' strings
     * - Scalars: kept as-is
     * - Null: kept as null
     *
     * @param  array<string, mixed>  $array  The array to flatten
     * @param  string  $prefix  The key prefix for recursion (internal use)
     * @return array<string, int|float|string|null> The flattened array
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

        foreach ($flat as $key => $value) {
            $this->setNestedValue($result, $key, $value);
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
     */
    private function flattenArrayValue(array $value, string $newKey): array
    {
        // Si c'est un tableau associatif, on le flatten normalement
        if ($this->isAssociativeArray($value)) {
            return $this->flatten($value, $newKey);
        }

        // Si c'est un tableau indexé avec des tableaux imbriqués, on le JSON encode
        if ($this->hasNestedArrays($value)) {
            return [$newKey => json_encode($value)];
        }

        // Sinon, on l'expand normalement
        return $this->expandIndexedArray($value, $newKey);
    }

    /**
     * Normalizes a scalar value for flattening.
     *
     * @param  mixed  $value  The value to normalize
     * @param  string  $key  The key for error messages
     * @return int|float|string|null The normalized value
     */
    private function normalizeScalarValue(mixed $value, string $key): int|float|string|null
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        // Fallback: convertir en JSON pour tout autre type
        return json_encode($value);
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
                // Si la valeur est un JSON string, on la décode
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
     * Expands an indexed array into individual keys with "true" as value.
     *
     * Converts ['admin', 'user'] to ['roles_admin' => 'true', 'roles_user' => 'true'].
     *
     * @param  array<int, mixed>  $array  The indexed array
     * @param  string  $baseKey  The base key prefix
     * @return array<string, int|float|string|null> The expanded array
     */
    private function expandIndexedArray(array $array, string $baseKey): array
    {
        if (empty($array)) {
            return [$baseKey => null];
        }

        $result = [];

        foreach ($array as $value) {
            if (is_array($value)) {
                // Si un tableau imbriqué est trouvé, on le JSON encode
                $keySuffix = $this->normalizeValueForKey($value);
                $newKey = $baseKey.'_'.$keySuffix;
                $result[$newKey] = json_encode($value);

                continue;
            }

            $keySuffix = $this->normalizeValueForKey($value);
            $newKey = $baseKey.'_'.$keySuffix;
            $result[$newKey] = 'true';
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
            is_bool($value) => $value ? 'true' : 'false',
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
