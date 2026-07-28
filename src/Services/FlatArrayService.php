<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Services;

use InvalidArgumentException;

/**
 * Service for flattening and unflattening nested arrays.
 *
 * This service provides methods to:
 * - Flatten: Transform nested arrays into flat structure (dot notation + expanded indexed arrays)
 * - Unflatten: Reconstruct nested arrays from flat structure
 *
 * @example
 * // Flatten
 * $flat = $service->flatten(['address' => ['city' => 'Paris']]);
 * // ['address.city' => 'Paris']
 *
 * // Unflatten
 * $nested = $service->unflatten(['address.city' => 'Paris']);
 * // ['address' => ['city' => 'Paris']]
 */
final class FlatArrayService
{
    /**
     * Flattens a nested array into a flat structure.
     *
     * @param  array<string, mixed>  $array  The array to flatten
     * @param  string  $prefix  The prefix for keys (used for recursion)
     * @return array<string, int|float|string|null> The flattened array
     *
     * @throws InvalidArgumentException If an unsupported value type is encountered
     */
    public function flatten(array $array, string $prefix = ''): array
    {
        try {
            $array = normalizer_chain(true)->normalize($array);
            $result = [];
        } catch (\Throwable $th) {
            // throw $th;
            throw new InvalidArgumentException($th->getMessage());
        }

        foreach ($array as $key => $value) {
            $newKey = $prefix !== '' ? $prefix.'.'.$key : $key;

            if (is_array($value)) {
                // Cas d'un tableau associatif (clés non numériques)
                if ($this->isAssociativeArray($value)) {
                    $flattened = $this->flatten($value, $newKey);
                    $result = array_merge($result, $flattened);

                    continue;
                }

                // Cas d'un tableau indexé (liste) → on l'expand en clés séparées
                $flattened = $this->expandIndexedArray($value, $newKey);
                $result = array_merge($result, $flattened);
            } elseif (is_bool($value)) {
                $result[$newKey] = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $result[$newKey] = $value;
            } elseif ($value === null) {
                $result[$newKey] = null;
            } else {
                throw new InvalidArgumentException(
                    sprintf('Unsupported value type "%s" for key "%s". Only string, int, float, null, or indexed arrays are allowed.', gettype($value), $newKey)
                );
            }
        }

        return $result;
    }

    /**
     * Reconstructs a nested array from a flat structure.
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

        return normalizer_chain(true)->normalize($result);
    }

    /**
     * Sets a value in a nested array using dot notation.
     *
     * @param  array<string, mixed>  $array  The array to modify
     * @param  string  $key  The dot notation key
     * @param  mixed  $value  The value to set
     */
    private function setNestedValue(array &$array, string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $current = &$array;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $current[$part] = $value;
            } else {
                if (! isset($current[$part]) || ! is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }
    }

    /**
     * Expands an indexed array (list) into individual keys with "true" as value.
     *
     * @param  array<int, mixed>  $array  The indexed array
     * @param  string  $baseKey  The base key prefix
     * @return array<string, int|float|string|null> The expanded indexed array
     *
     * @throws InvalidArgumentException If an unsupported value type is encountered
     */
    private function expandIndexedArray(array $array, string $baseKey): array
    {
        // Si le tableau est vide, on retourne null
        if (empty($array)) {
            return [$baseKey => null];
        }

        $result = [];

        foreach ($array as $value) {
            // Si la valeur est un tableau, on ne supporte pas l'imbrication
            if (is_array($value)) {
                throw new InvalidArgumentException(
                    sprintf('Nested arrays are not supported for key "%s". Only flat lists are allowed.', $baseKey)
                );
            }

            // Normaliser la valeur en string pour la clé
            $keySuffix = $this->normalizeValueForKey($value);
            $newKey = $baseKey.'_'.$keySuffix;

            // La valeur est toujours 'true' pour indiquer la présence
            $result[$newKey] = 'true';
        }

        return $result;
    }

    /**
     * Normalizes a value to be used as a key suffix.
     *
     * @param  mixed  $value  The value to normalize
     * @return string The normalized key suffix
     *
     * @throws InvalidArgumentException If the value cannot be used as a key
     */
    private function normalizeValueForKey(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        throw new InvalidArgumentException(
            sprintf('Cannot use value of type "%s" as a key suffix.', gettype($value))
        );
    }

    /**
     * Checks if an array is associative (has non-numeric keys).
     *
     * @param  array<mixed, mixed>  $array  The array to check
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
}
