<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Registry;

use AndyDefer\LaravelCluster\Contracts\Filters\AggregateFunctionInterface;
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

final class AggregateFunctionRegistry
{
    private array $functions = [];

    public function __construct()
    {
        $this->registerDefaultFunctions();
    }

    public function register(AggregateFunctionInterface $function): self
    {
        $this->functions[strtoupper($function->getName())] = $function;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->functions[strtoupper($name)]);
    }

    public function get(string $name): ?AggregateFunctionInterface
    {
        return $this->functions[strtoupper($name)] ?? null;
    }

    public function execute(string $name, array $data, array $args): mixed
    {
        $function = $this->get($name);

        if ($function === null) {
            throw new \InvalidArgumentException(
                sprintf('Function "%s" not registered', $name)
            );
        }

        return $function->execute($data, $args);
    }

    public function getDefaultValue(string $name): mixed
    {
        $function = $this->get($name);

        return $function?->getDefaultValue() ?? 0;
    }

    public function all(): array
    {
        return $this->functions;
    }

    public function getBooleanFunctions(): array
    {
        return array_filter(
            $this->functions,
            fn ($func) => $func->returnsBoolean()
        );
    }

    public function getNumericFunctions(): array
    {
        return array_filter(
            $this->functions,
            fn ($func) => ! $func->returnsBoolean()
        );
    }

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
    }
}
