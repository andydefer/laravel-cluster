<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Services;

/**
 * Service for flattening nested arrays with specific rules.
 *
 * This service transforms nested arrays into a flat structure where:
 * - Simple key-value pairs are preserved (key => value)
 * - Nested arrays become dot-notation keys (parent.child => value)
 * - Indexed arrays become keys with underscore and index (tags_0 => value)
 * - Empty indexed arrays become null
 * - Associative arrays are NOT allowed as values (throws exception)
 *
 * @example
 * // Input
 * [
 *     'name' => 'Dupont',
 *     'tags' => ['php', 'js', 'kotlin'],
 *     'address' => ['city' => 'Lyon', 'postal_code' => '69000']
 * ]
 *
 * // Output
 * [
 *     'name' => 'Dupont',
 *     'tags_0' => 'php',
 *     'tags_1' => 'js',
 *     'tags_2' => 'kotlin',
 *     'address.city' => 'Lyon',
 *     'address.postal_code' => '69000'
 * ]
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
     * @throws \InvalidArgumentException If a value is an associative array (nested object)
     */
    public function flatten(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix !== '' ? $prefix.'.'.$key : $key;

            if (is_array($value)) {
                // Cas d'un tableau associatif (clés non numériques)
                if ($this->isAssociativeArray($value)) {
                    // On continue à flatten les tableaux associatifs
                    $flattened = $this->flatten($value, $newKey);
                    $result = array_merge($result, $flattened);

                    continue;
                }

                // Cas d'un tableau indexé (liste)
                $flattened = $this->flattenIndexedArray($value, $newKey);
                $result = array_merge($result, $flattened);
            } elseif (is_bool($value)) {
                // Convertir les booléens en chaînes 'true' ou 'false'
                $result[$newKey] = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                // Garder les scalaires (string, int, float) tels quels
                $result[$newKey] = $value;
            } elseif ($value === null) {
                $result[$newKey] = null;
            } else {
                throw new \InvalidArgumentException(
                    sprintf('Unsupported value type "%s" for key "%s". Only string, int, float, null, or indexed arrays are allowed.', gettype($value), $newKey)
                );
            }
        }

        return $result;
    }

    /**
     * Flattens an indexed array (list) into individual keys.
     *
     * @param  array<int, mixed>  $array  The indexed array
     * @param  string  $baseKey  The base key prefix
     * @return array<string, int|float|string|null> The flattened indexed array
     *
     * @throws \InvalidArgumentException If an indexed array is empty
     */
    private function flattenIndexedArray(array $array, string $baseKey): array
    {
        // Si le tableau est vide, on retourne null
        if (empty($array)) {
            return [$baseKey => null];
        }

        $result = [];

        foreach ($array as $index => $value) {
            $newKey = $baseKey.'_'.$index;

            if (is_array($value)) {
                // Vérifier si c'est un tableau associatif dans un tableau indexé
                if ($this->isAssociativeArray($value)) {
                    // On continue à flatten les tableaux associatifs
                    $flattened = $this->flatten($value, $newKey);
                    $result = array_merge($result, $flattened);

                    continue;
                }

                // Vérifier si c'est un tableau indexé imbriqué
                if (! $this->isIndexedArray($value)) {
                    throw new \InvalidArgumentException(
                        sprintf('Unsupported nested structure for key "%s". Only flat indexed arrays are supported.', $newKey)
                    );
                }

                // Cas d'un tableau indexé imbriqué (on le traite récursivement)
                throw new \InvalidArgumentException(
                    sprintf('Nested indexed arrays are not supported for key "%s".', $newKey)
                );
            } elseif (is_bool($value)) {
                $result[$newKey] = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $result[$newKey] = $value;
            } elseif ($value === null) {
                $result[$newKey] = null;
            } else {
                throw new \InvalidArgumentException(
                    sprintf('Unsupported value type "%s" for key "%s".', gettype($value), $newKey)
                );
            }
        }

        return $result;
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

    /**
     * Checks if an array is an indexed array (all keys are numeric and sequential).
     *
     * @param  array<mixed, mixed>  $array  The array to check
     * @return bool True if the array is indexed
     */
    private function isIndexedArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }

        $keys = array_keys($array);

        return $keys === range(0, count($array) - 1);
    }

    /**
     * Flattens a nested array and normalizes values.
     *
     * @param  array<string, mixed>  $array  The array to flatten
     * @param  string  $prefix  The prefix for keys
     * @return array<string, int|float|string|null> The flattened normalized array
     */
    public function flattenAndNormalize(array $array, string $prefix = ''): array
    {
        $flattened = $this->flatten($array, $prefix);

        // Normaliser les valeurs (convertir les booléens, etc.)
        $normalized = [];
        foreach ($flattened as $key => $value) {
            if (is_bool($value)) {
                $normalized[$key] = $value ? 'true' : 'false';
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
