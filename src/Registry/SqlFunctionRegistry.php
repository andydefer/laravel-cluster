<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Registry;

use AndyDefer\LaravelCluster\Contracts\SqlFunctionInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\SqlFunctions\AvgFunction;
use AndyDefer\LaravelCluster\SqlFunctions\CountFunction;
use AndyDefer\LaravelCluster\SqlFunctions\JsonLengthFunction;
use AndyDefer\LaravelCluster\SqlFunctions\LengthFunction;
use AndyDefer\LaravelCluster\SqlFunctions\MaxFunction;
use AndyDefer\LaravelCluster\SqlFunctions\MinFunction;
use AndyDefer\LaravelCluster\SqlFunctions\SumFunction;

final class SqlFunctionRegistry
{
    private array $functions = [];

    public function __construct()
    {
        $this->registerDefaultFunctions();
    }

    public function register(SqlFunctionInterface $function): self
    {
        $this->functions[strtoupper($function->getName())] = $function;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->functions[strtoupper($name)]);
    }

    public function get(string $name): ?SqlFunctionInterface
    {
        return $this->functions[strtoupper($name)] ?? null;
    }

    public function toSql(string $name, string $column, string $path, DatabaseDriver $driver): ?string
    {
        $function = $this->get($name);

        if ($function === null) {
            return null;
        }

        return $function->toSql($column, $path, $driver);
    }

    public function execute(string $name, mixed $value): mixed
    {
        $function = $this->get($name);

        if ($function === null) {
            return $value;
        }

        return $function->execute($value);
    }

    public function getDefaultValue(string $name): mixed
    {
        $function = $this->get($name);

        if ($function === null) {
            return null;
        }

        return $function->getDefaultValue();
    }

    public function validateArgs(string $name, array $args): bool
    {
        $function = $this->get($name);

        if ($function === null) {
            return false;
        }

        return $function->validateArgs($args);
    }

    public function all(): array
    {
        return $this->functions;
    }

    public function getReturnType(string $name): ?string
    {
        $function = $this->get($name);

        if ($function === null) {
            return null;
        }

        return $function->getReturnType();
    }

    public function getNames(): array
    {
        return array_keys($this->functions);
    }

    private function registerDefaultFunctions(): void
    {
        $this->register(new CountFunction);
        $this->register(new SumFunction);
        $this->register(new AvgFunction);
        $this->register(new MinFunction);
        $this->register(new MaxFunction);
        $this->register(new LengthFunction);
        $this->register(new JsonLengthFunction);
    }
}
