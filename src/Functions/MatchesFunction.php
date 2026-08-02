<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Searches for a value matching a regular expression within an array or array of items.
 *
 * This function supports two usage patterns:
 * - With 2 arguments: Searches for a regex match within an array of string values
 * - With 3 arguments: Searches for a regex match on a specific key within an array of objects/arrays
 */
final class MatchesFunction extends AbstractAggregateFunction
{
    /**
     * Executes the regex search operation on the provided data.
     *
     * @param  array<string, mixed>  $data  The source data to extract values from
     * @param  array<int, string>  $args  Arguments: [path, key, pattern] (pattern is optional with 2 args)
     * @return bool True if the regex pattern matches any value
     */
    public function execute(array $data, array $args): bool
    {
        $path = $args[0] ?? null;
        $key = $args[1] ?? null;
        $pattern = $args[2] ?? null;

        if ($path === null) {
            return false;
        }

        $items = $this->extractValueWithIndexedSupport($data, $path);

        // Si on a 2 arguments, le deuxième est le pattern
        if ($pattern === null && $key !== null) {
            $pattern = $key;
            $key = null;
        }

        // Si items est une string, la traiter comme un seul élément
        if (is_string($items)) {
            return $this->matchRegex($items, $pattern ?? '');
        }

        if (! is_array($items)) {
            return false;
        }

        // Vérifier si on a un tableau indexé de strings ou un tableau d'objets
        if (isset($items[0]) && is_string($items[0])) {
            // Tableau de strings
            return $this->searchValuesWithRegex($items, $pattern ?? '');
        }

        // Tableau d'objets/arrays
        if ($key !== null && $pattern !== null) {
            return $this->searchKeyValueWithRegex($items, $key, $pattern);
        }

        // Recherche dans les valeurs du tableau
        return $this->searchValuesWithRegex($items, $pattern ?? '');
    }

    /**
     * Extracts values from data with support for indexed arrays in paths.
     *
     * @param  array<string, mixed>  $data  The source data
     * @param  string  $path  The dot notation path (supports indexed arrays)
     * @return mixed The extracted values
     */
    private function extractValueWithIndexedSupport(array $data, string $path): mixed
    {
        $parts = explode('.', $path);
        $current = $data;

        foreach ($parts as $part) {
            // Si on est dans un tableau indexé, on parcourt tous les éléments
            if (is_array($current) && isset($current[0]) && ! isset($current[$part])) {
                $results = [];
                foreach ($current as $item) {
                    if (is_array($item) && isset($item[$part])) {
                        $results[] = $item[$part];
                    }
                }

                return $results;
            }

            // Sinon, accès normal
            if (! isset($current[$part])) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }

    /**
     * Returns the function name as used in query expressions.
     *
     * @return string The uppercase function name
     */
    public function getName(): string
    {
        return 'MATCHES';
    }

    /**
     * Returns the default value when the function cannot be executed.
     *
     * @return bool Default fallback value
     */
    public function getDefaultValue(): mixed
    {
        return false;
    }

    /**
     * Returns the PHP type returned by the execution.
     *
     * @return string The return type name
     */
    public function getReturnType(): string
    {
        return 'bool';
    }

    /**
     * Indicates whether this function returns a boolean result.
     *
     * @return true This function always returns a boolean
     */
    public function returnsBoolean(): bool
    {
        return true;
    }

    /**
     * Returns the minimum number of arguments required.
     *
     * @return int Minimum arguments count
     */
    public function getMinArgs(): int
    {
        return 2;
    }

    /**
     * Returns the maximum number of arguments allowed.
     *
     * @return int Maximum arguments count
     */
    public function getMaxArgs(): int
    {
        return 3;
    }

    /**
     * Validates the provided arguments for this function.
     *
     * @param  array<int, mixed>  $args  The arguments to validate
     * @return bool True if arguments are valid
     */
    public function validateArgs(array $args): bool
    {
        $count = count($args);

        return $count >= 2 && $count <= 3;
    }

    /**
     * Searches for a regex match in an array of values.
     *
     * @param  array<mixed>  $items  The array to search
     * @param  string  $pattern  The regex pattern to match
     * @return bool True if any value matches the pattern
     */
    private function searchValuesWithRegex(array $items, string $pattern): bool
    {
        if (empty($pattern)) {
            return false;
        }

        foreach ($items as $item) {
            if (is_string($item) && $this->matchRegex($item, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Searches for a regex match on a specific key in an array of objects/arrays.
     *
     * @param  array<mixed>  $items  The array to search
     * @param  string  $key  The key to check
     * @param  string  $pattern  The regex pattern to match
     * @return bool True if any item has the key with a value matching the pattern
     */
    private function searchKeyValueWithRegex(array $items, string $key, string $pattern): bool
    {
        if (empty($key) || empty($pattern)) {
            return false;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! isset($item[$key])) {
                continue;
            }

            $value = $item[$key];

            if (is_string($value) && $this->matchRegex($value, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a string matches a regular expression pattern.
     *
     * @param  string  $value  The value to test
     * @param  string  $pattern  The regex pattern (must include delimiters)
     * @return bool True if the value matches the pattern
     */
    private function matchRegex(string $value, string $pattern): bool
    {
        $pattern = trim($pattern, '"\' ');

        // Vérifier que le pattern est valide
        if (@preg_match($pattern, '') === false) {
            return false;
        }

        return preg_match($pattern, $value) === 1;
    }
}
