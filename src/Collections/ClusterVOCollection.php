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

/**
 * Typed collection for ClusterVO objects with query filtering capabilities.
 *
 * This collection provides a fluent interface for filtering clusters using
 * various conditions, including:
 * - Simple key-value comparisons (where, whereNot, whereIn, etc.)
 * - Numeric comparisons (whereGreaterThan, whereLessThan, etc.)
 * - String operations (whereContains, whereStartsWith, whereEndsWith, whereLikePattern)
 * - Array operations (whereArrayContains, whereArrayEmpty, whereArraySize, etc.)
 * - Aggregate functions (whereAggregate, whereAggregateDirect)
 * - Query string parsing (whereQuery)
 *
 * @extends AbstractTypedCollection<ClusterVO>
 */
final class ClusterVOCollection extends AbstractTypedCollection
{
    private const MAX_RECURSION_DEPTH = 10;

    private array $originalItems = [];

    private int $index = 0;

    private ClusterQuery $query;

    private AggregateEvaluatorService $aggregateEvaluator;

    private static array $recursionDepth = [];

    public function __construct()
    {
        parent::__construct(ClusterVO::class);
        $this->query = new ClusterQuery;
        $this->aggregateEvaluator = new AggregateEvaluatorService;
    }

    /**
     * Filters the collection using a callback and yields results.
     *
     * @param  callable  $callback  The filter callback
     * @return Generator<int, ClusterVO> The filtered items
     */
    private function filterWithYield(callable $callback): Generator
    {
        foreach ($this->items as $cluster) {
            if ($callback($cluster)) {
                yield $cluster;
            }
        }
    }

    /**
     * Creates a new collection from a generator.
     *
     * @param  Generator<int, ClusterVO>  $generator  The generator to consume
     * @return self The new collection
     */
    private function createFromGenerator(Generator $generator): self
    {
        $result = new self;

        foreach ($generator as $item) {
            $result->add($item);
        }

        $result->originalItems = $this->getOriginalItems();

        return $result;
    }

