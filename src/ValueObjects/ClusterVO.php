<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\Services\FlatArrayService;
use ArrayAccess;
use InvalidArgumentException;

/**
 * Value Object representing a cluster of data for query evaluation.
 *
 * A ClusterVO encapsulates a nested array structure that is both flattened
 * and unflattened, providing efficient access to data by key paths.
 *
 * The cluster data is:
 * - Validated at construction for supported types
 * - Flattened to dot notation for fast key lookups
 * - Preserved in original nested form for complete data access
 *
 * @example
 * $cluster = new ClusterVO([
 *     'user' => ['name' => 'John', 'age' => 30],
 *     'roles' => ['admin', 'editor']
 * ]);
 *
 * // Access flattened values
 * $name = $cluster['user.name']; // 'John'
 * $hasRole = $cluster->has('roles_admin'); // true
 *
 * // Access the full data
 * $flat = $cluster->toArray();
 * $nested = $cluster->getUnflattened();
 *
 * @implements ArrayAccess<string, mixed>
 */
class ClusterVO extends AbstractValueObject implements ArrayAccess
{
    protected readonly StrictAssociative $flattenedData;

    protected readonly StrictAssociative $nestedData;

    protected readonly FlatArrayService $flatArrayService;

    protected readonly array $originalNestedData;

    /**
     * @param  array<string, mixed>  $data  The cluster data
     *
     * @throws InvalidArgumentException If data contains unsupported types or is empty
     */
    final public function __construct(array $data)
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Cluster cannot be empty');
        }

        $this->flatArrayService = new FlatArrayService;
        $this->validateInputData($data);

        $flattened = $this->flatArrayService->flatten($data);
        $this->validateFlattenedData($flattened);

        $this->originalNestedData = $data;
        $this->flattenedData = new StrictAssociative($flattened);
        $this->nestedData = new StrictAssociative(
            $this->flatArrayService->unflatten($flattened)
        );
    }

    /**
     * Returns the flattened representation of the cluster data.
     *
     * @return StrictAssociative The flattened data with dot-notated keys
     */
    final public function getValue(): StrictAssociative
    {
        return $this->getUnflattened();
    }

    /**
     * Returns the nested (unflattened) representation of the cluster data.
     * Automatically decodes JSON strings to arrays.
     *
     * @return StrictAssociative The original nested structure with JSON decoded
     */
    final public function getUnflattened(): StrictAssociative
    {
        $data = $this->originalNestedData;
        $decoded = $this->decodeJsonValues($data);

        return new StrictAssociative($decoded);
    }

    /**
     * Returns the nested (unflattened) representation as an array.
     * Automatically decodes JSON strings to arrays.
     */
    final public function getNestedData(): array
    {
        return $this->decodeJsonValues($this->originalNestedData);
    }

    /**
     * Recursively decodes JSON strings in an array.
     *
     * @param  array<mixed>  $data  The data to decode
     * @return array<mixed> The data with JSON strings decoded
     */
    private function decodeJsonValues(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->decodeJsonValues($value);
            } elseif (is_string($value) && $this->isJson($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (is_array($decoded)) {
                        $result[$key] = $this->decodeJsonValues($decoded);
                    } else {
                        $result[$key] = $decoded;
                    }
                } else {
                    $result[$key] = $value;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Checks if a string is valid JSON.
     *
     * @param  string  $string  The string to check
     * @return bool True if the string is valid JSON
     */
    private function isJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Checks if a key exists in the flattened data.
     *
     * @param  string  $key  The dot-notated key to check
     * @return bool True if the key exists
     */
    final public function has(string $key): bool
    {
        return $this->flattenedData->has($key);
    }

    /**
     * Retrieves a value from the flattened data by key.
     *
     * @param  string  $key  The dot-notated key
     * @param  mixed  $default  The default value if key doesn't exist
     * @return mixed The value or default
     */
    final public function get(string $key, mixed $default = null): mixed
    {
        return $this->flattenedData->get($key, $default);
    }

    /**
     * Returns all keys from the flattened data.
     *
     * @return array<int, string> The list of dot-notated keys
     */
    final public function keys(): array
    {
        return array_keys($this->flattenedData->toArray());
    }

    /**
     * Returns the flattened data as an array.
     *
     * @return array<string, int|float|string|null> The flattened data
     */
    final public function toArray(): array
    {
        return $this->flattenedData->toArray();
    }

    // ==================== ArrayAccess Implementation ====================

    /**
     * {@inheritDoc}
     */
    final public function offsetExists(mixed $offset): bool
    {
        if (! is_string($offset)) {
            return false;
        }

        return $this->has($offset);
    }

    /**
     * {@inheritDoc}
     */
    final public function offsetGet(mixed $offset): mixed
    {
        if (! is_string($offset)) {
            return null;
        }

        return $this->get($offset);
    }

    /**
     * {@inheritDoc}
     */
    final public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \RuntimeException('ClusterVO is immutable. Use toArray() and create a new instance to modify.');
    }

    /**
     * {@inheritDoc}
     */
    final public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException('ClusterVO is immutable. Use toArray() and create a new instance to modify.');
    }

    // ==================== Validation Methods ====================

    /**
     * Validates the input data before flattening.
     *
     * Ensures all keys are strings and values are of supported types.
     * Objects (except stdClass) and resources are not allowed.
     *
     * @param  array<string, mixed>  $data  The input data to validate
     *
     * @throws InvalidArgumentException If validation fails
     */
    private function validateInputData(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->validateKeyIsString($key);

            if (is_object($value) && ! $value instanceof \stdClass) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Cluster values must be string, int, float, bool, array or null. Got object for key "%s"',
                        $key
                    )
                );
            }

            if (is_resource($value)) {
                throw new InvalidArgumentException(
                    sprintf('Cluster values cannot be resources. Got resource for key "%s"', $key)
                );
            }
        }
    }

    /**
     * Validates the flattened data structure.
     *
     * Ensures all keys are strings and values are of the expected simple types.
     *
     * @param  array<string, mixed>  $data  The flattened data to validate
     *
     * @throws InvalidArgumentException If validation fails
     */
    private function validateFlattenedData(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->validateKeyIsString($key);
            $this->validateFlattenedValueType($value, $key);
        }
    }

    /**
     * Validates that a key is a string.
     *
     * @param  mixed  $key  The key to validate
     *
     * @throws InvalidArgumentException If the key is not a string
     */
    private function validateKeyIsString(mixed $key): void
    {
        if (! is_string($key)) {
            throw new InvalidArgumentException('Cluster keys must be strings');
        }
    }

    /**
     * Validates the type of a flattened value.
     *
     * @param  mixed  $value  The value to validate
     * @param  string  $key  The key for error messages
     *
     * @throws InvalidArgumentException If the value type is invalid
     */
    private function validateFlattenedValueType(mixed $value, string $key): void
    {
        $validTypes = ['string', 'integer', 'double', 'NULL'];

        if (! in_array(gettype($value), $validTypes, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cluster values must be string, int, float or null after flatten. Got %s for key "%s"',
                    gettype($value),
                    $key
                )
            );
        }
    }
}
