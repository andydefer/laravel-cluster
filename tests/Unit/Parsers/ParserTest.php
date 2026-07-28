<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Parser;

use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Parser;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use PHPUnit\Framework\TestCase;

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
        // "lang_fr" est converti en "lang_fr=true"
        $ast = $this->parser->parse('lang_fr');

        $this->assertInstanceOf(ConditionNode::class, $ast);

        $cluster = new ClusterVO(['lang_fr' => 'true']);
        $this->assertTrue($ast->evaluate($cluster));

        $cluster2 = new ClusterVO(['lang_fr' => 'false']);
        $this->assertFalse($ast->evaluate($cluster2));
    }

    public function test_parse_absence(): void
    {
        // "!lang_fr" est converti en "lang_fr=false"
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
        // "status!=active" est un ConditionNode avec NOT_EQUAL
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
        // "!lang_fr" est converti en "lang_fr=false"
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
        // "lang_fr" et "!lang_en" sont convertis en "lang_fr=true" et "lang_en=false"
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
}
