<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Registry;

use AndyDefer\LaravelCluster\Contracts\AggregateFunctionInterface;
use AndyDefer\LaravelCluster\Functions\AllFunction;
use AndyDefer\LaravelCluster\Functions\AvgFunction;
use AndyDefer\LaravelCluster\Functions\CountFunction;
use AndyDefer\LaravelCluster\Functions\ExistsFunction;
use AndyDefer\LaravelCluster\Functions\HasFunction;
use AndyDefer\LaravelCluster\Functions\IsEmptyFunction;
use AndyDefer\LaravelCluster\Functions\LengthFunction;
use AndyDefer\LaravelCluster\Functions\MatchesFunction;
use AndyDefer\LaravelCluster\Functions\MaxFunction;
use AndyDefer\LaravelCluster\Functions\MinFunction;
use AndyDefer\LaravelCluster\Functions\SumFunction;
use InvalidArgumentException;

/**
 * Registry for aggregate functions used in queries.
 *
 * This registry manages all aggregate functions (COUNT, SUM, AVG, MIN, MAX, etc.)
 * that can be used in query expressions. Functions are registered by name
 * and can be executed against data arrays.
 *
 * @example
 * $registry = new AggregateFunctionRegistry();
 * $registry->has('COUNT'); // true
 * $result = $registry->execute('COUNT', $data, ['addresses']); // 3
 * @example
 * $customFunction = new CustomFunction();
 * $registry->register($customFunction);
 */
final class AggregateFunctionRegistry
{
    private array $functions = [];

    public function __construct()
    {
        $this->registerDefaultFunctions();
    }

    /**
     * Registers an aggregate function in the registry.
     *
     * @param  AggregateFunctionInterface  $function  The function to register
     * @return self Returns the registry instance for method chaining
     */
    public function register(AggregateFunctionInterface $function): self
    {
        $this->functions[strtoupper($function->getName())] = $function;

        return $this;
    }

    /**
     * Checks if a function is registered by name.
     *
     * @param  string  $name  The function name (case-insensitive)
     * @return bool True if the function is registered
     */
    public function has(string $name): bool
    {
        return isset($this->functions[strtoupper($name)]);
    }

    /**
     * Retrieves a registered function by name.
     *
     * @param  string  $name  The function name (case-insensitive)
     * @return AggregateFunctionInterface|null The function instance, or null if not found
     */
    public function get(string $name): ?AggregateFunctionInterface
    {
        return $this->functions[strtoupper($name)] ?? null;
    }

    /**
     * Executes a registered function with the given data and arguments.
     *
     * @param  string  $name  The function name to execute
     * @param  array<string, mixed>  $data  The data to process
     * @param  array<int, string>  $args  The function arguments
     * @return mixed The result of the function execution
     *
     * @throws InvalidArgumentException When the function is not registered
     */
    public function execute(string $name, array $data, array $args): mixed
    {
        $function = $this->get($name);

        if ($function === null) {
            throw new InvalidArgumentException(
                sprintf('Function "%s" not registered', $name)
            );
        }

        return $function->execute($data, $args);
    }

    /**
     * Returns the default value for a function.
     *
     * @param  string  $name  The function name
     * @return mixed The default value, or 0 if the function is not found
     */
    public function getDefaultValue(string $name): mixed
    {
        $function = $this->get($name);

        return $function?->getDefaultValue() ?? 0;
    }

    /**
     * Returns all registered functions.
     *
     * @return array<string, AggregateFunctionInterface> Array of function instances indexed by name
     */
    public function all(): array
    {
        return $this->functions;
    }

    /**
     * Returns only functions that return boolean results.
     *
     * @return array<string, AggregateFunctionInterface> Array of boolean functions
     */
    public function getBooleanFunctions(): array
    {
        return array_filter(
            $this->functions,
            fn ($func) => $func->returnsBoolean()
        );
    }

    /**
     * Returns only functions that return numeric results.
     *
     * @return array<string, AggregateFunctionInterface> Array of numeric functions
     */
    public function getNumericFunctions(): array
    {
        return array_filter(
            $this->functions,
            fn ($func) => ! $func->returnsBoolean()
        );
    }

    /**
     * Registers the default set of aggregate functions.
     */
    private function registerDefaultFunctions(): void
    {
        $this->register(new CountFunction);
        $this->register(new SumFunction);
        $this->register(new AvgFunction);
        $this->register(new MinFunction);
        $this->register(new MaxFunction);
        $this->register(new LengthFunction);
        $this->register(new ExistsFunction);
        $this->register(new HasFunction);
        $this->register(new AllFunction);
        $this->register(new IsEmptyFunction);
        $this->register(new MatchesFunction);
    }
}
