<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Services;

use AndyDefer\LaravelCluster\Parser\AggregateExpressionParser;
use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;
use InvalidArgumentException;

/**
 * Service for evaluating aggregate function expressions against data arrays.
 *
 * This service parses and evaluates complex expressions containing aggregate
 * functions like COUNT, SUM, AVG, etc. It supports logical operators (&, |)
 * and GROUP function for grouping complex expressions.
 *
 * @example
 * $service = new AggregateEvaluatorService();
 * $data = ['addresses' => ['a', 'b', 'c'], 'status' => 'active'];
 *
 * // Simple function
 * $service->evaluate($data, 'COUNT(addresses) > 2'); // true
 *
 * // Complex expression with GROUP
 * $service->evaluate($data, '{GROUP({COUNT(addresses) > 1} & {AVG(scores) >= 85})} | {HAS(tags, "php")}');
 */
final class AggregateEvaluatorService
{
    private AggregateFunctionRegistry $registry;

    private AggregateExpressionParser $parser;

    /**
     * Constructor.
     *
     * @param  AggregateFunctionRegistry|null  $registry  The function registry
     */
    public function __construct(?AggregateFunctionRegistry $registry = null)
    {
        $this->registry = $registry ?? new AggregateFunctionRegistry;
        $this->parser = new AggregateExpressionParser($this->registry);
    }

    /**
     * Evaluates an expression against the provided data.
     *
     * @param  array<string, mixed>  $data  The data to evaluate against
     * @param  string  $expression  The expression to evaluate
     * @return bool True if the expression evaluates to true
     */
    public function evaluate(array $data, string $expression): bool
    {
        $expression = trim($expression);

        if ($expression === '') {
            return true;
        }

        return $this->evaluateComplex($data, $expression);
    }

    /**
     * Evaluates a complex expression with logical operators.
     *
     * @param  array<string, mixed>  $data  The data to evaluate against
     * @param  string  $expression  The expression to evaluate
     * @return bool True if the expression evaluates to true
     */
    private function evaluateComplex(array $data, string $expression): bool
    {
        $parts = $this->parser->split($expression);

        if (count($parts) === 1) {
            return $this->evaluateSingle($data, $parts[0]['expression']);
        }

        $result = null;

        foreach ($parts as $part) {
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

    /**
     * Evaluates a single function expression.
     *
     * @param  array<string, mixed>  $data  The data to evaluate against
     * @param  string  $expression  The expression to evaluate
     * @return bool True if the expression evaluates to true
     */
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

        // Si c'est GROUP, le résultat est l'expression à évaluer
        if ($functionName === 'GROUP') {
            if (is_string($result)) {
                return $this->evaluateComplex($data, $result);
            }

            return (bool) $result;
        }

        $function = $this->registry->get($functionName);

        if ($function && $function->returnsBoolean()) {
            return (bool) $result;
        }

        if ($operator === null) {
            return (bool) $result;
        }

        return $operator->evaluate($result, $value);
    }

    /**
     * Executes a function directly without expression parsing.
     *
     * @param  array<string, mixed>  $data  The data to evaluate against
     * @param  string  $functionName  The name of the function to execute
     * @param  array<int, string>  $args  The function arguments
     * @return mixed The result of the function execution
     *
     * @throws InvalidArgumentException When the function is not registered
     */
    public function evaluateDirect(array $data, string $functionName, array $args = []): mixed
    {
        $functionName = strtoupper($functionName);

        if (! $this->registry->has($functionName)) {
            throw new InvalidArgumentException(
                sprintf('Function "%s" not registered', $functionName)
            );
        }

        return $this->registry->execute($functionName, $data, $args);
    }

    /**
     * Validates an expression syntax.
     *
     * @param  string  $expression  The expression to validate
     * @return bool True if the expression is syntactically valid
     */
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

    /**
     * Returns the function registry.
     *
     * @return AggregateFunctionRegistry The function registry
     */
    public function getRegistry(): AggregateFunctionRegistry
    {
        return $this->registry;
    }

    /**
     * Returns the expression parser.
     *
     * @return AggregateExpressionParser The expression parser
     */
    public function getParser(): AggregateExpressionParser
    {
        return $this->parser;
    }
}
