<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use InvalidArgumentException;

final class ClusterVO extends AbstractValueObject
{
    private readonly StrictAssociative $value;

    /**
     * @param  array<string, mixed>  $value
     */
    public function __construct(array $value)
    {
        if (empty($value)) {
            throw new InvalidArgumentException('Cluster cannot be empty');
        }

        // Vérification que les clés sont des strings
        foreach (array_keys($value) as $key) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Cluster keys must be strings');
            }
        }

        // Empêcher les valeurs imbriquées (nested)
        foreach ($value as $val) {
            if (is_array($val)) {
                throw new InvalidArgumentException('Cluster values cannot be nested arrays. Only scalar values are allowed.');
            }
            if (is_object($val) && ! is_scalar($val)) {
                throw new InvalidArgumentException('Cluster values cannot be objects. Only scalar values are allowed.');
            }
        }

        $this->value = new StrictAssociative($value);
    }

    public function getValue(): StrictAssociative
    {
        return $this->value;
    }

    /**
     * Vérifie si une clé existe dans le cluster
     */
    public function has(string $key): bool
    {
        return $this->value->has($key);
    }

    /**
     * Récupère une valeur du cluster
     */
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->value->toArray();
    }
}
