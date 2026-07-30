<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Services;

use AndyDefer\LaravelCluster\Parser\AggregateExpressionParser;
use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;
use AndyDefer\LaravelCluster\Services\AggregateEvaluatorService;
use AndyDefer\LaravelCluster\Tests\Fixtures\Functions\DoubleCountFunction;
use AndyDefer\LaravelCluster\Tests\Fixtures\Functions\WeightedAvgFunction;
use AndyDefer\LaravelCluster\Tests\Fixtures\TestRegistryFactory;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AggregateEvaluatorService.
 *
 * Tests cover:
 * - Evaluating aggregate expressions (COUNT, SUM, AVG, MIN, MAX, LENGTH)
 * - Evaluating boolean functions (EXISTS, HAS, ALL, IS_EMPTY)
 * - Complex expressions with AND/OR operators
 * - Direct function evaluation (evaluateDirect)
 * - Expression validation
 * - Edge cases (null values, missing paths, empty arrays)
 * - Performance with caching
 * - Custom function integration
 */
final class AggregateEvaluatorServiceTest extends TestCase
{
    private AggregateEvaluatorService $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $registry = TestRegistryFactory::create();
        $this->evaluator = new AggregateEvaluatorService($registry);
    }

    // ==================== EVALUATE TESTS ====================

    public function test_evaluate_count_greater_than(): void
    {
        $data = [
            'name' => 'John',
            'addresses' => ['Kinshasa', 'Paris', 'London'],
        ];

        $result = $this->evaluator->evaluate($data, '{COUNT(addresses) > 2}');

        $this->assertTrue($result);
    }

    public function test_evaluate_count_greater_than_false(): void
    {
        $data = [
            'name' => 'Jane',
            'addresses' => ['Kinshasa'],
        ];

        $result = $this->evaluator->evaluate($data, '{COUNT(addresses) > 2}');

        $this->assertFalse($result);
    }

    public function test_evaluate_count_equals(): void
    {
        $data = [
            'name' => 'John',
            'addresses' => ['Kinshasa', 'Paris'],
        ];

        $result = $this->evaluator->evaluate($data, '{COUNT(addresses) = 2}');

        $this->assertTrue($result);
    }

    public function test_evaluate_count_not_equals(): void
    {
        $data = [
            'name' => 'John',
            'addresses' => ['Kinshasa', 'Paris', 'London'],
        ];

        $result = $this->evaluator->evaluate($data, '{COUNT(addresses) != 2}');

        $this->assertTrue($result);
    }

    public function test_evaluate_sum_greater_than(): void
    {
        $data = [
            'name' => 'John',
            'prices' => [100, 200, 300],
        ];

        $result = $this->evaluator->evaluate($data, '{SUM(prices) > 500}');

        $this->assertTrue($result);
    }

    public function test_evaluate_sum_greater_than_false(): void
    {
        $data = [
            'name' => 'Jane',
            'prices' => [50, 75],
        ];

        $result = $this->evaluator->evaluate($data, '{SUM(prices) > 200}');

        $this->assertFalse($result);
    }

    public function test_evaluate_avg_greater_than_or_equal(): void
    {
        $data = [
            'name' => 'John',
            'scores' => [80, 90, 85],
        ];

        $result = $this->evaluator->evaluate($data, '{AVG(scores) >= 85}');

        $this->assertTrue($result);
    }

    public function test_evaluate_avg_greater_than_or_equal_false(): void
    {
        $data = [
            'name' => 'Jane',
            'scores' => [70, 75, 80],
        ];

        $result = $this->evaluator->evaluate($data, '{AVG(scores) >= 85}');

        $this->assertFalse($result);
    }

    public function test_evaluate_min_greater_than(): void
    {
        $data = [
            'name' => 'Bob',
            'scores' => [95, 98, 92],
        ];

        $result = $this->evaluator->evaluate($data, '{MIN(scores) > 90}');

        $this->assertTrue($result);
    }

    public function test_evaluate_max_less_than(): void
    {
        $data = [
            'name' => 'John',
            'scores' => [80, 90, 85],
        ];

        $result = $this->evaluator->evaluate($data, '{MAX(scores) < 95}');

        $this->assertTrue($result);
    }

    public function test_evaluate_length_greater_than(): void
    {
        $data = [
            'name' => 'John Doe',
        ];

        $result = $this->evaluator->evaluate($data, '{LENGTH(name) > 5}');

        $this->assertTrue($result);
    }

    public function test_evaluate_length_greater_than_false(): void
    {
        $data = [
            'name' => 'John',
        ];

        $result = $this->evaluator->evaluate($data, '{LENGTH(name) > 10}');

        $this->assertFalse($result);
    }

    // ==================== BOOLEAN FUNCTION TESTS ====================

    public function test_evaluate_exists_true(): void
    {
        $data = [
            'addresses' => ['Kinshasa', 'Paris'],
        ];

        $result = $this->evaluator->evaluate($data, '{EXISTS(addresses)}');

        $this->assertTrue($result);
    }

    public function test_evaluate_exists_false(): void
    {
        $data = [];

        $result = $this->evaluator->evaluate($data, '{EXISTS(addresses)}');

        $this->assertFalse($result);
    }

    public function test_evaluate_has_true(): void
    {
        $data = [
            'addresses' => [
                ['city' => 'Kinshasa', 'country' => 'RDC'],
                ['city' => 'Paris', 'country' => 'France'],
            ],
        ];

        $result = $this->evaluator->evaluate($data, '{HAS(addresses, city, "Kinshasa")}');

        $this->assertTrue($result);
    }

    public function test_evaluate_has_false(): void
    {
        $data = [
            'addresses' => [
                ['city' => 'Paris', 'country' => 'France'],
                ['city' => 'London', 'country' => 'UK'],
            ],
        ];

        $result = $this->evaluator->evaluate($data, '{HAS(addresses, city, "Kinshasa")}');

        $this->assertFalse($result);
    }

    public function test_evaluate_all_true(): void
    {
        $data = [
            'addresses' => [
                ['country' => 'RDC'],
                ['country' => 'RDC'],
            ],
        ];

        $result = $this->evaluator->evaluate($data, '{ALL(addresses, country, "RDC")}');

        $this->assertTrue($result);
    }

    public function test_evaluate_all_false(): void
    {
        $data = [
            'addresses' => [
                ['country' => 'RDC'],
                ['country' => 'France'],
            ],
        ];

        $result = $this->evaluator->evaluate($data, '{ALL(addresses, country, "RDC")}');

        $this->assertFalse($result);
    }

    public function test_evaluate_is_empty_true(): void
    {
        $data = [
            'cart' => [],
        ];

        $result = $this->evaluator->evaluate($data, '{IS_EMPTY(cart)}');

        $this->assertTrue($result);
    }

    public function test_evaluate_is_empty_false(): void
    {
        $data = [
            'cart' => ['item1', 'item2'],
        ];

        $result = $this->evaluator->evaluate($data, '{IS_EMPTY(cart)}');

        $this->assertFalse($result);
    }

    // ==================== COMPLEX EXPRESSIONS TESTS ====================

    public function test_evaluate_complex_with_and(): void
    {
        $data = [
            'name' => 'John',
            'addresses' => ['Kinshasa', 'Paris', 'London'],
            'scores' => [80, 90, 85],
        ];

        $result = $this->evaluator->evaluate(
            $data,
            '{COUNT(addresses) > 2} & {AVG(scores) >= 85}'
        );

        $this->assertTrue($result);
    }

    public function test_evaluate_complex_with_and_false(): void
    {
        $data = [
            'name' => 'Jane',
            'addresses' => ['Kinshasa'],
            'scores' => [70, 75, 80],
        ];

        $result = $this->evaluator->evaluate(
            $data,
            '{COUNT(addresses) > 2} & {AVG(scores) >= 85}'
        );

        $this->assertFalse($result);
    }

    public function test_evaluate_complex_with_or_true(): void
    {
        $data = [
            'name' => 'John',
            'addresses' => ['Kinshasa', 'Paris', 'London'],
            'scores' => [70, 75, 80],
        ];

        $result = $this->evaluator->evaluate(
            $data,
            '{COUNT(addresses) > 2} | {AVG(scores) >= 85}'
        );

        $this->assertTrue($result);
    }

    public function test_evaluate_complex_with_or_false(): void
    {
        $data = [
            'name' => 'Jane',
            'addresses' => ['Kinshasa'],
            'scores' => [70, 75, 80],
        ];

        $result = $this->evaluator->evaluate(
            $data,
            '{COUNT(addresses) > 2} | {AVG(scores) >= 85}'
        );

        $this->assertFalse($result);
    }

    public function test_evaluate_complex_with_boolean_and_numeric(): void
    {
        $data = [
            'name' => 'John',
            'addresses' => ['Kinshasa', 'Paris', 'London'],
        ];

        $result = $this->evaluator->evaluate(
            $data,
            '{EXISTS(addresses)} & {COUNT(addresses) > 2}'
        );

        $this->assertTrue($result);
    }

    public function test_evaluate_complex_with_three_expressions(): void
    {
        $data = [
            'name' => 'John',
            'addresses' => ['Kinshasa', 'Paris', 'London'],
            'scores' => [80, 90, 85],
            'prices' => [100, 200, 300],
        ];

        $result = $this->evaluator->evaluate(
            $data,
            '{COUNT(addresses) > 2} & {AVG(scores) >= 85} & {SUM(prices) > 500}'
        );

        $this->assertTrue($result);
    }

    public function test_evaluate_complex_with_mixed_operators(): void
    {
        $data = [
            'name' => 'John',
            'addresses' => ['Kinshasa', 'Paris'],
            'scores' => [80, 90, 85],
            'prices' => [100, 200, 300],
        ];

        $result = $this->evaluator->evaluate(
            $data,
            '{COUNT(addresses) > 2} & {AVG(scores) >= 85} | {SUM(prices) > 500}'
        );

        $this->assertTrue($result);
    }

    // ==================== EVALUATE DIRECT TESTS ====================

    public function test_evaluate_direct_count(): void
    {
        $data = [
            'addresses' => ['Kinshasa', 'Paris', 'London'],
        ];

        $result = $this->evaluator->evaluateDirect($data, 'COUNT', ['addresses']);

        $this->assertSame(3, $result);
    }

    public function test_evaluate_direct_sum(): void
    {
        $data = [
            'prices' => [100, 200, 300],
        ];

        $result = $this->evaluator->evaluateDirect($data, 'SUM', ['prices']);

        $this->assertSame(600.0, $result);
    }

    public function test_evaluate_direct_avg(): void
    {
        $data = [
            'scores' => [80, 90, 85],
        ];

        $result = $this->evaluator->evaluateDirect($data, 'AVG', ['scores']);

        $this->assertSame(85.0, $result);
    }

    public function test_evaluate_direct_min(): void
    {
        $data = [
            'scores' => [80, 90, 85],
        ];

        $result = $this->evaluator->evaluateDirect($data, 'MIN', ['scores']);

        $this->assertSame(80.0, $result);
    }

    public function test_evaluate_direct_max(): void
    {
        $data = [
            'scores' => [80, 90, 85],
        ];

        $result = $this->evaluator->evaluateDirect($data, 'MAX', ['scores']);

        $this->assertSame(90.0, $result);
    }

    public function test_evaluate_direct_length(): void
    {
        $data = [
            'name' => 'John Doe',
        ];

        $result = $this->evaluator->evaluateDirect($data, 'LENGTH', ['name']);

        $this->assertSame(8, $result);
    }

    public function test_evaluate_direct_exists(): void
    {
        $data = [
            'addresses' => ['Kinshasa', 'Paris'],
        ];

        $result = $this->evaluator->evaluateDirect($data, 'EXISTS', ['addresses']);

        $this->assertTrue($result);
    }

    public function test_evaluate_direct_has(): void
    {
        $data = [
            'addresses' => [
                ['city' => 'Kinshasa', 'country' => 'RDC'],
            ],
        ];

        $result = $this->evaluator->evaluateDirect($data, 'HAS', ['addresses', 'city', 'Kinshasa']);

        $this->assertTrue($result);
    }

    public function test_evaluate_direct_all(): void
    {
        $data = [
            'addresses' => [
                ['country' => 'RDC'],
                ['country' => 'RDC'],
            ],
        ];

        $result = $this->evaluator->evaluateDirect($data, 'ALL', ['addresses', 'country', 'RDC']);

        $this->assertTrue($result);
    }

    public function test_evaluate_direct_is_empty(): void
    {
        $data = [
            'cart' => [],
        ];

        $result = $this->evaluator->evaluateDirect($data, 'IS_EMPTY', ['cart']);

        $this->assertTrue($result);
    }

    public function test_evaluate_direct_unknown_function_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Function "UNKNOWN" not registered');

        $this->evaluator->evaluateDirect([], 'UNKNOWN', []);
    }

    // ==================== VALIDATE TESTS ====================

    public function test_validate_valid_expression(): void
    {
        $result = $this->evaluator->validate('{COUNT(addresses) > 2}');

        $this->assertTrue($result);
    }

    public function test_validate_valid_complex_expression(): void
    {
        $result = $this->evaluator->validate(
            '{COUNT(addresses) > 2} & {AVG(scores) >= 85}'
        );

        $this->assertTrue($result);
    }

    public function test_validate_valid_boolean_expression(): void
    {
        $result = $this->evaluator->validate('{EXISTS(addresses)}');

        $this->assertTrue($result);
    }

    public function test_validate_invalid_expression(): void
    {
        $result = $this->evaluator->validate('{INVALID(addresses) > 2}');

        $this->assertFalse($result);
    }

    public function test_validate_malformed_expression(): void
    {
        $result = $this->evaluator->validate('{COUNT(addresses > 2}');

        $this->assertFalse($result);
    }

    public function test_validate_empty_expression(): void
    {
        $result = $this->evaluator->validate('');

        $this->assertTrue($result);
    }

    public function test_validate_expression_with_invalid_args(): void
    {
        $result = $this->evaluator->validate('{COUNT(addresses, city, country) > 2}');

        $this->assertFalse($result);
    }

    // ==================== EDGE CASES TESTS ====================

    public function test_evaluate_empty_expression_returns_true(): void
    {
        $data = ['name' => 'John'];

        $result = $this->evaluator->evaluate($data, '');

        $this->assertTrue($result);
    }

    public function test_evaluate_with_null_values(): void
    {
        $data = [
            'addresses' => null,
        ];

        $result = $this->evaluator->evaluate($data, '{COUNT(addresses) > 0}');

        $this->assertFalse($result);
    }

    public function test_evaluate_with_missing_path(): void
    {
        $data = [
            'name' => 'John',
        ];

        $result = $this->evaluator->evaluate($data, '{COUNT(addresses) > 0}');

        $this->assertFalse($result);
    }

    public function test_evaluate_with_numeric_strings(): void
    {
        $data = [
            'scores' => ['80', '90', '85'],
        ];

        $result = $this->evaluator->evaluate($data, '{AVG(scores) >= 85}');

        $this->assertTrue($result);
    }

    public function test_evaluate_with_nested_path(): void
    {
        $data = [
            'user' => [
                'profile' => [
                    'addresses' => ['Kinshasa', 'Paris'],
                ],
            ],
        ];

        $result = $this->evaluator->evaluate($data, '{COUNT(user.profile.addresses) > 1}');

        $this->assertTrue($result);
    }

    public function test_evaluate_with_nested_path_false(): void
    {
        $data = [
            'user' => [
                'profile' => [
                    'addresses' => ['Kinshasa'],
                ],
            ],
        ];

        $result = $this->evaluator->evaluate($data, '{COUNT(user.profile.addresses) > 1}');

        $this->assertFalse($result);
    }

    public function test_evaluate_with_deep_nested_path(): void
    {
        $data = [
            'settings' => [
                'notifications' => [
                    ['email' => 'true', 'sms' => 'false'],
                    ['email' => 'false', 'sms' => 'true'],
                ],
            ],
        ];

        $result = $this->evaluator->evaluate($data, '{COUNT(settings.notifications) = 2}');

        $this->assertTrue($result);
    }

    // ==================== GETTER TESTS ====================

    public function test_get_registry(): void
    {
        $registry = $this->evaluator->getRegistry();

        $this->assertInstanceOf(AggregateFunctionRegistry::class, $registry);
        $this->assertTrue($registry->has('COUNT'));
        $this->assertTrue($registry->has('SUM'));
        $this->assertTrue($registry->has('AVG'));
    }

    public function test_get_parser(): void
    {
        $parser = $this->evaluator->getParser();

        $this->assertInstanceOf(
            AggregateExpressionParser::class,
            $parser
        );
    }

    // ==================== REGRESSION TESTS ====================

    public function test_evaluate_count_zero(): void
    {
        $data = [
            'addresses' => [],
        ];

        $result = $this->evaluator->evaluate($data, '{COUNT(addresses) > 0}');

        $this->assertFalse($result);
    }

    public function test_evaluate_count_zero_equals(): void
    {
        $data = [
            'addresses' => [],
        ];

        $result = $this->evaluator->evaluate($data, '{COUNT(addresses) = 0}');

        $this->assertTrue($result);
    }

    public function test_evaluate_sum_empty_array(): void
    {
        $data = [
            'prices' => [],
        ];

        $result = $this->evaluator->evaluate($data, '{SUM(prices) > 0}');

        $this->assertFalse($result);
    }

    public function test_evaluate_avg_empty_array(): void
    {
        $data = [
            'scores' => [],
        ];

        $result = $this->evaluator->evaluate($data, '{AVG(scores) >= 0}');

        $this->assertTrue($result);
    }

    // ==================== PERFORMANCE TESTS ====================

    public function test_evaluate_performance_with_caching(): void
    {
        $data = [
            'addresses' => ['Kinshasa', 'Paris', 'London'],
        ];

        $expression = '{COUNT(addresses) > 2}';
        $iterations = 1000;

        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $this->evaluator->evaluate($data, $expression);
        }

        $end = microtime(true);

        $this->assertLessThan(1.0, $end - $start);
    }

    // ==================== INTEGRATION TESTS ====================

    public function test_evaluate_with_custom_function(): void
    {
        $registry = $this->evaluator->getRegistry();
        $registry->register(new DoubleCountFunction);

        $data = ['addresses' => ['Kinshasa', 'Paris', 'London']];
        $result = $this->evaluator->evaluate($data, '{DOUBLE_COUNT(addresses) > 4}');

        $this->assertTrue($result);
    }

    public function test_evaluate_direct_with_variable_argument(): void
    {
        $registry = $this->evaluator->getRegistry();
        $registry->register(new WeightedAvgFunction);

        $data = [
            'scores' => [80, 90, 85],
            'weights' => [1, 2, 1],
        ];

        $result = $this->evaluator->evaluateDirect($data, 'WEIGHTED_AVG', ['scores', '$weights']);

        $this->assertSame(86.25, $result);
    }
}
