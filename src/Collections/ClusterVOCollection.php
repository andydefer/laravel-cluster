<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Closure;

/**
 * A specialized collection for managing ClusterVO objects with advanced filtering capabilities.
 *
 * This collection extends the base typed collection to provide a fluent, chainable API
 * for querying and filtering clusters. It maintains the original dataset internally
 * to support complex queries with OR conditions and grouped logic.
 *
 * @method ClusterVO|null first()
 * @method ClusterVO|null last()
 *
 * @example
 * $collection = new ClusterVOCollection();
 * $collection->add($cluster1)->add($cluster2);
 *
 * $filtered = $collection
 *     ->where('status', 'active')
 *     ->orWhere('priority', 'high')
 *     ->whereHas('metadata')
 *     ->get();
 */
final class ClusterVOCollection extends AbstractTypedCollection
{
    /**
     * The original items before any filters were applied.
     *
     * Used as the source for OR queries and to determine if filtering has occurred.
     *
     * @var array<ClusterVO>
     */
    private array $originalItems = [];

    /**
     * Initializes the collection with ClusterVO type validation.
     */
    public function __construct()
    {
        parent::__construct(ClusterVO::class);
    }

    /**
     * Filters clusters where the specified key equals the given value.
     *
     * This is the primary filter method that all other where methods build upon.
     * The result is a new collection instance preserving the original dataset.
     *
     * @param  string  $key  The attribute key to check
     * @param  mixed  $value  The value to match against
     * @return self A new collection with only matching clusters
     */
    public function where(string $key, mixed $value): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->get($key) === $value) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Alias for where() to provide syntactic sugar for chained conditions.
     *
     * @param  string  $key  The attribute key to check
     * @param  mixed  $value  The value to match against
     * @return self A new collection with only matching clusters
     */
    public function andWhere(string $key, mixed $value): self
    {
        return $this->where($key, $value);
    }

    /**
     * Filters clusters where the specified key does NOT equal the given value.
     *
     * @param  string  $key  The attribute key to check
     * @param  mixed  $value  The value to exclude
     * @return self A new collection with only non-matching clusters
     */
    public function whereNot(string $key, mixed $value): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->get($key) !== $value) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the specified key equals the string 'true'.
     *
     * @param  string  $key  The attribute key to check
     * @return self A new collection with only clusters where the key is 'true'
     */
    public function whereTrue(string $key): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->get($key) === 'true') {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the specified key equals the string 'false'.
     *
     * @param  string  $key  The attribute key to check
     * @return self A new collection with only clusters where the key is 'false'
     */
    public function whereFalse(string $key): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->get($key) === 'false') {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Adds an OR condition to the current filter.
     *
     * Includes clusters that either match the current filter criteria OR
     * match the new condition. The original dataset is always used as the source.
     *
     * @param  string  $key  The attribute key to check
     * @param  mixed  $value  The value to match against
     * @return self A new collection with combined filter results
     */
    public function orWhere(string $key, mixed $value): self
    {
        $filtered = [];
        $addedIdentifiers = [];
        $originalItems = $this->getOriginalItems();

        // Preserve already filtered results if they exist
        if ($this->hasPriorFilter()) {
            foreach ($this->items as $cluster) {
                $identifier = $this->getClusterIdentifier($cluster);
                if (! in_array($identifier, $addedIdentifiers, true)) {
                    $filtered[] = $cluster;
                    $addedIdentifiers[] = $identifier;
                }
            }
        }

        // Add clusters that match the OR condition from the original dataset
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
     * Applies a group of filter conditions using a callback.
     *
     * The callback receives a new collection instance and returns a filtered collection.
     * Only clusters that pass ALL conditions in the callback are included.
     *
     * @param  Closure(ClusterVOCollection): ClusterVOCollection  $callback
     * @return self A new collection with clusters that passed all group conditions
     */
    public function whereGroup(Closure $callback): self
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

        return $this->createFilteredResult($filtered);
    }

    /**
     * Applies a group of OR conditions using a callback.
     *
     * The callback receives a new collection instance and returns a filtered collection.
     * Clusters from the original dataset that pass ANY condition in the callback are included.
     *
     * @param  Closure(ClusterVOCollection): ClusterVOCollection  $callback
     * @return self A new collection with clusters that passed any group condition
     */
    public function orWhereGroup(Closure $callback): self
    {
        $filtered = [];
        $addedIdentifiers = [];
        $originalItems = $this->getOriginalItems();

        // Preserve already filtered results if they exist
        if ($this->hasPriorFilter()) {
            foreach ($this->items as $cluster) {
                $identifier = $this->getClusterIdentifier($cluster);
                if (! in_array($identifier, $addedIdentifiers, true)) {
                    $filtered[] = $cluster;
                    $addedIdentifiers[] = $identifier;
                }
            }
        }

        // Check each cluster from original dataset against the group condition
        foreach ($originalItems as $cluster) {
            $identifier = $this->getClusterIdentifier($cluster);
            if (! in_array($identifier, $addedIdentifiers, true)) {
                $tempCollection = new self;
                $tempCollection->add($cluster);
                $tempCollection->originalItems = $originalItems;

                $result = $callback($tempCollection);

                if ($this->clusterExistsInResult($cluster, $result)) {
                    $filtered[] = $cluster;
                    $addedIdentifiers[] = $identifier;
                }
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters that have the specified key.
     *
     * @param  string  $key  The attribute key to check for existence
     * @return self A new collection with only clusters that have the key
     */
    public function whereHas(string $key): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->has($key)) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters that do NOT have the specified key.
     *
     * @param  string  $key  The attribute key to check for absence
     * @return self A new collection with only clusters that do not have the key
     */
    public function whereMissing(string $key): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if (! $cluster->has($key)) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the key's value is in the given array.
     *
     * @param  string  $key  The attribute key to check
     * @param  array<mixed>  $values  The array of acceptable values
     * @return self A new collection with clusters whose values are in the array
     */
    public function whereIn(string $key, array $values): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if (in_array($cluster->get($key), $values, true)) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the key's value is NOT in the given array.
     *
     * @param  string  $key  The attribute key to check
     * @param  array<mixed>  $values  The array of excluded values
     * @return self A new collection with clusters whose values are not in the array
     */
    public function whereNotIn(string $key, array $values): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if (! in_array($cluster->get($key), $values, true)) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the key's numeric value is greater than the given value.
     *
     * @param  string  $key  The attribute key to check
     * @param  int|float  $value  The minimum value (exclusive)
     * @return self A new collection with clusters where value > threshold
     */
    public function whereGreaterThan(string $key, int|float $value): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $val = $cluster->get($key);
            if (is_numeric($val) && (float) $val > $value) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the key's numeric value is greater than or equal to the given value.
     *
     * @param  string  $key  The attribute key to check
     * @param  int|float  $value  The minimum value (inclusive)
     * @return self A new collection with clusters where value >= threshold
     */
    public function whereGreaterThanOrEqual(string $key, int|float $value): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $val = $cluster->get($key);
            if (is_numeric($val) && (float) $val >= $value) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the key's numeric value is less than the given value.
     *
     * @param  string  $key  The attribute key to check
     * @param  int|float  $value  The maximum value (exclusive)
     * @return self A new collection with clusters where value < threshold
     */
    public function whereLessThan(string $key, int|float $value): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $val = $cluster->get($key);
            if (is_numeric($val) && (float) $val < $value) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the key's numeric value is less than or equal to the given value.
     *
     * @param  string  $key  The attribute key to check
     * @param  int|float  $value  The maximum value (inclusive)
     * @return self A new collection with clusters where value <= threshold
     */
    public function whereLessThanOrEqual(string $key, int|float $value): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $val = $cluster->get($key);
            if (is_numeric($val) && (float) $val <= $value) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the key's numeric value is between the given min and max.
     *
     * @param  string  $key  The attribute key to check
     * @param  mixed  $min  The minimum value (inclusive)
     * @param  mixed  $max  The maximum value (inclusive)
     * @return self A new collection with clusters where value is in range
     */
    public function whereBetween(string $key, mixed $min, mixed $max): self
    {
        if (! is_numeric($min) || ! is_numeric($max)) {
            return $this->createFilteredResult([]);
        }

        $filtered = [];

        foreach ($this->items as $cluster) {
            $val = $cluster->get($key);
            if (is_numeric($val) && $val >= $min && $val <= $max) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the key's numeric value is outside the given range.
     *
     * @param  string  $key  The attribute key to check
     * @param  mixed  $min  The minimum value (inclusive)
     * @param  mixed  $max  The maximum value (inclusive)
     * @return self A new collection with clusters where value is outside range
     */
    public function whereNotBetween(string $key, mixed $min, mixed $max): self
    {
        if (! is_numeric($min) || ! is_numeric($max)) {
            return $this->createFilteredResult($this->items);
        }

        $filtered = [];

        foreach ($this->items as $cluster) {
            $val = $cluster->get($key);
            if (! is_numeric($val) || $val < $min || $val > $max) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the key's value is null.
     *
     * @param  string  $key  The attribute key to check
     * @return self A new collection with clusters where value is null
     */
    public function whereNull(string $key): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->get($key) === null) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the key's value is not null.
     *
     * @param  string  $key  The attribute key to check
     * @return self A new collection with clusters where value is not null
     */
    public function whereNotNull(string $key): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($cluster->get($key) !== null) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the string value contains the search term (case-insensitive).
     *
     * @param  string  $key  The attribute key to check
     * @param  string  $search  The search term to look for
     * @return self A new collection with clusters where value contains the search term
     */
    public function whereContains(string $key, string $search): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $value = $cluster->get($key);
            if (is_string($value) && stripos($value, $search) !== false) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the string value starts with the given prefix (case-insensitive).
     *
     * @param  string  $key  The attribute key to check
     * @param  string  $prefix  The prefix to look for
     * @return self A new collection with clusters where value starts with the prefix
     */
    public function whereStartsWith(string $key, string $prefix): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $value = $cluster->get($key);
            if (is_string($value) && stripos($value, $prefix) === 0) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the string value ends with the given suffix (case-insensitive).
     *
     * @param  string  $key  The attribute key to check
     * @param  string  $suffix  The suffix to look for
     * @return self A new collection with clusters where value ends with the suffix
     */
    public function whereEndsWith(string $key, string $suffix): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $value = $cluster->get($key);
            if (is_string($value) && str_ends_with(strtolower($value), strtolower($suffix))) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters using a custom callback function.
     *
     * The callback receives a ClusterVO instance and should return true to include it.
     *
     * @param  Closure(ClusterVO): bool  $callback  The filter function
     * @return self A new collection with clusters that pass the callback
     */
    public function whereClosure(Closure $callback): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            if ($callback($cluster)) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Adds an OR condition using a custom callback.
     *
     * The callback receives a ClusterVO instance and should return true to include it.
     * Clusters that match either the current filter OR the callback condition are included.
     *
     * @param  Closure(ClusterVO): bool  $callback  The filter function
     * @return self A new collection with clusters that pass any condition
     */
    public function orWhereClosure(Closure $callback): self
    {
        $filtered = [];
        $addedIdentifiers = [];
        $currentItems = $this->items;

        // Preserve already filtered results if they exist
        if ($this->hasPriorFilter()) {
            foreach ($currentItems as $cluster) {
                $identifier = $this->getClusterIdentifier($cluster);
                $filtered[] = $cluster;
                $addedIdentifiers[] = $identifier;
            }
        }

        // Add clusters that pass the callback condition
        foreach ($currentItems as $cluster) {
            $identifier = $this->getClusterIdentifier($cluster);
            if ($callback($cluster) && ! in_array($identifier, $addedIdentifiers, true)) {
                $filtered[] = $cluster;
                $addedIdentifiers[] = $identifier;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Returns the first cluster matching the given condition.
     *
     * @param  string  $key  The attribute key to check
     * @param  mixed  $value  The value to match against
     * @return ClusterVO|null The first matching cluster or null if none found
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
     * Returns all clusters in the collection as an array.
     *
     * @return array<ClusterVO> The array of clusters
     */
    public function get(): array
    {
        return $this->items;
    }

    /**
     * Filters clusters where the string value contains the search term (case-insensitive).
     *
     * @param  string  $key  The attribute key to check
     * @param  string  $search  The search term to look for
     * @return self A new collection with clusters where value contains the search term
     */
    public function whereLike(string $key, string $search): self
    {
        return $this->whereContains($key, $search);
    }

    /**
     * Filters clusters where the string value starts with the prefix (case-insensitive).
     *
     * @param  string  $key  The attribute key to check
     * @param  string  $prefix  The prefix to look for
     * @return self A new collection with clusters where value starts with the prefix
     */
    public function whereStarts(string $key, string $prefix): self
    {
        return $this->whereStartsWith($key, $prefix);
    }

    /**
     * Filters clusters where the string value ends with the suffix (case-insensitive).
     *
     * @param  string  $key  The attribute key to check
     * @param  string  $suffix  The suffix to look for
     * @return self A new collection with clusters where value ends with the suffix
     */
    public function whereEnds(string $key, string $suffix): self
    {
        return $this->whereEndsWith($key, $suffix);
    }

    /**
     * Filters clusters where the string value does NOT contain the search term (case-insensitive).
     *
     * @param  string  $key  The attribute key to check
     * @param  string  $search  The search term to exclude
     * @return self A new collection with clusters where value does not contain the search term
     */
    public function whereNotLike(string $key, string $search): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $value = $cluster->get($key);
            if (! is_string($value) || stripos($value, $search) === false) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the string value does NOT start with the prefix (case-insensitive).
     *
     * @param  string  $key  The attribute key to check
     * @param  string  $prefix  The prefix to exclude
     * @return self A new collection with clusters where value does not start with the prefix
     */
    public function whereNotStarts(string $key, string $prefix): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $value = $cluster->get($key);
            if (! is_string($value) || stripos($value, $prefix) !== 0) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Filters clusters where the string value does NOT end with the suffix (case-insensitive).
     *
     * @param  string  $key  The attribute key to check
     * @param  string  $suffix  The suffix to exclude
     * @return self A new collection with clusters where value does not end with the suffix
     */
    public function whereNotEnds(string $key, string $suffix): self
    {
        $filtered = [];

        foreach ($this->items as $cluster) {
            $value = $cluster->get($key);
            if (! is_string($value) || ! str_ends_with(strtolower($value), strtolower($suffix))) {
                $filtered[] = $cluster;
            }
        }

        return $this->createFilteredResult($filtered);
    }

    /**
     * Initializes the original items if not already set.
     *
     * The original items are preserved to support OR queries and to detect filtering.
     */
    private function initializeOriginalItems(): void
    {
        if (empty($this->originalItems)) {
            $this->originalItems = $this->items;
        }
    }

    /**
     * Retrieves the original items, initializing them if necessary.
     *
     * @return array<ClusterVO> The original items
     */
    private function getOriginalItems(): array
    {
        $this->initializeOriginalItems();

        return $this->originalItems;
    }

    /**
     * Creates a new collection instance with the given items.
     *
     * Preserves the original items from this collection for future queries.
     *
     * @param  array<ClusterVO>  $items  The items for the new collection
     * @return self A new collection with the given items
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
     * Checks if a filter has been applied to this collection.
     *
     * @return bool True if the current items differ from the original items
     */
    private function hasPriorFilter(): bool
    {
        return count($this->items) < count($this->getOriginalItems());
    }

    /**
     * Gets a unique identifier for a cluster instance.
     *
     * Uses spl_object_id to distinguish between different instances,
     * even if they contain identical data.
     *
     * @param  ClusterVO  $cluster  The cluster instance
     * @return int The unique identifier
     */
    private function getClusterIdentifier(ClusterVO $cluster): int
    {
        return spl_object_id($cluster);
    }

    /**
     * Determines if a cluster exists in a result collection.
     *
     * @param  ClusterVO  $cluster  The cluster to check
     * @param  self  $result  The collection to search in
     * @return bool True if the cluster exists in the collection
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
