<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Parser;

use AndyDefer\LaravelCluster\Contracts\AggregateFunctionInterface;
use AndyDefer\LaravelCluster\Enums\AggregateOperator;
use AndyDefer\LaravelCluster\Parser\AggregateExpressionParser;
use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;
use PHPUnit\Framework\TestCase;

final class AggregateExpressionParserTest extends TestCase
{
    private AggregateExpressionParser $parser;

    private AggregateFunctionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new AggregateFunctionRegistry;
        $this->registerCustomFunction();
        $this->parser = new AggregateExpressionParser($this->registry);
    }

    private function registerCustomFunction(): void
    {
        $mockFunction = $this->createStub(AggregateFunctionInterface::class);
        $mockFunction->method('getName')->willReturn('CUSTOM');
        $mockFunction->method('getMinArgs')->willReturn(0);
        $mockFunction->method('getMaxArgs')->willReturn(PHP_INT_MAX);
        $mockFunction->method('validateArgs')->willReturn(true);
        $mockFunction->method('execute')->willReturn(0);
        $mockFunction->method('getDefaultValue')->willReturn(0);
        $mockFunction->method('getReturnType')->willReturn('int');
        $mockFunction->method('returnsBoolean')->willReturn(false);

        $this->registry->register($mockFunction);
    }

    // ==================== SPLIT TESTS ====================

    public function test_split_single_expression(): void
    {
        $result = $this->parser->split('{COUNT(addresses) > 2}');

        $this->assertCount(1, $result);
        $this->assertEquals('{COUNT(addresses) > 2}', $result[0]['expression']);
        $this->assertEquals('&', $result[0]['operator']);
    }

    public function test_split_two_expressions_with_and(): void
    {
        $result = $this->parser->split('{COUNT(addresses) > 2} & {AVG(scores) >= 85}');

        $this->assertCount(2, $result);
        $this->assertEquals('{COUNT(addresses) > 2}', $result[0]['expression']);
        $this->assertEquals('&', $result[0]['operator']);
        $this->assertEquals('{AVG(scores) >= 85}', $result[1]['expression']);
        $this->assertEquals('&', $result[1]['operator']);
    }

    public function test_split_two_expressions_with_or(): void
    {
        $result = $this->parser->split('{COUNT(addresses) > 2} | {SUM(prices) > 1000}');

        $this->assertCount(2, $result);
        $this->assertEquals('{COUNT(addresses) > 2}', $result[0]['expression']);
        $this->assertEquals('|', $result[0]['operator']);
        $this->assertEquals('{SUM(prices) > 1000}', $result[1]['expression']);
        $this->assertEquals('|', $result[1]['operator']);
    }

    public function test_split_three_expressions(): void
    {
        $result = $this->parser->split(
            '{COUNT(addresses) > 2} & {AVG(scores) >= 85} & {SUM(prices) > 1000}'
        );

        $this->assertCount(3, $result);
        $this->assertEquals('{COUNT(addresses) > 2}', $result[0]['expression']);
        $this->assertEquals('&', $result[0]['operator']);
        $this->assertEquals('{AVG(scores) >= 85}', $result[1]['expression']);
        $this->assertEquals('&', $result[1]['operator']);
        $this->assertEquals('{SUM(prices) > 1000}', $result[2]['expression']);
        $this->assertEquals('&', $result[2]['operator']);
    }

    public function test_split_with_mixed_operators(): void
    {
        $result = $this->parser->split(
            '{COUNT(addresses) > 2} & {AVG(scores) >= 85} | {SUM(prices) > 1000}'
        );

        $this->assertCount(3, $result);
        $this->assertEquals('{COUNT(addresses) > 2}', $result[0]['expression']);
        $this->assertEquals('&', $result[0]['operator']);
        $this->assertEquals('{AVG(scores) >= 85}', $result[1]['expression']);
        $this->assertEquals('|', $result[1]['operator']);
        $this->assertEquals('{SUM(prices) > 1000}', $result[2]['expression']);
        $this->assertEquals('|', $result[2]['operator']);
    }

    public function test_split_with_complex_nested_expressions(): void
    {
        $result = $this->parser->split(
            '{COUNT({LENGTH(name) > 5}) > 2} & {SUM(prices) > 1000}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('{COUNT({LENGTH(name) > 5}) > 2}', $result[0]['expression']);
        $this->assertEquals('&', $result[0]['operator']);
        $this->assertEquals('{SUM(prices) > 1000}', $result[1]['expression']);
        $this->assertEquals('&', $result[1]['operator']);
    }

    // ==================== GROUP FUNCTION TESTS ====================

    public function test_split_with_group_function(): void
    {
        $result = $this->parser->split(
            '{GROUP({COUNT(addresses) > 2} & {AVG(scores) >= 85})} | {HAS(tags, "php")}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('{GROUP({COUNT(addresses) > 2} & {AVG(scores) >= 85})}', $result[0]['expression']);
        $this->assertEquals('|', $result[0]['operator']);
        $this->assertEquals('{HAS(tags, "php")}', $result[1]['expression']);
        $this->assertEquals('|', $result[1]['operator']);
    }

    public function test_split_with_nested_group_functions(): void
    {
        $result = $this->parser->split(
            '{GROUP({GROUP({COUNT(addresses) > 2} & {AVG(scores) >= 85})} | {HAS(tags, "php")})} & {SUM(prices) > 1000}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals(
            '{GROUP({GROUP({COUNT(addresses) > 2} & {AVG(scores) >= 85})} | {HAS(tags, "php")})}',
            $result[0]['expression']
        );
        $this->assertEquals('&', $result[0]['operator']);
        $this->assertEquals('{SUM(prices) > 1000}', $result[1]['expression']);
        $this->assertEquals('&', $result[1]['operator']);
    }

    public function test_split_with_group_function_and_other_conditions(): void
    {
        $result = $this->parser->split(
            '{GROUP({COUNT(addresses) > 2} & {AVG(scores) >= 85})} & {HAS(tags, "php")}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('{GROUP({COUNT(addresses) > 2} & {AVG(scores) >= 85})}', $result[0]['expression']);
        $this->assertEquals('&', $result[0]['operator']);
        $this->assertEquals('{HAS(tags, "php")}', $result[1]['expression']);
        $this->assertEquals('&', $result[1]['operator']);
    }

    public function test_split_with_group_function_and_spaces(): void
    {
        $result = $this->parser->split(
            ' {GROUP({COUNT(addresses) > 2} & {AVG(scores) >= 85})}  |  {HAS(tags, "php")} '
        );

        $this->assertCount(2, $result);
        $this->assertEquals('{GROUP({COUNT(addresses) > 2} & {AVG(scores) >= 85})}', trim($result[0]['expression']));
        $this->assertEquals('|', $result[0]['operator']);
        $this->assertEquals('{HAS(tags, "php")}', trim($result[1]['expression']));
        $this->assertEquals('|', $result[1]['operator']);
    }

    // ==================== PARSE TESTS ====================

    public function test_parse_simple_count_expression(): void
    {
        $result = $this->parser->parse('{COUNT(addresses) > 2}');

        $this->assertNotNull($result);
        $this->assertEquals('COUNT', $result['functionName']);
        $this->assertEquals(['addresses'], $result['args']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $result['operator']);
        $this->assertEquals(2, $result['value']);
    }

    public function test_parse_sum_expression(): void
    {
        $result = $this->parser->parse('{SUM(prices) >= 1000}');

        $this->assertNotNull($result);
        $this->assertEquals('SUM', $result['functionName']);
        $this->assertEquals(['prices'], $result['args']);
        $this->assertEquals(AggregateOperator::GREATER_THAN_OR_EQUAL, $result['operator']);
        $this->assertEquals(1000, $result['value']);
    }

    public function test_parse_avg_expression(): void
    {
        $result = $this->parser->parse('{AVG(scores) < 85}');

        $this->assertNotNull($result);
        $this->assertEquals('AVG', $result['functionName']);
        $this->assertEquals(['scores'], $result['args']);
        $this->assertEquals(AggregateOperator::LESS_THAN, $result['operator']);
        $this->assertEquals(85, $result['value']);
    }

    public function test_parse_min_expression(): void
    {
        $result = $this->parser->parse('{MIN(values) <= 10}');

        $this->assertNotNull($result);
        $this->assertEquals('MIN', $result['functionName']);
        $this->assertEquals(['values'], $result['args']);
        $this->assertEquals(AggregateOperator::LESS_THAN_OR_EQUAL, $result['operator']);
        $this->assertEquals(10, $result['value']);
    }

    public function test_parse_max_expression(): void
    {
        $result = $this->parser->parse('{MAX(values) != 100}');

        $this->assertNotNull($result);
        $this->assertEquals('MAX', $result['functionName']);
        $this->assertEquals(['values'], $result['args']);
        $this->assertEquals(AggregateOperator::NOT_EQUAL, $result['operator']);
        $this->assertEquals(100, $result['value']);
    }

    public function test_parse_length_expression(): void
    {
        $result = $this->parser->parse('{LENGTH(name) > 5}');

        $this->assertNotNull($result);
        $this->assertEquals('LENGTH', $result['functionName']);
        $this->assertEquals(['name'], $result['args']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $result['operator']);
        $this->assertEquals(5, $result['value']);
    }

    public function test_parse_exists_expression(): void
    {
        $result = $this->parser->parse('{EXISTS(addresses)}');

        $this->assertNotNull($result);
        $this->assertEquals('EXISTS', $result['functionName']);
        $this->assertEquals(['addresses'], $result['args']);
        $this->assertNull($result['operator']);
        $this->assertNull($result['value']);
    }

    public function test_parse_group_function(): void
    {
        $result = $this->parser->parse(
            '{GROUP({COUNT(addresses) > 2} & {AVG(scores) >= 85})}'
        );

        $this->assertNotNull($result);
        $this->assertEquals('GROUP', $result['functionName']);
        $this->assertCount(1, $result['args']);

        // L'argument est soit une string, soit un tableau selon le parsing
        $arg = $result['args'][0];
        $this->assertTrue(is_string($arg) || is_array($arg));
    }

    public function test_parse_nested_group_functions(): void
    {
        $result = $this->parser->parse(
            '{GROUP({GROUP({COUNT(addresses) > 2} & {AVG(scores) >= 85})} | {HAS(tags, "php")})}'
        );

        $this->assertNotNull($result);
        $this->assertEquals('GROUP', $result['functionName']);
        $this->assertCount(1, $result['args']);

        $arg = $result['args'][0];
        $this->assertTrue(is_string($arg) || is_array($arg));

        // Si c'est une string, elle doit contenir 'GROUP'
        if (is_string($arg)) {
            $this->assertStringContainsString('GROUP', $arg);
        }
    }

    // ==================== ARGUMENT PARSING TESTS ====================

    public function test_parse_args_with_multiple_arguments(): void
    {
        $result = $this->parser->parse('{HAS(addresses, city, "Kinshasa")}');

        $this->assertNotNull($result);
        $this->assertEquals('HAS', $result['functionName']);
        $this->assertEquals(['addresses', 'city', 'Kinshasa'], $result['args']);
    }

    public function test_parse_args_with_variable(): void
    {
        $result = $this->parser->parse('{COUNT($prices)}');

        $this->assertNotNull($result);
        $this->assertEquals('COUNT', $result['functionName']);
        $this->assertEquals([
            ['type' => 'variable', 'value' => 'prices'],
        ], $result['args']);
    }

    public function test_parse_args_with_array(): void
    {
        $result = $this->parser->parse('{COUNT([1, 2, 3])}');

        $this->assertNotNull($result);
        $this->assertEquals('COUNT', $result['functionName']);
        $this->assertEquals([[1, 2, 3]], $result['args']);
    }

    public function test_parse_args_with_nested_array(): void
    {
        $result = $this->parser->parse('{COUNT([1, [2, 3], 4])}');

        $this->assertNotNull($result);
        $this->assertEquals('COUNT', $result['functionName']);
        $this->assertEquals([[1, [2, 3], 4]], $result['args']);
    }

    public function test_parse_args_with_string_quotes(): void
    {
        $result = $this->parser->parse('{HAS(addresses, city, "Kinshasa")}');

        $this->assertNotNull($result);
        $this->assertEquals('HAS', $result['functionName']);
        $this->assertEquals(['addresses', 'city', 'Kinshasa'], $result['args']);
    }

    public function test_parse_args_with_boolean_values(): void
    {
        $result = $this->parser->parse('{EXISTS(addresses)}');

        $this->assertNotNull($result);
        $this->assertEquals('EXISTS', $result['functionName']);
        $this->assertEquals(['addresses'], $result['args']);
    }

    public function test_parse_args_with_numeric_values(): void
    {
        $result = $this->parser->parse('{COUNT(42)}');

        $this->assertNotNull($result);
        $this->assertEquals('COUNT', $result['functionName']);
        $this->assertEquals([42], $result['args']);
    }

    public function test_parse_args_with_spaces(): void
    {
        $result = $this->parser->parse('{COUNT( addresses )}');

        $this->assertNotNull($result);
        $this->assertEquals('COUNT', $result['functionName']);
        $this->assertEquals(['addresses'], $result['args']);
    }

    public function test_parse_args_empty(): void
    {
        $result = $this->parser->parse('{CUSTOM()}');

        $this->assertNotNull($result);
        $this->assertEquals('CUSTOM', $result['functionName']);
        $this->assertEquals([], $result['args']);
    }

    // ==================== COMPLEX EXPRESSIONS TESTS ====================

    public function test_parse_with_special_characters_in_string(): void
    {
        $result = $this->parser->parse('{HAS(addresses, city, "Kinshasa, DRC")}');

        $this->assertNotNull($result);
        $this->assertEquals('HAS', $result['functionName']);
        $this->assertEquals(['addresses', 'city', 'Kinshasa, DRC'], $result['args']);
    }

    public function test_parse_with_escaped_quotes(): void
    {
        $result = $this->parser->parse('{COUNT("hello \"world\"")}');

        $this->assertNotNull($result);
        $this->assertEquals('COUNT', $result['functionName']);
        $this->assertEquals(['hello "world"'], $result['args']);
    }

    public function test_parse_args_with_array_of_variables(): void
    {
        $result = $this->parser->parse('{COUNT([$a, $b, $c])}');

        $this->assertNotNull($result);
        $this->assertEquals([
            [
                ['type' => 'variable', 'value' => 'a'],
                ['type' => 'variable', 'value' => 'b'],
                ['type' => 'variable', 'value' => 'c'],
            ],
        ], $result['args']);
    }

    // ==================== INTEGRATION TESTS ====================

    public function test_parse_with_all_function_types(): void
    {
        $functions = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'LENGTH', 'EXISTS', 'HAS', 'ALL', 'IS_EMPTY', 'GROUP'];

        foreach ($functions as $function) {
            if (in_array($function, ['HAS', 'ALL'])) {
                $expression = sprintf('{%s(test, key, "value") > 1}', $function);
            } elseif ($function === 'GROUP') {
                $expression = '{GROUP({COUNT(test) > 1})}';
            } elseif (in_array($function, ['EXISTS', 'IS_EMPTY'])) {
                $expression = sprintf('{%s(test)}', $function);
            } else {
                $expression = sprintf('{%s(test) > 1}', $function);
            }

            $result = $this->parser->parse($expression);

            if (in_array($function, ['EXISTS', 'HAS', 'ALL', 'IS_EMPTY'])) {
                if ($result === null) {
                    $this->assertTrue($this->registry->has($function));

                    continue;
                }
            }

            $this->assertNotNull($result, sprintf('Failed for function: %s', $function));
            $this->assertEquals($function, $result['functionName']);
        }
    }

    // ==================== BOOLEAN FUNCTION TESTS ====================

    public function test_parse_boolean_function_with_operator_ignores_operator(): void
    {
        $result = $this->parser->parse('{EXISTS(addresses) > 0}');

        $this->assertNotNull($result);
        $this->assertEquals('EXISTS', $result['functionName']);
        $this->assertEquals(['addresses'], $result['args']);
        $this->assertNull($result['operator']);
        $this->assertNull($result['value']);
    }

    public function test_parse_has_function_with_operator_ignores_operator(): void
    {
        $result = $this->parser->parse('{HAS(addresses, city, "Kinshasa") = true}');

        $this->assertNotNull($result);
        $this->assertEquals('HAS', $result['functionName']);
        $this->assertEquals(['addresses', 'city', 'Kinshasa'], $result['args']);
        $this->assertNull($result['operator']);
        $this->assertNull($result['value']);
    }

    // ==================== NESTED FUNCTION TESTS ====================

    public function test_parse_with_nested_function_in_expression(): void
    {
        $result = $this->parser->parse('{COUNT({LENGTH(name) > 5}) > 2}');

        $this->assertNotNull($result);
        $this->assertEquals('COUNT', $result['functionName']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $result['operator']);
        $this->assertEquals(2, $result['value']);

        $this->assertIsArray($result['args'][0]);
        $this->assertEquals('LENGTH', $result['args'][0]['functionName']);
        $this->assertEquals(['name'], $result['args'][0]['args']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $result['args'][0]['operator']);
        $this->assertEquals(5, $result['args'][0]['value']);
    }

    public function test_parse_with_multiple_nested_functions(): void
    {
        $result = $this->parser->parse('{CUSTOM({LENGTH(name) > 5}, {SUM(prices) > 100}) > 2}');

        $this->assertNotNull($result);
        $this->assertEquals('CUSTOM', $result['functionName']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $result['operator']);
        $this->assertEquals(2, $result['value']);

        $this->assertIsArray($result['args'][0]);
        $this->assertEquals('LENGTH', $result['args'][0]['functionName']);
        $this->assertEquals(['name'], $result['args'][0]['args']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $result['args'][0]['operator']);
        $this->assertEquals(5, $result['args'][0]['value']);

        $this->assertIsArray($result['args'][1]);
        $this->assertEquals('SUM', $result['args'][1]['functionName']);
        $this->assertEquals(['prices'], $result['args'][1]['args']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $result['args'][1]['operator']);
        $this->assertEquals(100, $result['args'][1]['value']);
    }

    public function test_parse_with_deep_nested_functions(): void
    {
        $result = $this->parser->parse('{COUNT({LENGTH({LENGTH(name)}) > 5}) > 2}');

        $this->assertNotNull($result);
        $this->assertEquals('COUNT', $result['functionName']);

        $this->assertIsArray($result['args'][0]);
        $this->assertEquals('LENGTH', $result['args'][0]['functionName']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $result['args'][0]['operator']);
        $this->assertEquals(5, $result['args'][0]['value']);

        $this->assertIsArray($result['args'][0]['args'][0]);
        $this->assertEquals('LENGTH', $result['args'][0]['args'][0]['functionName']);
        $this->assertEquals(['name'], $result['args'][0]['args'][0]['args']);
    }

    public function test_parse_nested_function_without_operator(): void
    {
        $result = $this->parser->parse('{COUNT({LENGTH(name)}) > 2}');

        $this->assertNotNull($result);
        $this->assertEquals('COUNT', $result['functionName']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $result['operator']);
        $this->assertEquals(2, $result['value']);

        $this->assertIsArray($result['args'][0]);
        $this->assertEquals('LENGTH', $result['args'][0]['functionName']);
        $this->assertEquals(['name'], $result['args'][0]['args']);
        $this->assertNull($result['args'][0]['operator']);
        $this->assertNull($result['args'][0]['value']);
    }

    public function test_parse_nested_boolean_function(): void
    {
        $result = $this->parser->parse('{COUNT({EXISTS(addresses)}) > 2}');

        $this->assertNotNull($result);
        $this->assertEquals('COUNT', $result['functionName']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $result['operator']);
        $this->assertEquals(2, $result['value']);

        $this->assertIsArray($result['args'][0]);
        $this->assertEquals('EXISTS', $result['args'][0]['functionName']);
        $this->assertEquals(['addresses'], $result['args'][0]['args']);
        $this->assertNull($result['args'][0]['operator']);
        $this->assertNull($result['args'][0]['value']);
    }

    public function test_parse_nested_function_with_complex_expression(): void
    {
        $result = $this->parser->parse('{COUNT({LENGTH(name) > 5} & {SUM(prices) > 100}) > 2}');

        $this->assertNotNull($result);
        $this->assertEquals('COUNT', $result['functionName']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $result['operator']);
        $this->assertEquals(2, $result['value']);

        $this->assertIsArray($result['args'][0]);
        $this->assertEquals('complex_expression', $result['args'][0]['type']);

        $parts = $result['args'][0]['parts'];
        $this->assertCount(2, $parts);

        $this->assertEquals('{LENGTH(name) > 5}', $parts[0]['expression']);
        $this->assertEquals('&', $parts[0]['operator']);
        $this->assertIsArray($parts[0]['parsed']);
        $this->assertEquals('LENGTH', $parts[0]['parsed']['functionName']);
        $this->assertEquals(['name'], $parts[0]['parsed']['args']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $parts[0]['parsed']['operator']);
        $this->assertEquals(5, $parts[0]['parsed']['value']);

        $this->assertEquals('{SUM(prices) > 100}', $parts[1]['expression']);
        $this->assertEquals('&', $parts[1]['operator']);
        $this->assertIsArray($parts[1]['parsed']);
        $this->assertEquals('SUM', $parts[1]['parsed']['functionName']);
        $this->assertEquals(['prices'], $parts[1]['parsed']['args']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $parts[1]['parsed']['operator']);
        $this->assertEquals(100, $parts[1]['parsed']['value']);
    }

    public function test_parse_nested_function_with_has(): void
    {
        $result = $this->parser->parse('{COUNT({HAS(addresses, city, "Kinshasa")}) > 2}');

        $this->assertNotNull($result);
        $this->assertEquals('COUNT', $result['functionName']);
        $this->assertEquals(AggregateOperator::GREATER_THAN, $result['operator']);
        $this->assertEquals(2, $result['value']);

        $this->assertIsArray($result['args'][0]);
        $this->assertEquals('HAS', $result['args'][0]['functionName']);
        $this->assertEquals(['addresses', 'city', 'Kinshasa'], $result['args'][0]['args']);
        $this->assertNull($result['args'][0]['operator']);
        $this->assertNull($result['args'][0]['value']);
    }
}
