<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\Services\FlatArrayService;
use InvalidArgumentException;

final class ClusterVO extends AbstractValueObject
{
    private readonly StrictAssociative $value;

    private readonly StrictAssociative $unflattenedValue;

    private readonly FlatArrayService $flatArrayService;

    /**
     * @param  array<string, mixed>  $value
     */
    public function __construct(array $value)
    {
        if (empty($value)) {
            throw new InvalidArgumentException('Cluster cannot be empty');
        }

        // Validation des types avant flatten
        $this->validateInput($value);

        $this->flatArrayService = new FlatArrayService;

        // Flatten le tableau
        $flattened = $this->flatArrayService->flatten($value);

        // Valider le résultat flatten
        $this->validateFlattened($flattened);

        $this->value = new StrictAssociative($flattened);

        // Stocker la version unflattened
        $unflattened = $this->flatArrayService->unflatten($flattened);
        $this->unflattenedValue = new StrictAssociative($unflattened);
    }

    public function getValue(): StrictAssociative
    {
        return $this->value;
    }

    public function getUnflattened(): StrictAssociative
    {
        return $this->unflattenedValue;
    }

    public function has(string $key): bool
    {
        return $this->value->has($key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->value->get($key, $default);
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->value->toArray());
    }

    /**
     * @return array<string, int|float|string|null>
     */
    public function toArray(): array
    {
        return $this->value->toArray();
    }

    /**
     * Valide les types avant flatten
     *
     * @param  array<string, mixed>  $data
     */
    private function validateInput(array $data): void
    {
        foreach ($data as $key => $val) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Cluster keys must be strings');
            }

            // Les booléens sont autorisés (ils seront convertis en 'true'/'false')
            // Les tableaux sont autorisés (ils seront flatten)
            // Les objets sont interdits
            if (is_object($val) && ! $val instanceof \stdClass) {
                throw new InvalidArgumentException(
                    sprintf('Cluster values must be string, int, float, bool, array or null. Got object for key "%s"', $key)
                );
            }

            // Les ressources sont interdites
            if (is_resource($val)) {
                throw new InvalidArgumentException(
                    sprintf('Cluster values cannot be resources. Got resource for key "%s"', $key)
                );
            }
        }
    }

    /**
     * Valide les types après flatten
     *
     * @param  array<string, mixed>  $data
     */
    private function validateFlattened(array $data): void
    {
        foreach ($data as $key => $val) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Cluster keys must be strings');
            }

            // Après flatten, on doit avoir string, int, float ou null
            if (! is_string($val) && ! is_int($val) && ! is_float($val) && $val !== null) {
                throw new InvalidArgumentException(
                    sprintf('Cluster values must be string, int, float or null after flatten. Got %s for key "%s"', gettype($val), $key)
                );
            }
        }
    }
}
