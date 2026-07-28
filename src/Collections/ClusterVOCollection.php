<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

final class ClusterVOCollection extends AbstractTypedCollection
{
    private array $originalItems = [];

    public function __construct()
    {
        parent::__construct(ClusterVO::class);
    }

    private function initializeOriginalItems(): void
    {
        if (empty($this->originalItems)) {
            $this->originalItems = $this->items;
        }
    }

    private function getOriginalItems(): array
    {
        $this->initializeOriginalItems();

        return $this->originalItems;
    }

    private function createResult(array $items): self
    {
        $result = new self;

        foreach ($items as $item) {
            $result->add($item);
        }

        $result->originalItems = $this->getOriginalItems();

        return $result;
    }

    private function hasPriorFilter(): bool
    {
        return count($this->items) < count($this->getOriginalItems());
    }

    private function getClusterIdentifier(ClusterVO $cluster): int
    {
        return spl_object_id($cluster);
    }

    private function clusterExistsInResult(ClusterVO $cluster, self $result): bool
    {
        $clusterId = $this->getClusterIdentifier($cluster);
        foreach ($result->all() as $item) {
            if ($this->getClusterIdentifier($item) === $clusterId) {
                return true;
            }
        }

        return false;
    }

    public function where(string $key, mixed $value): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->get($key) === $value) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function andWhere(string $key, mixed $value): self
    {
        return $this->where($key, $value);
    }

    public function whereNot(string $key, mixed $value): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->get($key) !== $value) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereTrue(string $key): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->get($key) === true) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereFalse(string $key): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->get($key) === false) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function orWhere(string $key, mixed $value): self
    {
        $filtered = [];
        $addedIds = [];
        $originalItems = $this->getOriginalItems();

        if ($this->hasPriorFilter()) {
            foreach ($this->items as $cluster) {
                $id = $this->getClusterIdentifier($cluster);
                if (! in_array($id, $addedIds, true)) {
                    $filtered[] = $cluster;
                    $addedIds[] = $id;
                }
            }
        }

        foreach ($originalItems as $cluster) {
            $id = $this->getClusterIdentifier($cluster);
            if ($cluster->get($key) === $value && ! in_array($id, $addedIds, true)) {
                $filtered[] = $cluster;
                $addedIds[] = $id;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereGroup(callable $callback): self
    {
        $filtered = [];
        $originalItems = $this->getOriginalItems();

        foreach ($this->items as $cluster) {
            $tempCollection = new self;
            $tempCollection->add($cluster);
            $tempCollection->originalItems = $originalItems;

            $result = $callback($tempCollection);

            if ($this->clusterExistsInResult($cluster, $result)) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function orWhereGroup(callable $callback): self
    {
        $filtered = [];
        $addedIds = [];
        $originalItems = $this->getOriginalItems();

        if ($this->hasPriorFilter()) {
            foreach ($this->items as $cluster) {
                $id = $this->getClusterIdentifier($cluster);
                if (! in_array($id, $addedIds, true)) {
                    $filtered[] = $cluster;
                    $addedIds[] = $id;
                }
            }
        }

        foreach ($originalItems as $cluster) {
            $id = $this->getClusterIdentifier($cluster);
            if (! in_array($id, $addedIds, true)) {
                $tempCollection = new self;
                $tempCollection->add($cluster);
                $tempCollection->originalItems = $originalItems;

                $result = $callback($tempCollection);

                if ($this->clusterExistsInResult($cluster, $result)) {
                    $filtered[] = $cluster;
                    $addedIds[] = $id;
                }
            }
        }

        return $this->createResult($filtered);
    }

    public function whereHas(string $key): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->has($key)) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereMissing(string $key): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if (! $cluster->has($key)) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereIn(string $key, array $values): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if (in_array($cluster->get($key), $values, true)) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereNotIn(string $key, array $values): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if (! in_array($cluster->get($key), $values, true)) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereGreaterThan(string $key, int|float $value): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $val = $cluster->get($key);
            if (is_numeric($val) && (float) $val > $value) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereGreaterThanOrEqual(string $key, int|float $value): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $val = $cluster->get($key);
            if (is_numeric($val) && (float) $val >= $value) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereLessThan(string $key, int|float $value): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $val = $cluster->get($key);
            if (is_numeric($val) && (float) $val < $value) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereLessThanOrEqual(string $key, int|float $value): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $val = $cluster->get($key);
            if (is_numeric($val) && (float) $val <= $value) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereBetween(string $key, mixed $min, mixed $max): self
    {
        if (! is_numeric($min) || ! is_numeric($max)) {
            return $this->createResult([]);
        }

        $filtered = [];

        foreach ($this->items as $cluster) {
            $val = $cluster->get($key);
            if (is_numeric($val) && $val >= $min && $val <= $max) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereNotBetween(string $key, mixed $min, mixed $max): self
    {
        if (! is_numeric($min) || ! is_numeric($max)) {
            return $this->createResult($this->items);
        }

        $filtered = [];

        foreach ($this->items as $cluster) {
            $val = $cluster->get($key);
            if (! is_numeric($val) || $val < $min || $val > $max) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereNull(string $key): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->get($key) === null) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereNotNull(string $key): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->get($key) !== null) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereContains(string $key, string $search): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $value = $cluster->get($key);
            if (is_string($value) && stripos($value, $search) !== false) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereStartsWith(string $key, string $prefix): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $value = $cluster->get($key);
            if (is_string($value) && str_starts_with($value, $prefix)) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereEndsWith(string $key, string $suffix): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $value = $cluster->get($key);
            if (is_string($value) && str_ends_with($value, $suffix)) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function whereClosure(callable $callback): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($callback($cluster)) {
                $filtered[] = $cluster;
            }
        }

        return $this->createResult($filtered);
    }

    public function orWhereClosure(callable $callback): self
    {
        $filtered = [];
        $addedIds = [];
        $currentItems = $this->items;

        // Si un filtre préalable existe, garder les items déjà présents
        if ($this->hasPriorFilter()) {
            foreach ($currentItems as $cluster) {
                $id = $this->getClusterIdentifier($cluster);
                $filtered[] = $cluster;
                $addedIds[] = $id;
            }
        }

        // Ajouter les items de la collection ACTUELLE qui satisfont la condition
        foreach ($currentItems as $cluster) {
            $id = $this->getClusterIdentifier($cluster);
            if ($callback($cluster) && ! in_array($id, $addedIds, true)) {
                $filtered[] = $cluster;
                $addedIds[] = $id;
            }
        }

        return $this->createResult($filtered);
    }

    public function firstWhere(string $key, mixed $value): ?ClusterVO
    {
        foreach ($this->items as $cluster) {
            if ($cluster->get($key) === $value) {
                return $cluster;
            }
        }

        return null;
    }

    public function get(): array
    {
        return $this->items;
    }
}
