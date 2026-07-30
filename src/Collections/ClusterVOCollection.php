<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Services\AggregateEvaluatorService;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Closure;
use Generator;
use Illuminate\Support\Collection;

final class ClusterVOCollection extends AbstractTypedCollection
{
    private const MAX_RECURSION_DEPTH = 10;

    private array $originalItems = [];

    private int $index = 0;

    private ClusterQuery $query;

    private AggregateEvaluatorService $aggregateEvaluator;

    /**
     * @var array<string, int> Compteur de récursion par méthode
     */
    private static array $recursionDepth = [];

    public function __construct()
    {
        parent::__construct(ClusterVO::class);
        $this->query = new ClusterQuery;
        $this->aggregateEvaluator = new AggregateEvaluatorService;
    }

    // ==================== MÉTHODES EXISTANTES ====================

    private function filterWithYield(callable $callback): Generator
    {
        foreach ($this->items as $cluster) {
            if ($callback($cluster)) {
                yield $cluster;
            }
        }
    }

    private function createFromGenerator(Generator $generator): self
    {
        $result = new self;

        foreach ($generator as $item) {
            $result->add($item);
        }

        $result->originalItems = $this->getOriginalItems();

        return $result;
    }

    public function where(string $key, mixed $value): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->get($key) === $value
        );

        return $this->createFromGenerator($generator);
    }

    public function andWhere(string $key, mixed $value): self
    {
        return $this->where($key, $value);
    }

    public function whereNot(string $key, mixed $value): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->get($key) !== $value
        );

        return $this->createFromGenerator($generator);
    }

    public function whereTrue(string $key): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->get($key) === 'true'
        );

        return $this->createFromGenerator($generator);
    }

    public function whereFalse(string $key): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->get($key) === 'false'
        );

        return $this->createFromGenerator($generator);
    }

    public function orWhere(string $key, mixed $value): self
    {
        $filtered = [];
        $addedIdentifiers = [];
        $originalItems = $this->getOriginalItems();

        if ($this->hasPriorFilter()) {
            foreach ($this->items as $cluster) {
                $identifier = $this->getClusterIdentifier($cluster);

                if (! in_array($identifier, $addedIdentifiers, true)) {
                    $filtered[] = $cluster;
                    $addedIdentifiers[] = $identifier;
                }
            }
        }

        foreach ($originalItems as $cluster) {
            $identifier = $this->getClusterIdentifier($cluster);

            if ($cluster->get($key) === $value && ! in_array($identifier, $addedIdentifiers, true)) {
                $filtered[] = $cluster;
                $addedIdentifiers[] = $identifier;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    public function whereHas(string $key): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->has($key)
        );

        return $this->createFromGenerator($generator);
    }

    public function whereMissing(string $key): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => ! $cluster->has($key)
        );

        return $this->createFromGenerator($generator);
    }

    public function whereIn(string $key, array $values): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => in_array($cluster->get($key), $values, true)
        );

        return $this->createFromGenerator($generator);
    }

    public function whereNotIn(string $key, array $values): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => ! in_array($cluster->get($key), $values, true)
        );

        return $this->createFromGenerator($generator);
    }

    public function whereQuery(string $query): self
    {
        if (str_contains($query, '{') && str_contains($query, '}')) {
            return $this->whereAggregate($query);
        }

        return $this->whereClosure(
            fn (ClusterVO $cluster) => $this->query->matches($cluster, $query)
        );
    }

    public function whereGreaterThan(string $key, int|float $value): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $value) {
                $val = $cluster->get($key);

                return is_numeric($val) && (float) $val > $value;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereGreaterThanOrEqual(string $key, int|float $value): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $value) {
                $val = $cluster->get($key);

                return is_numeric($val) && (float) $val >= $value;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereLessThan(string $key, int|float $value): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $value) {
                $val = $cluster->get($key);

                return is_numeric($val) && (float) $val < $value;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereLessThanOrEqual(string $key, int|float $value): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $value) {
                $val = $cluster->get($key);

                return is_numeric($val) && (float) $val <= $value;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereBetween(string $key, mixed $min, mixed $max): self
    {
        if (! is_numeric($min) || ! is_numeric($max)) {
            return $this->createFilteredResult([]);
        }

        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $min, $max) {
                $val = $cluster->get($key);

                return is_numeric($val) && $val >= $min && $val <= $max;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereNotBetween(string $key, mixed $min, mixed $max): self
    {
        if (! is_numeric($min) || ! is_numeric($max)) {
            return $this->createFilteredResult($this->items);
        }

        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $min, $max) {
                $val = $cluster->get($key);

                return ! is_numeric($val) || $val < $min || $val > $max;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereNull(string $key): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->get($key) === null
        );

        return $this->createFromGenerator($generator);
    }

    public function whereNotNull(string $key): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->get($key) !== null
        );

        return $this->createFromGenerator($generator);
    }

    public function whereContains(string $key, string $search): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $search) {
                $value = $cluster->get($key);

                return is_string($value) && stripos($value, $search) !== false;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereStartsWith(string $key, string $prefix): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $prefix) {
                $value = $cluster->get($key);

                return is_string($value) && stripos($value, $prefix) === 0;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereEndsWith(string $key, string $suffix): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $suffix) {
                $value = $cluster->get($key);

                return is_string($value) && str_ends_with(strtolower($value), strtolower($suffix));
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereNotLike(string $key, string $search): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $search) {
                $value = $cluster->get($key);

                return ! is_string($value) || stripos($value, $search) === false;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereNotStarts(string $key, string $prefix): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $prefix) {
                $value = $cluster->get($key);

                return ! is_string($value) || stripos($value, $prefix) !== 0;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereNotEnds(string $key, string $suffix): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $suffix) {
                $value = $cluster->get($key);

                return ! is_string($value) || ! str_ends_with(strtolower($value), strtolower($suffix));
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereClosure(Closure $callback): self
    {
        $this->checkRecursion(__FUNCTION__);

        try {
            $generator = $this->filterWithYield($callback);

            return $this->createFromGenerator($generator);
        } finally {
            $this->resetRecursion(__FUNCTION__);
        }
    }

    public function orWhereClosure(Closure $callback): self
    {
        $this->checkRecursion(__FUNCTION__);

        try {
            $filtered = [];
            $addedIdentifiers = [];
            $currentItems = $this->items;

            if ($this->hasPriorFilter()) {
                foreach ($currentItems as $cluster) {
                    $identifier = $this->getClusterIdentifier($cluster);
                    $filtered[] = $cluster;
                    $addedIdentifiers[] = $identifier;
                }
            }

            foreach ($currentItems as $cluster) {
                $identifier = $this->getClusterIdentifier($cluster);

                if ($callback($cluster) && ! in_array($identifier, $addedIdentifiers, true)) {
                    $filtered[] = $cluster;
                    $addedIdentifiers[] = $identifier;
                }
            }

            return $this->createFilteredResult($filtered);
        } finally {
            $this->resetRecursion(__FUNCTION__);
        }
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

    public function whereLike(string $key, string $search): self
    {
        return $this->whereContains($key, $search);
    }

    public function whereLikePattern(string $key, string $pattern): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $pattern) {
                $value = $cluster->get($key);

                if (! is_string($value)) {
                    return false;
                }

                return $this->matchLikePattern($value, $pattern);
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereNotLikePattern(string $key, string $pattern): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $pattern) {
                $value = $cluster->get($key);

                if (! is_string($value)) {
                    return true;
                }

                return ! $this->matchLikePattern($value, $pattern);
            }
        );

        return $this->createFromGenerator($generator);
    }

    private function matchLikePattern(string $value, string $pattern): bool
    {
        $valueLower = strtolower($value);
        $patternLower = strtolower($pattern);

        if (str_contains($pattern, '%')) {
            if (str_starts_with($pattern, '%') && str_ends_with($pattern, '%') && substr_count($pattern, '%') === 2) {
                $search = substr($pattern, 1, -1);

                return str_contains($valueLower, strtolower($search));
            }

            if (str_ends_with($pattern, '%') && ! str_starts_with($pattern, '%')) {
                $search = substr($pattern, 0, -1);

                return str_starts_with($valueLower, strtolower($search));
            }

            if (str_starts_with($pattern, '%') && ! str_ends_with($pattern, '%') && substr_count($pattern, '%') === 1) {
                $search = substr($pattern, 1);

                return str_ends_with($valueLower, strtolower($search));
            }

            $parts = explode('%', $pattern);
            $parts = array_filter($parts, fn ($p) => $p !== '');

            if (count($parts) >= 2) {
                $position = 0;

                foreach ($parts as $part) {
                    $partLower = strtolower($part);
                    $pos = strpos($valueLower, $partLower, $position);

                    if ($pos === false) {
                        return false;
                    }

                    $position = $pos + strlen($partLower);
                }

                return true;
            }

            $search = str_replace('%', '', $pattern);

            return str_contains($valueLower, strtolower($search));
        }

        return str_contains($valueLower, $patternLower);
    }

    public function orWhereLikePattern(string $key, string $pattern): self
    {
        $filtered = [];
        $addedIdentifiers = [];
        $originalItems = $this->getOriginalItems();

        if ($this->hasPriorFilter()) {
            foreach ($this->items as $cluster) {
                $identifier = $this->getClusterIdentifier($cluster);

                if (! in_array($identifier, $addedIdentifiers, true)) {
                    $filtered[] = $cluster;
                    $addedIdentifiers[] = $identifier;
                }
            }
        }

        foreach ($originalItems as $cluster) {
            $identifier = $this->getClusterIdentifier($cluster);

            if (! in_array($identifier, $addedIdentifiers, true)) {
                $value = $cluster->get($key);

                if (is_string($value) && $this->matchLikePattern($value, $pattern)) {
                    $filtered[] = $cluster;
                    $addedIdentifiers[] = $identifier;
                }
            }
        }

        return $this->createFilteredResult($filtered);
    }

    public function orWhereNotLikePattern(string $key, string $pattern): self
    {
        $filtered = [];
        $addedIdentifiers = [];
        $originalItems = $this->getOriginalItems();

        if ($this->hasPriorFilter()) {
            foreach ($this->items as $cluster) {
                $identifier = $this->getClusterIdentifier($cluster);

                if (! in_array($identifier, $addedIdentifiers, true)) {
                    $filtered[] = $cluster;
                    $addedIdentifiers[] = $identifier;
                }
            }
        }

        foreach ($originalItems as $cluster) {
            $identifier = $this->getClusterIdentifier($cluster);

            if (! in_array($identifier, $addedIdentifiers, true)) {
                $value = $cluster->get($key);

                if (! is_string($value) || ! $this->matchLikePattern($value, $pattern)) {
                    $filtered[] = $cluster;
                    $addedIdentifiers[] = $identifier;
                }
            }
        }

        return $this->createFilteredResult($filtered);
    }

    public function whereStarts(string $key, string $prefix): self
    {
        return $this->whereStartsWith($key, $prefix);
    }

    public function whereEnds(string $key, string $suffix): self
    {
        return $this->whereEndsWith($key, $suffix);
    }

    public function whereArrayContains(string $key, mixed $value): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $value) {
                $prefix = $key.'_';
                $clusterData = $cluster->toArray();

                foreach ($clusterData as $clusterKey => $clusterValue) {
                    if (str_starts_with($clusterKey, $prefix) && $clusterValue === 'true') {
                        $suffix = substr($clusterKey, strlen($prefix));

                        if ((string) $suffix === (string) $value) {
                            return true;
                        }
                    }
                }

                return false;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereArrayNotContains(string $key, mixed $value): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $value) {
                $prefix = $key.'_';
                $clusterData = $cluster->toArray();

                foreach ($clusterData as $clusterKey => $clusterValue) {
                    if (str_starts_with($clusterKey, $prefix) && $clusterValue === 'true') {
                        $suffix = substr($clusterKey, strlen($prefix));

                        if ((string) $suffix === (string) $value) {
                            return false;
                        }
                    }
                }

                return true;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function orWhereArrayContains(string $key, mixed $value): self
    {
        $filtered = [];
        $addedIdentifiers = [];
        $originalItems = $this->getOriginalItems();

        foreach ($this->items as $cluster) {
            $identifier = $this->getClusterIdentifier($cluster);

            if (! in_array($identifier, $addedIdentifiers, true)) {
                $filtered[] = $cluster;
                $addedIdentifiers[] = $identifier;
            }
        }

        foreach ($originalItems as $cluster) {
            $identifier = $this->getClusterIdentifier($cluster);

            if (! in_array($identifier, $addedIdentifiers, true)) {
                $prefix = $key.'_';
                $found = false;
                $clusterData = $cluster->toArray();

                foreach ($clusterData as $clusterKey => $clusterValue) {
                    if (str_starts_with($clusterKey, $prefix) && $clusterValue === 'true') {
                        $suffix = substr($clusterKey, strlen($prefix));

                        if ((string) $suffix === (string) $value) {
                            $found = true;
                            break;
                        }
                    }
                }

                if ($found) {
                    $filtered[] = $cluster;
                    $addedIdentifiers[] = $identifier;
                }
            }
        }

        return $this->createFilteredResult($filtered);
    }

    public function orWhereArrayNotContains(string $key, mixed $value): self
    {
        $filtered = [];
        $addedIdentifiers = [];
        $originalItems = $this->getOriginalItems();

        foreach ($this->items as $cluster) {
            $identifier = $this->getClusterIdentifier($cluster);

            if (! in_array($identifier, $addedIdentifiers, true)) {
                $filtered[] = $cluster;
                $addedIdentifiers[] = $identifier;
            }
        }

        foreach ($originalItems as $cluster) {
            $identifier = $this->getClusterIdentifier($cluster);

            if (! in_array($identifier, $addedIdentifiers, true)) {
                $prefix = $key.'_';
                $found = false;
                $clusterData = $cluster->toArray();

                foreach ($clusterData as $clusterKey => $clusterValue) {
                    if (str_starts_with($clusterKey, $prefix) && $clusterValue === 'true') {
                        $suffix = substr($clusterKey, strlen($prefix));

                        if ((string) $suffix === (string) $value) {
                            $found = true;
                            break;
                        }
                    }
                }

                if (! $found) {
                    $filtered[] = $cluster;
                    $addedIdentifiers[] = $identifier;
                }
            }
        }

        return $this->createFilteredResult($filtered);
    }

    public function whereArrayContainsAny(string $key, array $values): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $values) {
                $prefix = $key.'_';
                $clusterData = $cluster->toArray();

                foreach ($clusterData as $clusterKey => $clusterValue) {
                    if (str_starts_with($clusterKey, $prefix) && $clusterValue === 'true') {
                        $suffix = substr($clusterKey, strlen($prefix));

                        if (in_array($suffix, $values, true)) {
                            return true;
                        }
                    }
                }

                return false;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function orWhereArrayContainsAny(string $key, array $values): self
    {
        $filtered = [];
        $addedIds = [];
        $originalItems = $this->getOriginalItems();

        if ($this->hasPriorFilter()) {
            foreach ($this->items as $cluster) {
                $id = $this->getClusterIdentifier($cluster);
                $filtered[] = $cluster;
                $addedIds[] = $id;
            }
        }

        foreach ($originalItems as $cluster) {
            $id = $this->getClusterIdentifier($cluster);

            if (! in_array($id, $addedIds, true) && $this->arrayContainsAny($cluster, $key, $values)) {
                $filtered[] = $cluster;
                $addedIds[] = $id;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    public function whereArrayContainsAll(string $key, array $values): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $values) {
                $prefix = $key.'_';
                $foundValues = [];
                $clusterData = $cluster->toArray();

                foreach ($clusterData as $clusterKey => $clusterValue) {
                    if (str_starts_with($clusterKey, $prefix) && $clusterValue === 'true') {
                        $suffix = substr($clusterKey, strlen($prefix));

                        if (in_array($suffix, $values, true)) {
                            $foundValues[] = $suffix;
                        }
                    }
                }

                $allFound = true;

                foreach ($values as $value) {
                    if (! in_array($value, $foundValues, true)) {
                        $allFound = false;
                        break;
                    }
                }

                return $allFound;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function orWhereArrayContainsAll(string $key, array $values): self
    {
        $filtered = [];
        $addedIdentifiers = [];
        $originalItems = $this->getOriginalItems();

        foreach ($this->items as $cluster) {
            $identifier = $this->getClusterIdentifier($cluster);

            if (! in_array($identifier, $addedIdentifiers, true)) {
                $filtered[] = $cluster;
                $addedIdentifiers[] = $identifier;
            }
        }

        foreach ($originalItems as $cluster) {
            $identifier = $this->getClusterIdentifier($cluster);

            if (! in_array($identifier, $addedIdentifiers, true)) {
                $prefix = $key.'_';
                $foundValues = [];
                $clusterData = $cluster->toArray();

                foreach ($clusterData as $clusterKey => $clusterValue) {
                    if (str_starts_with($clusterKey, $prefix) && $clusterValue === 'true') {
                        $suffix = substr($clusterKey, strlen($prefix));

                        if (in_array($suffix, $values, true)) {
                            $foundValues[] = $suffix;
                        }
                    }
                }

                $allFound = true;

                foreach ($values as $value) {
                    if (! in_array($value, $foundValues, true)) {
                        $allFound = false;
                        break;
                    }
                }

                if ($allFound) {
                    $filtered[] = $cluster;
                    $addedIdentifiers[] = $identifier;
                }
            }
        }

        return $this->createFilteredResult($filtered);
    }

    public function whereArraySize(string $key, int $size): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $size) {
                $prefix = $key.'_';
                $count = 0;
                $clusterData = $cluster->toArray();

                foreach ($clusterData as $clusterKey => $clusterValue) {
                    if (str_starts_with($clusterKey, $prefix) && $clusterValue === 'true') {
                        $count++;
                    }
                }

                return $count === $size;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereArraySizeGreaterThan(string $key, int $size): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $size) {
                $prefix = $key.'_';
                $count = 0;
                $clusterData = $cluster->toArray();

                foreach ($clusterData as $clusterKey => $clusterValue) {
                    if (str_starts_with($clusterKey, $prefix) && $clusterValue === 'true') {
                        $count++;
                    }
                }

                return $count > $size;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereArraySizeLessThan(string $key, int $size): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key, $size) {
                $prefix = $key.'_';
                $count = 0;
                $clusterData = $cluster->toArray();

                foreach ($clusterData as $clusterKey => $clusterValue) {
                    if (str_starts_with($clusterKey, $prefix) && $clusterValue === 'true') {
                        $count++;
                    }
                }

                return $count < $size;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereArrayEmpty(string $key): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key) {
                if (! $cluster->has($key)) {
                    return false;
                }

                $value = $cluster->get($key);

                if ($value !== null) {
                    return false;
                }

                $prefix = $key.'_';
                $clusterData = $cluster->toArray();

                foreach ($clusterData as $clusterKey => $clusterValue) {
                    if (str_starts_with($clusterKey, $prefix) && $clusterValue === 'true') {
                        return false;
                    }
                }

                return true;
            }
        );

        return $this->createFromGenerator($generator);
    }

    public function whereArrayNotEmpty(string $key): self
    {
        $generator = $this->filterWithYield(
            function (ClusterVO $cluster) use ($key) {
                $prefix = $key.'_';
                $clusterData = $cluster->toArray();

                foreach ($clusterData as $clusterKey => $clusterValue) {
                    if (str_starts_with($clusterKey, $prefix) && $clusterValue === 'true') {
                        return true;
                    }
                }

                return false;
            }
        );

        return $this->createFromGenerator($generator);
    }

    // ==================== NOUVELLES MÉTHODES D'AGRÉGATION ====================

    /**
     * Filtre la collection avec une expression d'agrégation
     * Supporte les expressions complexes avec multiples fonctions
     *
     * @example $collection->whereAggregate('{COUNT(addresses) > 2}')
     * @example $collection->whereAggregate('{COUNT(addresses) > 2} & {CUSTOM(scores, $prices) > 100}')
     * @example $collection->whereAggregate('{EXISTS(addresses)}')
     * @example $collection->whereAggregate('{HAS(addresses, city, "Kinshasa")}')
     */
    public function whereAggregate(string $expression): self
    {
        static $recursionStack = [];
        $hash = spl_object_hash($this);

        if (isset($recursionStack[$hash])) {
            throw new \RuntimeException(
                sprintf('Recursive whereAggregate detected for object %s', $hash)
            );
        }

        $recursionStack[$hash] = true;

        try {
            return $this->whereClosure(
                function (ClusterVO $cluster) use ($expression) {
                    $data = $cluster->getUnflattened()->toArray();

                    return $this->aggregateEvaluator->evaluate($data, $expression);
                }
            );
        } finally {
            unset($recursionStack[$hash]);
        }
    }

    /**
     * Filtre la collection avec une fonction directe (sans comparaison)
     *
     * @example $collection->whereAggregateDirect('COUNT', ['addresses'])
     * @example $collection->whereAggregateDirect('EXISTS', ['addresses'])
     * @example $collection->whereAggregateDirect('HAS', ['addresses', 'city', 'Kinshasa'])
     */
    public function whereAggregateDirect(string $functionName, array $args = []): self
    {
        $this->checkRecursion(__FUNCTION__);

        try {
            return $this->whereClosure(
                function (ClusterVO $cluster) use ($functionName, $args) {
                    $data = $cluster->getUnflattened()->toArray();
                    $result = $this->aggregateEvaluator->evaluateDirect($data, $functionName, $args);

                    return (bool) $result;
                }
            );
        } finally {
            $this->resetRecursion(__FUNCTION__);
        }
    }

    /**
     * Retourne un booléen pour un cluster spécifique avec expression complète
     *
     * @example $collection->matchesAggregate($cluster, '{COUNT(addresses) > 2}')
     * @example $collection->matchesAggregate($cluster, '{EXISTS(addresses)}')
     */
    public function matchesAggregate(ClusterVO $cluster, string $expression): bool
    {
        $data = $cluster->getUnflattened()->toArray();

        return $this->aggregateEvaluator->evaluate($data, $expression);
    }

    /**
     * Retourne un booléen pour un cluster avec fonction directe
     *
     * @example $collection->matchesAggregateDirect($cluster, 'COUNT', ['addresses'])
     * @example $collection->matchesAggregateDirect($cluster, 'EXISTS', ['addresses'])
     */
    public function matchesAggregateDirect(ClusterVO $cluster, string $functionName, array $args = []): bool
    {
        $data = $cluster->getUnflattened()->toArray();
        $result = $this->aggregateEvaluator->evaluateDirect($data, $functionName, $args);

        return (bool) $result;
    }

    /**
     * Retourne la valeur directe d'une fonction sur un cluster
     *
     * @example $collection->getAggregateValue($cluster, 'COUNT', ['addresses'])
     * @example $collection->getAggregateValue($cluster, 'AVG', ['scores'])
     */
    public function getAggregateValue(ClusterVO $cluster, string $functionName, array $args = []): mixed
    {
        $data = $cluster->getUnflattened()->toArray();

        return $this->aggregateEvaluator->evaluateDirect($data, $functionName, $args);
    }

    /**
     * Valide une expression d'agrégation
     *
     * @example $collection->validateAggregate('{COUNT(addresses) > 2}')
     */
    public function validateAggregate(string $expression): bool
    {
        return $this->aggregateEvaluator->validate($expression);
    }

    /**
     * Retourne l'évaluateur d'agrégation pour accéder au registre
     */
    public function getAggregateEvaluator(): AggregateEvaluatorService
    {
        return $this->aggregateEvaluator;
    }

    // ==================== MÉTHODES PRIVÉES ====================

    /**
     * Vérifie la profondeur de récursion pour une méthode
     *
     * @throws \RuntimeException Si la profondeur maximale est atteinte
     */
    private function checkRecursion(string $method): void
    {
        $key = $this->getRecursionKey($method);

        if (! isset(self::$recursionDepth[$key])) {
            self::$recursionDepth[$key] = 0;
        }

        self::$recursionDepth[$key]++;

        if (self::$recursionDepth[$key] > self::MAX_RECURSION_DEPTH) {
            $this->resetRecursion($method);
            throw new \RuntimeException(
                sprintf(
                    'Maximum recursion depth (%d) exceeded for method "%s". Possible infinite loop detected.',
                    self::MAX_RECURSION_DEPTH,
                    $method
                )
            );
        }
    }

    /**
     * Réinitialise le compteur de récursion pour une méthode
     */
    private function resetRecursion(string $method): void
    {
        $key = $this->getRecursionKey($method);
        unset(self::$recursionDepth[$key]);
    }

    /**
     * Génère une clé unique pour le suivi de la récursion
     */
    private function getRecursionKey(string $method): string
    {
        return spl_object_hash($this).':'.$method;
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

    private function createFilteredResult(array $items): self
    {
        $result = new self;

        foreach ($items as $item) {
            $result->add($item);
        }

        $result->originalItems = $this->getOriginalItems();

        return $result;
    }

    private function arrayContainsAny(ClusterVO $cluster, string $key, array $values): bool
    {
        $prefix = $key.'_';
        $clusterData = $cluster->toArray();

        foreach ($values as $value) {
            if (isset($clusterData[$prefix.$value]) && $clusterData[$prefix.$value] === 'true') {
                return true;
            }
        }

        return false;
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
}