    /**
     * Filters items where the key equals the given value.
     *
     * @param  string  $key  The key to check
     * @param  mixed  $value  The value to match
     * @return self The filtered collection
     */
    public function where(string $key, mixed $value): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->get($key) === $value
        );

        return $this->createFromGenerator($generator);
    }

    /**
     * Alias for where().
     *
     * @param  string  $key  The key to check
     * @param  mixed  $value  The value to match
     * @return self The filtered collection
     */
    public function andWhere(string $key, mixed $value): self
    {
        return $this->where($key, $value);
    }

    /**
     * Filters items where the key does not equal the given value.
     *
     * @param  string  $key  The key to check
     * @param  mixed  $value  The value to exclude
     * @return self The filtered collection
     */
    public function whereNot(string $key, mixed $value): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->get($key) !== $value
        );

        return $this->createFromGenerator($generator);
    }

    /**
     * Filters items where the key equals 'true'.
     *
     * @param  string  $key  The key to check
     * @return self The filtered collection
     */
    public function whereTrue(string $key): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->get($key) === 'true'
        );

        return $this->createFromGenerator($generator);
    }

    /**
     * Filters items where the key equals 'false'.
     *
     * @param  string  $key  The key to check
     * @return self The filtered collection
     */
    public function whereFalse(string $key): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->get($key) === 'false'
        );

        return $this->createFromGenerator($generator);
    }

    /**
     * Adds items matching the condition using OR logic.
     *
     * @param  string  $key  The key to check
     * @param  mixed  $value  The value to match
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key exists.
     *
     * @param  string  $key  The key to check
     * @return self The filtered collection
     */
    public function whereHas(string $key): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->has($key)
        );

        return $this->createFromGenerator($generator);
    }

    /**
     * Filters items where the key does not exist.
     *
     * @param  string  $key  The key to check
     * @return self The filtered collection
     */
    public function whereMissing(string $key): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => ! $cluster->has($key)
        );

        return $this->createFromGenerator($generator);
    }

    /**
     * Filters items where the key value is in the given array.
     *
     * @param  string  $key  The key to check
     * @param  array<mixed>  $values  The allowed values
     * @return self The filtered collection
     */
    public function whereIn(string $key, array $values): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => in_array($cluster->get($key), $values, true)
        );

        return $this->createFromGenerator($generator);
    }

    /**
     * Filters items where the key value is not in the given array.
     *
     * @param  string  $key  The key to check
     * @param  array<mixed>  $values  The excluded values
     * @return self The filtered collection
     */
    public function whereNotIn(string $key, array $values): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => ! in_array($cluster->get($key), $values, true)
        );

        return $this->createFromGenerator($generator);
    }

    /**
     * Filters items using a query string expression.
     *
     * @param  string  $query  The query string
     * @return self The filtered collection
     */
    public function whereQuery(string $query): self
    {
        if (str_contains($query, '{') && str_contains($query, '}')) {
            return $this->whereAggregate($query);
        }

        return $this->whereClosure(
            fn (ClusterVO $cluster) => $this->query->matches($cluster, $query)
        );
    }

    /**
     * Filters items where the key value is greater than the given value.
     *
     * @param  string  $key  The key to check
     * @param  int|float  $value  The threshold value
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key value is greater than or equal to the given value.
     *
     * @param  string  $key  The key to check
     * @param  int|float  $value  The threshold value
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key value is less than the given value.
     *
     * @param  string  $key  The key to check
     * @param  int|float  $value  The threshold value
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key value is less than or equal to the given value.
     *
     * @param  string  $key  The key to check
     * @param  int|float  $value  The threshold value
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key value is between the given min and max.
     *
     * @param  string  $key  The key to check
     * @param  mixed  $min  The minimum value
     * @param  mixed  $max  The maximum value
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key value is not between the given min and max.
     *
     * @param  string  $key  The key to check
     * @param  mixed  $min  The minimum value
     * @param  mixed  $max  The maximum value
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key value is null.
     *
     * @param  string  $key  The key to check
     * @return self The filtered collection
     */
    public function whereNull(string $key): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->get($key) === null
        );

        return $this->createFromGenerator($generator);
    }

    /**
     * Filters items where the key value is not null.
     *
     * @param  string  $key  The key to check
     * @return self The filtered collection
     */
    public function whereNotNull(string $key): self
    {
        $generator = $this->filterWithYield(
            fn (ClusterVO $cluster) => $cluster->get($key) !== null
        );

        return $this->createFromGenerator($generator);
    }

    /**
     * Filters items where the key value contains the given substring.
     *
     * @param  string  $key  The key to check
     * @param  string  $search  The substring to search for
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key value starts with the given prefix.
     *
     * @param  string  $key  The key to check
     * @param  string  $prefix  The prefix to match
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key value ends with the given suffix.
     *
     * @param  string  $key  The key to check
     * @param  string  $suffix  The suffix to match
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key value does not contain the given substring.
     *
     * @param  string  $key  The key to check
     * @param  string  $search  The substring to exclude
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key value does not start with the given prefix.
     *
     * @param  string  $key  The key to check
     * @param  string  $prefix  The prefix to exclude
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key value does not end with the given suffix.
     *
     * @param  string  $key  The key to check
     * @param  string  $suffix  The suffix to exclude
     * @return self The filtered collection
     */
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

    /**
     * Filters items using a custom callback.
     *
     * @param  Closure(ClusterVO): bool  $callback  The filter callback
     * @return self The filtered collection
     *
     * @throws \RuntimeException When maximum recursion depth is exceeded
     */
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

    /**
     * Adds items matching the callback using OR logic.
     *
     * @param  Closure(ClusterVO): bool  $callback  The filter callback
     * @return self The filtered collection
     *
     * @throws \RuntimeException When maximum recursion depth is exceeded
     */
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

    /**
     * Returns the first item where the key matches the given value.
     *
     * @param  string  $key  The key to check
     * @param  mixed  $value  The value to match
     * @return ClusterVO|null The matching cluster or null
     */
    public function firstWhere(string $key, mixed $value): ?ClusterVO
    {
        foreach ($this->items as $cluster) {
            if ($cluster->get($key) === $value) {
                return $cluster;
            }
        }

        return null;
    }

    /**
     * Returns all items as an array.
     *
     * @return array<ClusterVO> The items
     */
    public function get(): array
    {
        return $this->items;
    }

    /**
     * Alias for whereContains.
     *
     * @param  string  $key  The key to check
     * @param  string  $search  The substring to search for
     * @return self The filtered collection
     */
    public function whereLike(string $key, string $search): self
    {
        return $this->whereContains($key, $search);
    }

    /**
     * Filters items using a SQL LIKE pattern.
     *
     * Supports:
     * - '%pattern%' for contains
     * - 'pattern%' for starts with
     * - '%pattern' for ends with
     * - '%pattern1%pattern2%' for multiple conditions
     *
     * @param  string  $key  The key to check
     * @param  string  $pattern  The LIKE pattern
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key does not match the given LIKE pattern.
     *
     * @param  string  $key  The key to check
     * @param  string  $pattern  The LIKE pattern to exclude
     * @return self The filtered collection
     */
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

    /**
     * Matches a value against a SQL LIKE pattern.
     *
     * @param  string  $value  The value to match
     * @param  string  $pattern  The LIKE pattern
     * @return bool True if the value matches the pattern
     */
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

    /**
     * Adds items matching the LIKE pattern using OR logic.
     *
     * @param  string  $key  The key to check
     * @param  string  $pattern  The LIKE pattern
     * @return self The filtered collection
     */
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

    /**
     * Adds items not matching the LIKE pattern using OR logic.
     *
     * @param  string  $key  The key to check
     * @param  string  $pattern  The LIKE pattern to exclude
     * @return self The filtered collection
     */
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

    /**
     * Alias for whereStartsWith.
     *
     * @param  string  $key  The key to check
     * @param  string  $prefix  The prefix to match
     * @return self The filtered collection
     */
    public function whereStarts(string $key, string $prefix): self
    {
        return $this->whereStartsWith($key, $prefix);
    }

    /**
     * Alias for whereEndsWith.
     *
     * @param  string  $key  The key to check
     * @param  string  $suffix  The suffix to match
     * @return self The filtered collection
     */
    public function whereEnds(string $key, string $suffix): self
    {
        return $this->whereEndsWith($key, $suffix);
    }

    /**
     * Filters items where the key array contains the given value.
     *
     * @param  string  $key  The key to check
     * @param  mixed  $value  The value to search for
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key array does not contain the given value.
     *
     * @param  string  $key  The key to check
     * @param  mixed  $value  The value to exclude
     * @return self The filtered collection
     */
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

    /**
     * Adds items where the key array contains the given value using OR logic.
     *
     * @param  string  $key  The key to check
     * @param  mixed  $value  The value to search for
     * @return self The filtered collection
     */
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

    /**
     * Adds items where the key array does not contain the given value using OR logic.
     *
     * @param  string  $key  The key to check
     * @param  mixed  $value  The value to exclude
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key array contains any of the given values.
     *
     * @param  string  $key  The key to check
     * @param  array<mixed>  $values  The values to search for
     * @return self The filtered collection
     */
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

    /**
     * Adds items where the key array contains any of the given values using OR logic.
     *
     * @param  string  $key  The key to check
     * @param  array<mixed>  $values  The values to search for
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key array contains all of the given values.
     *
     * @param  string  $key  The key to check
     * @param  array<mixed>  $values  The values that must all be present
     * @return self The filtered collection
     */
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

    /**
     * Adds items where the key array contains all of the given values using OR logic.
     *
     * @param  string  $key  The key to check
     * @param  array<mixed>  $values  The values that must all be present
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key array has exactly the given size.
     *
     * @param  string  $key  The key to check
     * @param  int  $size  The expected array size
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key array size is greater than the given size.
     *
     * @param  string  $key  The key to check
     * @param  int  $size  The minimum size
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key array size is less than the given size.
     *
     * @param  string  $key  The key to check
     * @param  int  $size  The maximum size
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key array is empty.
     *
     * @param  string  $key  The key to check
     * @return self The filtered collection
     */
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

    /**
     * Filters items where the key array is not empty.
     *
     * @param  string  $key  The key to check
     * @return self The filtered collection
     */
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

    // ==================== AGGREGATE METHODS ====================

    /**
     * Filters the collection using an aggregate expression.
     *
     * Supports complex expressions with multiple functions.
     *
     * @param  string  $expression  The aggregate expression
     * @return self The filtered collection
     *
     * @throws \RuntimeException When recursive call is detected
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
     * Filters the collection using a direct function call (without comparison).
     *
     * @param  string  $functionName  The function name
     * @param  array<mixed>  $args  The function arguments
     * @return self The filtered collection
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
     * Checks if a cluster matches the aggregate expression.
     *
     * @param  ClusterVO  $cluster  The cluster to check
     * @param  string  $expression  The aggregate expression
     * @return bool True if the cluster matches
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
     * Checks if a cluster matches a direct function call.
     *
     * @param  ClusterVO  $cluster  The cluster to check
     * @param  string  $functionName  The function name
     * @param  array<mixed>  $args  The function arguments
     * @return bool True if the cluster matches
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
     * Returns the direct value of a function on a cluster.
     *
     * @param  ClusterVO  $cluster  The cluster to evaluate
     * @param  string  $functionName  The function name
     * @param  array<mixed>  $args  The function arguments
     * @return mixed The function result
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
     * Validates an aggregate expression.
     *
     * @param  string  $expression  The expression to validate
     * @return bool True if the expression is valid
     *
     * @example $collection->validateAggregate('{COUNT(addresses) > 2}')
     */
    public function validateAggregate(string $expression): bool
    {
        return $this->aggregateEvaluator->validate($expression);
    }

    /**
     * Returns the aggregate evaluator service.
     *
     * @return AggregateEvaluatorService The evaluator instance
     */
    public function getAggregateEvaluator(): AggregateEvaluatorService
    {
        return $this->aggregateEvaluator;
    }

    // ==================== PRIVATE METHODS ====================

    /**
     * Checks the recursion depth for a method.
     *
     * @param  string  $method  The method name
     *
     * @throws \RuntimeException When maximum recursion depth is exceeded
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
     * Resets the recursion counter for a method.
     *
     * @param  string  $method  The method name
     */
    private function resetRecursion(string $method): void
    {
        $key = $this->getRecursionKey($method);
        unset(self::$recursionDepth[$key]);
    }

    /**
     * Generates a unique key for recursion tracking.
     *
     * @param  string  $method  The method name
     * @return string The unique key
     */
    private function getRecursionKey(string $method): string
    {
        return spl_object_hash($this).':'.$method;
    }

    /**
     * Initializes the original items if not already set.
     */
    private function initializeOriginalItems(): void
    {
        if (empty($this->originalItems)) {
            $this->originalItems = $this->items;
        }
    }

    /**
     * Returns the original items from before any filtering.
     *
     * @return array<ClusterVO> The original items
     */
    private function getOriginalItems(): array
    {
        $this->initializeOriginalItems();

        return $this->originalItems;
    }

    /**
     * Creates a new collection from filtered items.
     *
     * @param  array<ClusterVO>  $items  The filtered items
     * @return self The new collection
     */
    private function createFilteredResult(array $items): self
    {
        $result = new self;

        foreach ($items as $item) {
            $result->add($item);
        }

        $result->originalItems = $this->getOriginalItems();

        return $result;
    }

    /**
     * Checks if a cluster contains any of the given values in an array.
     *
     * @param  ClusterVO  $cluster  The cluster to check
     * @param  string  $key  The array key
     * @param  array<mixed>  $values  The values to check
     * @return bool True if any value is found
     */
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

    /**
     * Checks if there is a prior filter applied.
     *
     * @return bool True if the collection has been filtered
     */
    private function hasPriorFilter(): bool
    {
        return count($this->items) < count($this->getOriginalItems());
    }

    /**
     * Returns a unique identifier for a cluster.
     *
     * @param  ClusterVO  $cluster  The cluster
     * @return int The cluster identifier
     */
    private function getClusterIdentifier(ClusterVO $cluster): int
    {
        return spl_object_id($cluster);
    }

    /**
     * Checks if a cluster already exists in the result collection.
     *
     * @param  ClusterVO  $cluster  The cluster to check
     * @param  self  $result  The result collection
     * @return bool True if the cluster exists
     */
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
