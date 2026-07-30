<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Fixtures;

use AndyDefer\LaravelCluster\Functions\AllFunction;
use AndyDefer\LaravelCluster\Functions\AvgFunction;
use AndyDefer\LaravelCluster\Functions\CountFunction;
use AndyDefer\LaravelCluster\Functions\ExistsFunction;
use AndyDefer\LaravelCluster\Functions\HasFunction;
use AndyDefer\LaravelCluster\Functions\IsEmptyFunction;
use AndyDefer\LaravelCluster\Functions\LengthFunction;
use AndyDefer\LaravelCluster\Functions\MaxFunction;
use AndyDefer\LaravelCluster\Functions\MinFunction;
use AndyDefer\LaravelCluster\Functions\SumFunction;
use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;

/**
 * Factory for creating test registries with all standard functions.
 */
final class TestRegistryFactory
{
    /**
     * Creates a registry with all standard aggregate functions registered.
     *
     * @return AggregateFunctionRegistry The configured registry
     */
    public static function create(): AggregateFunctionRegistry
    {
        $registry = new AggregateFunctionRegistry;

        $registry->register(new CountFunction);
        $registry->register(new SumFunction);
        $registry->register(new AvgFunction);
        $registry->register(new MinFunction);
        $registry->register(new MaxFunction);
        $registry->register(new LengthFunction);
        $registry->register(new ExistsFunction);
        $registry->register(new HasFunction);
        $registry->register(new AllFunction);
        $registry->register(new IsEmptyFunction);

        return $registry;
    }
}
