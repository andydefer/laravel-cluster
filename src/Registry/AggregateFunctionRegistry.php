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
     * Validates a function name against SCREAMING_SNAKE_CASE convention.
     *
     * Rules:
     * - Must start with a letter (A-Z)
     * - Can contain letters (A-Z), numbers (0-9), and underscores (_)
     * - Must be in uppercase
     *
     * @param  string  $name  The function name to validate
     * @return bool True if the name is valid
     */
    private function isValidFunctionName(string $name): bool
    {
        // Must start with a letter, can contain letters, numbers and underscores
        // Must be all uppercase
        return (bool) preg_match('/^[A-Z][A-Z0-9_]*$/', $name);
    }

    /**
     * Registers an aggregate function in the registry.
     *
     * @param  AggregateFunctionInterface  $function  The function to register
     * @return self Returns the registry instance for method chaining
     *
     * @throws InvalidArgumentException When a function with the same name is already registered
     * @throws InvalidArgumentException When the function name is invalid
     */
    public function register(AggregateFunctionInterface $function): self
    {
        $originalName = $function->getName();
        $name = strtoupper($originalName);

        // Validate function name format - check the ORIGINAL name (must already be uppercase)
        if (! $this->isValidFunctionName($originalName)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid function name "%s". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.',
                    $originalName
                )
            );
        }

        if ($this->has($name)) {
            throw new InvalidArgumentException(
                sprintf('Function "%s" is already registered. Cannot register duplicate.', $name)
            );
        }

        $this->functions[$name] = $function;

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
     * Returns the names of all registered functions.
     *
     * @return array<string> Array of function names
     */
    public function getNames(): array
    {
        return array_keys($this->functions);
    }

    /**
     * Registers the default set of aggregate functions.
     *
     * @throws InvalidArgumentException If a default function is already registered
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
