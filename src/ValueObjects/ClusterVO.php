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
     * @param  array<string, int|float|string|null>  $value
     */
    public function __construct(array $value)
    {
        if (empty($value)) {
            throw new InvalidArgumentException('Cluster cannot be empty');
        }

        foreach ($value as $key => $val) {
            // Les clés doivent être des strings
            if (! is_string($key)) {
                throw new InvalidArgumentException('Cluster keys must be strings');
            }

            // Les valeurs doivent être string, int, float ou null
            if (! is_string($val) && ! is_int($val) && ! is_float($val) && $val !== null) {
                throw new InvalidArgumentException(
                    sprintf('Cluster values must be string, int, float or null. Got %s for key "%s"', gettype($val), $key)
                );
            }
        }

        $this->value = new StrictAssociative($value);
    }

    public function getValue(): StrictAssociative
    {
        return $this->value;
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
}
