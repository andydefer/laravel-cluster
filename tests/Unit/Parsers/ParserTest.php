<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Parser;

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\FunctionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Parser;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Parser class.
 *
 * Tests cover:
 * - Simple equality and comparison operators (=, !=, >, <, >=, <=, ===, !==, <=>)
 * - Presence/absence operators (!key)
 * - Logical operators (AND, OR)
 * - Parentheses and nested expressions
 * - EXISTS (*) and NOT_EXISTS (#) operators
 * - LIKE (=~) and NOT_LIKE (!~) operators
 * - Sub-conditions (addresses[city=kinshasa])
 * - SQL functions (COUNT, SUM, AVG, MIN, MAX, LENGTH, JSON_LENGTH)
 * - Parser cache
 * - Error handling and edge cases
 */
final class ParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new Parser;
    }

    // ==================== SIMPLE CONDITIONS TESTS ====================

    public function test_parse_simple_equality(): void
    {
        $ast = $this->parser->parse('status=active');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'active']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'inactive']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_simple_not_equal(): void
    {
        $ast = $this->parser->parse('status!=inactive');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'active']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'inactive']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_simple_greater_than(): void
    {
        $ast = $this->parser->parse('age>25');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['age' => '30']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['age' => '20']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_simple_less_than(): void
    {
        $ast = $this->parser->parse('age<25');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['age' => '20']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['age' => '30']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_simple_greater_than_or_equal(): void
    {
        $ast = $this->parser->parse('age>=25');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['age' => '25']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['age' => '20']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_simple_less_than_or_equal(): void
    {
        $ast = $this->parser->parse('age<=25');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['age' => '25']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['age' => '30']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_simple_strict_equality(): void
    {
        $ast = $this->parser->parse('age===25');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['age' => '25']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['age' => 25]);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_simple_strict_not_equal(): void
    {
        $ast = $this->parser->parse('age!==25');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['age' => 25]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['age' => '25']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_simple_spaceship(): void
    {
        $ast = $this->parser->parse('age<=>30');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['age' => '25']);
        $this->assertEquals(-1, $ast->evaluate($cluster));
    }

    // ==================== PRESENCE / ABSENCE TESTS ====================

    public function test_parse_presence(): void
    {
        $ast = $this->parser->parse('lang_fr');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['lang_fr' => 'true']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['lang_fr' => 'false']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_absence(): void
    {
        $ast = $this->parser->parse('!lang_fr');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['lang_fr' => 'false']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['lang_fr' => 'true']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    // ==================== AND / OR TESTS ====================

    public function test_parse_and(): void
    {
        $ast = $this->parser->parse('status=active & role=admin');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'active', 'role' => 'admin']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'active', 'role' => 'doctor']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_or(): void
    {
        $ast = $this->parser->parse('status=active | role=admin');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'active', 'role' => 'doctor']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'inactive', 'role' => 'guest']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_multiple_and(): void
    {
        $ast = $this->parser->parse('status=active & role=admin & verified=true');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'active', 'role' => 'admin', 'verified' => 'true']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'active', 'role' => 'admin', 'verified' => 'false']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_multiple_or(): void
    {
        $ast = $this->parser->parse('status=active | role=admin | verified=true');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'inactive', 'role' => 'guest', 'verified' => 'true']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'inactive', 'role' => 'guest', 'verified' => 'false']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    // ==================== PARENTHESES TESTS ====================

    public function test_parse_parentheses(): void
    {
        $ast = $this->parser->parse('(status=active & role=admin) | lang_fr');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'active', 'role' => 'admin', 'lang_fr' => 'false']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'inactive', 'role' => 'guest', 'lang_fr' => 'true']);
        $this->assertTrue($ast->evaluate($cluster2));

        $cluster3 = new ClusterVO(['status' => 'inactive', 'role' => 'guest', 'lang_fr' => 'false']);
        $this->assertFalse($ast->evaluate($cluster3));
    }

    public function test_parse_nested_parentheses(): void
    {
        $ast = $this->parser->parse('(status=active & (role=admin | role=doctor))');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'active', 'role' => 'admin']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'active', 'role' => 'doctor']);
        $this->assertTrue($ast->evaluate($cluster2));

        $cluster3 = new ClusterVO(['status' => 'active', 'role' => 'guest']);
        $this->assertFalse($ast->evaluate($cluster3));

        $cluster4 = new ClusterVO(['status' => 'inactive', 'role' => 'admin']);
        $this->assertFalse($ast->evaluate($cluster4));
    }

    public function test_parse_complex_parentheses(): void
    {
        $ast = $this->parser->parse('(status=active | status=pending) & (role=admin | role=doctor)');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'active', 'role' => 'admin']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'pending', 'role' => 'doctor']);
        $this->assertTrue($ast->evaluate($cluster2));

        $cluster3 = new ClusterVO(['status' => 'active', 'role' => 'guest']);
        $this->assertFalse($ast->evaluate($cluster3));

        $cluster4 = new ClusterVO(['status' => 'inactive', 'role' => 'admin']);
        $this->assertFalse($ast->evaluate($cluster4));
    }

    // ==================== NOT EQUAL TESTS ====================

    public function test_parse_not_equal(): void
    {
        $ast = $this->parser->parse('status!=active');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'inactive']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'active']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    // ==================== COMBINED TESTS ====================

    public function test_parse_combined_and_or_not(): void
    {
        $ast = $this->parser->parse('status=active & !lang_fr & (role=admin | role=doctor)');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'active', 'lang_fr' => 'false', 'role' => 'admin']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'active', 'lang_fr' => 'true', 'role' => 'admin']);
        $this->assertFalse($ast->evaluate($cluster2));

        $cluster3 = new ClusterVO(['status' => 'active', 'lang_fr' => 'false', 'role' => 'guest']);
        $this->assertFalse($ast->evaluate($cluster3));
    }

    public function test_parse_complex_expression(): void
    {
        $ast = $this->parser->parse('(status=active | status=pending) & lang_fr & !lang_en & age>=25');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'active', 'lang_fr' => 'true', 'lang_en' => 'false', 'age' => '30']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'pending', 'lang_fr' => 'true', 'lang_en' => 'false', 'age' => '25']);
        $this->assertTrue($ast->evaluate($cluster2));

        $cluster3 = new ClusterVO(['status' => 'inactive', 'lang_fr' => 'true', 'lang_en' => 'false', 'age' => '30']);
        $this->assertFalse($ast->evaluate($cluster3));

        $cluster4 = new ClusterVO(['status' => 'active', 'lang_fr' => 'true', 'lang_en' => 'true', 'age' => '30']);
        $this->assertFalse($ast->evaluate($cluster4));
    }

    // ==================== CACHE TESTS ====================

    public function test_parse_cache(): void
    {
        $query = 'status=active & role=admin';

        $ast1 = $this->parser->parse($query);
        $ast2 = $this->parser->parse($query);

        $this->assertSame($ast1, $ast2);
    }

    public function test_parse_different_queries(): void
    {
        $ast1 = $this->parser->parse('status=active');
        $ast2 = $this->parser->parse('role=admin');

        $this->assertNotSame($ast1, $ast2);
    }

    // ==================== ERROR TESTS ====================

    public function test_parse_invalid_expression(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parser->parse('status active');
    }

    public function test_parse_missing_closing_parenthesis(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parser->parse('(status=active');
    }

    public function test_parse_missing_opening_parenthesis(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parser->parse('status=active)');
    }

    public function test_parse_unexpected_tokens(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parser->parse('status=active &');
    }

    public function test_parse_invalid_operator(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parser->parse('status=>active');
    }

    public function test_parse_missing_value_after_operator(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parser->parse('status=');
    }

    // ==================== EDGE CASES TESTS ====================

    public function test_parse_with_spaces(): void
    {
        $ast1 = $this->parser->parse('status=active & role=admin');
        $ast2 = $this->parser->parse('status=active&role=admin');

        $cluster = new ClusterVO(['status' => 'active', 'role' => 'admin']);
        $this->assertEquals($ast1->evaluate($cluster), $ast2->evaluate($cluster));
    }

    public function test_parse_with_identifiers_hyphen(): void
    {
        $ast = $this->parser->parse('lang-fr=true');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['lang-fr' => 'true']);
        $this->assertTrue($ast->evaluate($cluster));
    }

    public function test_parse_empty_query(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parser->parse('');
    }

    // ==================== EXISTS / NOT_EXISTS TESTS ====================

    public function test_parse_exists_operator(): void
    {
        $ast = $this->parser->parse('*name');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['name' => 'John']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'active']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_not_exists_operator(): void
    {
        $ast = $this->parser->parse('#profile');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'active']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['profile' => 'admin']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_exists_with_and_condition(): void
    {
        $ast = $this->parser->parse('*verified & status=active');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['verified' => 'true', 'status' => 'active']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'active']);
        $this->assertFalse($ast->evaluate($cluster2));

        $cluster3 = new ClusterVO(['verified' => 'true', 'status' => 'inactive']);
        $this->assertFalse($ast->evaluate($cluster3));
    }

    public function test_parse_not_exists_with_or_condition(): void
    {
        $ast = $this->parser->parse('#lang_es | status=active');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'active']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'inactive']);
        $this->assertTrue($ast->evaluate($cluster2));

        $cluster3 = new ClusterVO(['lang_es' => 'true', 'status' => 'inactive']);
        $this->assertFalse($ast->evaluate($cluster3));
    }

    public function test_parse_complex_with_exists(): void
    {
        $ast = $this->parser->parse('(*lang_fr | #lang_en) & age>=25');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['lang_fr' => 'true', 'age' => '30']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['age' => '30']);
        $this->assertTrue($ast->evaluate($cluster2));

        $cluster3 = new ClusterVO(['lang_en' => 'true', 'age' => '20']);
        $this->assertFalse($ast->evaluate($cluster3));
    }

    public function test_parse_exists_with_not_operator(): void
    {
        $ast = $this->parser->parse('*name & !lang_fr');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['name' => 'John', 'lang_fr' => 'false']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['name' => 'John', 'lang_fr' => 'true']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    // ==================== LIKE / NOT_LIKE TESTS ====================

    public function test_parse_like_operator(): void
    {
        $ast = $this->parser->parse('name=~john');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['name' => 'john_doe']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['name' => 'jane']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_not_like_operator(): void
    {
        $ast = $this->parser->parse('name!~john');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['name' => 'jane']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['name' => 'john_doe']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_like_with_starts_pattern(): void
    {
        $ast = $this->parser->parse('name=~john%');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['name' => 'john_doe']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['name' => 'doe_john']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_like_with_ends_pattern(): void
    {
        $ast = $this->parser->parse('name=~%doe');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['name' => 'john_doe']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['name' => 'doe_john']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_like_with_contains_pattern(): void
    {
        $ast = $this->parser->parse('name=~%john%');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['name' => 'john_doe']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['name' => 'jane_doe']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    // ==================== SUBCONDITION TESTS ====================

    public function test_parse_subcondition_does_not_throw_error(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parser->parse('addresses[city=kinshasa]');
    }

    public function test_parse_subcondition_with_and_does_not_throw_error(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parser->parse('addresses[city=kinshasa & country=RDC]');
    }

    public function test_parse_subcondition_with_or_does_not_throw_error(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parser->parse('addresses[city=kinshasa | city=paris]');
    }

    public function test_parse_subcondition_with_parentheses_does_not_throw_error(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parser->parse('addresses[(city=kinshasa | city=paris)]');
    }

    public function test_parse_subcondition_with_like_does_not_throw_error(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parser->parse('addresses[city=~kin%]');
    }

    public function test_parse_subcondition_with_wildcard_path_does_not_throw_error(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parser->parse('tags[0][0]=php');
        $this->parser->parse('tags[*][0]=php');
        $this->parser->parse('tags[0][*]=php');
        $this->parser->parse('tags[*][*]=php');
    }

    public function test_parse_subcondition_with_exists_does_not_throw_error(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parser->parse('addresses[]');
    }

    public function test_parse_subcondition_with_not_exists_does_not_throw_error(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parser->parse('addresses[#profile]');
    }

    public function test_parse_subcondition_with_nested_dot_does_not_throw_error(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parser->parse('settings.notifications[email=true]');
    }

    public function test_parse_subcondition_with_complex_does_not_throw_error(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parser->parse('addresses[city=kinshasa & country=RDC & active=true]');
    }

    public function test_parse_subcondition_with_nested_path_and_condition_does_not_throw_error(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parser->parse('tags[0][1]=php & status=active');
    }

    public function test_parse_subcondition_multiple_does_not_throw_error(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parser->parse('addresses[city=kinshasa] & settings[theme=dark]');
    }

    public function test_parse_subcondition_empty_does_not_throw_error(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parser->parse('addresses[]');
    }

    // ==================== SQL FUNCTION TESTS ====================

    public function test_parse_count_function(): void
    {
        $ast = $this->parser->parse('COUNT(addresses) > 2');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO(['addresses' => ['a', 'b', 'c']]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['addresses' => ['a', 'b']]);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_count_equals(): void
    {
        $ast = $this->parser->parse('COUNT(addresses) = 2');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO(['addresses' => ['a', 'b']]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['addresses' => ['a', 'b', 'c']]);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_count_not_equals(): void
    {
        $ast = $this->parser->parse('COUNT(addresses) != 2');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO(['addresses' => ['a', 'b', 'c']]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['addresses' => ['a', 'b']]);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_count_greater_than_or_equal(): void
    {
        $ast = $this->parser->parse('COUNT(addresses) >= 2');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO(['addresses' => ['a', 'b']]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['addresses' => ['a']]);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_sum_function(): void
    {
        $ast = $this->parser->parse('SUM(prices) > 500');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO(['prices' => [100, 200, 300]]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['prices' => [50, 75]]);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_sum_function_with_numeric_string(): void
    {
        $ast = $this->parser->parse('SUM(prices) > 500');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO(['prices' => ['100', '200', '300']]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['prices' => ['50', '75']]);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_avg_function(): void
    {
        $ast = $this->parser->parse('AVG(scores) >= 85');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO(['scores' => [80, 90, 85]]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['scores' => [70, 75, 80]]);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_min_function(): void
    {
        $ast = $this->parser->parse('MIN(scores) > 75');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO(['scores' => [80, 90, 85]]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['scores' => [70, 75, 80]]);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_max_function(): void
    {
        $ast = $this->parser->parse('MAX(scores) > 90');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO(['scores' => [80, 90, 95]]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['scores' => [80, 90, 85]]);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_length_function(): void
    {
        $ast = $this->parser->parse('LENGTH(name) > 5');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO(['name' => 'John Doe']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['name' => 'John']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_function_with_nested_path(): void
    {
        $ast = $this->parser->parse('COUNT(settings.notifications) > 1');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO([
            'settings' => [
                'notifications' => [
                    ['email' => 'true'],
                    ['sms' => 'true'],
                ],
            ],
        ]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO([
            'settings' => [
                'notifications' => [
                    ['email' => 'true'],
                ],
            ],
        ]);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_function_with_condition(): void
    {
        $ast = $this->parser->parse('status=active & COUNT(addresses) > 2');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO(['status' => 'active', 'addresses' => ['a', 'b', 'c']]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'active', 'addresses' => ['a', 'b']]);
        $this->assertFalse($ast->evaluate($cluster2));

        $cluster3 = new ClusterVO(['status' => 'inactive', 'addresses' => ['a', 'b', 'c']]);
        $this->assertFalse($ast->evaluate($cluster3));
    }

    public function test_parse_multiple_functions(): void
    {
        $ast = $this->parser->parse('COUNT(addresses) > 1 & SUM(prices) > 500');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO([
            'addresses' => ['a', 'b'],
            'prices' => [100, 200, 300],
        ]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO([
            'addresses' => ['a'],
            'prices' => [100, 200, 300],
        ]);
        $this->assertFalse($ast->evaluate($cluster2));

        $cluster3 = new ClusterVO([
            'addresses' => ['a', 'b'],
            'prices' => [50, 75],
        ]);
        $this->assertFalse($ast->evaluate($cluster3));
    }

    public function test_parse_function_with_or(): void
    {
        $ast = $this->parser->parse('COUNT(addresses) > 2 | SUM(prices) > 500');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO([
            'addresses' => ['a', 'b', 'c'],
            'prices' => [50, 75],
        ]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO([
            'addresses' => ['a', 'b'],
            'prices' => [100, 200, 300],
        ]);
        $this->assertTrue($ast->evaluate($cluster2));

        $cluster3 = new ClusterVO([
            'addresses' => ['a', 'b'],
            'prices' => [50, 75],
        ]);
        $this->assertFalse($ast->evaluate($cluster3));
    }

    public function test_parse_function_with_parentheses(): void
    {
        $ast = $this->parser->parse('(COUNT(addresses) > 2 | SUM(prices) > 500) & status=active');

        $this->assertInstanceOf(GroupNode::class, $ast);

        $cluster = new ClusterVO([
            'addresses' => ['a', 'b', 'c'],
            'prices' => [50, 75],
            'status' => 'active',
        ]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO([
            'addresses' => ['a', 'b'],
            'prices' => [100, 200, 300],
            'status' => 'active',
        ]);
        $this->assertTrue($ast->evaluate($cluster2));

        $cluster3 = new ClusterVO([
            'addresses' => ['a', 'b'],
            'prices' => [50, 75],
            'status' => 'active',
        ]);
        $this->assertFalse($ast->evaluate($cluster3));

        $cluster4 = new ClusterVO([
            'addresses' => ['a', 'b', 'c'],
            'prices' => [50, 75],
            'status' => 'inactive',
        ]);
        $this->assertFalse($ast->evaluate($cluster4));
    }

    public function test_parse_function_with_unknown_function_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown function "UNKNOWN"');

        $this->parser->parse('UNKNOWN(addresses) > 2');
    }

    public function test_parse_function_with_missing_arguments(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected path argument for function');

        $this->parser->parse('COUNT() > 2');
    }

    public function test_parse_function_with_missing_closing_paren(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected closing parenthesis');

        $this->parser->parse('COUNT(addresses > 2');
    }

    public function test_parse_function_with_missing_opening_paren(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected opening parenthesis after function name');

        $this->parser->parse('COUNT addresses) > 2');
    }

    public function test_parse_function_with_missing_operator(): void
    {
        $ast = $this->parser->parse('COUNT(addresses)');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO(['addresses' => ['a', 'b']]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['addresses' => []]);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_function_with_empty_array(): void
    {
        $ast = $this->parser->parse('COUNT(addresses) > 0');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO(['addresses' => []]);
        $this->assertFalse($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['addresses' => ['a']]);
        $this->assertTrue($ast->evaluate($cluster2));
    }

    public function test_parse_json_length_function(): void
    {
        $ast = $this->parser->parse('JSON_LENGTH(addresses) > 2');

        $this->assertInstanceOf(FunctionNode::class, $ast);

        $cluster = new ClusterVO(['addresses' => ['a', 'b', 'c']]);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['addresses' => ['a', 'b']]);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_function_with_spaces(): void
    {
        $ast1 = $this->parser->parse('COUNT(addresses) > 2');
        $ast2 = $this->parser->parse('COUNT( addresses ) > 2');

        $cluster = new ClusterVO(['addresses' => ['a', 'b', 'c']]);
        $this->assertEquals($ast1->evaluate($cluster), $ast2->evaluate($cluster));
    }
}
