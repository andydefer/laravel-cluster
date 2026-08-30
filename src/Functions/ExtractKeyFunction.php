<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

/**
 * Extracts a value from a data structure using a key path.
 *
 * This function extracts a value from the provided data using a path expression
 * and returns the extracted value. Supports nested paths with dot notation.
 *
 * @example
 * $extract = new ExtractKeyFunction();
 * $data = ['pharmacy' => ['slug' => 'pharma-a', 'name' => 'Pharmacie A']];
 * $extract->execute($data, ['slug', 'pharmacy']); // 'pharma-a'
 * @example
 * $data = ['pharmacy' => ['profile' => ['name' => 'Jean Dupont']]];
 * $extract->execute($data, ['profile.name', 'pharmacy']); // 'Jean Dupont'
 */
final class ExtractKeyFunction extends AbstractAggregateFunction
{
    /**
     * Executes the extract operation on the provided data.
     *
     * @param  array<string, mixed>  $data  The source data to extract values from
     * @param  array<int, string>  $args  Arguments: [key to extract, object path]
     * @return mixed The extracted value, or null if not found
     */
    public function execute(array $data, array $args): mixed
    {
        $key = $args[0] ?? null;
        $objectPath = $args[1] ?? null;

        dump('🔍 EXTRACT_KEY::execute');
        dump('   key: '.$key);
        dump('   objectPath: '.$objectPath);
        dump('   data: ', $data);

        if ($key === null) {
            dump('   ❌ key is null');

            return null;
        }

        // Si un objectPath est fourni, on commence par extraire l'objet
        if ($objectPath !== null) {
            $object = $this->resolveArg($data, $objectPath);
            dump('   object resolved: '.json_encode($object));
            if (! is_array($object)) {
                dump('   ❌ object is not array');

                return null;
            }
            $target = $object;
        } else {
            $target = $data;
        }

        // Support des chemins imbriqués avec notation pointée (ex: 'profile.name')
        $parts = explode('.', $key);
        $current = $target;

        dump('   parts: '.json_encode($parts));

        foreach ($parts as $part) {
            dump('   part: '.$part);
            dump('   current: '.json_encode($current));
            if (! is_array($current) || ! array_key_exists($part, $current)) {
                dump('   ❌ key "'.$part.'" not found');

                return null;
            }
            $current = $current[$part];
        }

        dump('   ✅ result: '.json_encode($current));

        return $current;
    }

    /**
     * Returns the function name as used in query expressions.
     *
     * @return string The uppercase function name
     */
    public function getName(): string
    {
        return 'EXTRACT_KEY';
    }

    /**
     * Returns the default value when the function cannot be executed.
     *
     * @return null Default fallback value
     */
    public function getDefaultValue(): mixed
    {
        return null;
    }

    /**
     * Returns the PHP type returned by the execution.
     *
     * @return string The return type name
     */
    public function getReturnType(): string
    {
        return 'mixed';
    }

    /**
     * Indicates whether this function returns a boolean result.
     *
     * @return false This function returns a mixed value
     */
    public function returnsBoolean(): bool
    {
        return false;
    }

    /**
     * Returns the minimum number of arguments required.
     *
     * @return int Minimum arguments count
     */
    public function getMinArgs(): int
    {
        return 1;
    }

    /**
     * Returns the maximum number of arguments allowed.
     *
     * @return int Maximum arguments count
     */
    public function getMaxArgs(): int
    {
        return 2;
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
        if ($count < 1 || $count > 2) {
            return false;
        }

        // Le premier argument (key) doit être une string non vide
        if (! is_string($args[0]) || empty($args[0])) {
            return false;
        }

        // Le deuxième argument (objectPath) est optionnel
        if ($count === 2) {
            if (! is_string($args[1]) || empty($args[1])) {
                return false;
            }
        }

        return true;
    }
}
