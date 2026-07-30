<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Services;

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
use AndyDefer\LaravelCluster\Parser\AggregateExpressionParser;
use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;
use InvalidArgumentException;

final class AggregateEvaluatorService
{
    private AggregateFunctionRegistry $registry;

    private AggregateExpressionParser $parser;

    private bool $debug = true;

    public function __construct(?AggregateFunctionRegistry $registry = null)
    {
        $this->registry = $registry ?? $this->createDefaultRegistry();
        $this->parser = new AggregateExpressionParser($this->registry);
    }

    public function evaluate(array $data, string $expression): bool
    {

        $expression = trim($expression);

        if ($expression === '') {

            return true;
        }

        $result = $this->evaluateComplex($data, $expression);

        return $result;
    }

    private function evaluateComplex(array $data, string $expression): bool
    {

        $parts = $this->parser->split($expression);

        if (count($parts) === 1) {

            return $this->evaluateSingle($data, $parts[0]['expression']);
        }

        $result = null;

        foreach ($parts as $index => $part) {

            $value = $this->evaluateSingle($data, $part['expression']);

            if ($result === null) {
                $result = $value;

                continue;
            }

            $operator = $part['operator'] ?? '&';

            $result = match ($operator) {
                '&' => $result && $value,
                '|' => $result || $value,
                default => $result && $value,
            };

        }

        return $result ?? false;
    }

    private function evaluateSingle(array $data, string $expression): bool
    {

        $parsed = $this->parser->parse($expression);

        if ($parsed === null) {

            return false;
        }

        $functionName = $parsed['functionName'];
        $args = $parsed['args'];
        $operator = $parsed['operator'];
        $value = $parsed['value'];

        $result = $this->registry->execute($functionName, $data, $args);

        // ✅ DEBUG : Afficher le type et la valeur du résultat

        $function = $this->registry->get($functionName);

        if ($function && $function->returnsBoolean()) {
            $boolResult = (bool) $result;

            return $boolResult;
        }

        if ($operator === null) {
            $boolResult = (bool) $result;

            return $boolResult;
        }

        // ✅ DEBUG : Afficher la comparaison

        $operatorResult = $operator->evaluate($result, $value);

        return $operatorResult;
    }

    public function evaluateDirect(array $data, string $functionName, array $args = []): mixed
    {

        $functionName = strtoupper($functionName);

        if (! $this->registry->has($functionName)) {
            throw new InvalidArgumentException(
                sprintf('Function "%s" not registered', $functionName)
            );
        }

        $result = $this->registry->execute($functionName, $data, $args);

        return $result;
    }

    public function validate(string $expression): bool
    {
        try {
            $parts = $this->parser->split($expression);

            foreach ($parts as $part) {
                $parsed = $this->parser->parse($part['expression']);

                if ($parsed === null) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getRegistry(): AggregateFunctionRegistry
    {
        return $this->registry;
    }

    public function getParser(): AggregateExpressionParser
    {
        return $this->parser;
    }

    private function createDefaultRegistry(): AggregateFunctionRegistry
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
