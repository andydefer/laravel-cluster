<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\Nodes;

use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

final class GroupNodeTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestCluster::create([
            'clusters' => [
                'id' => 1,
                'status' => 'active',
                'role' => 'admin',
                'age' => '25',
                'lang_fr' => 'yes',
                'lang_en' => 'no',
                'verified' => 'yes',
                'score' => '85.5',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'id' => 2,
                'status' => 'inactive',
                'role' => 'doctor',
                'age' => '30',
                'lang_fr' => 'no',
                'lang_en' => 'yes',
                'verified' => 'no',
                'score' => '92.0',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'id' => 3,
                'status' => 'active',
                'role' => 'doctor',
                'age' => '35',
                'lang_fr' => 'yes',
                'lang_en' => 'no',
                'verified' => 'yes',
                'score' => '78.0',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'id' => 4,
                'status' => 'pending',
                'role' => 'guest',
                'age' => '18',
                'lang_fr' => 'no',
                'lang_en' => 'yes',
                'verified' => 'no',
                'score' => '30.5',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'id' => 5,
                'status' => 'active',
                'role' => 'admin',
                'age' => '40',
                'lang_fr' => 'yes',
                'lang_en' => 'no',
                'verified' => 'yes',
                'score' => '95.0',
            ],
        ]);
    }

    public function test_evaluate_and(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $group = new GroupNode(LogicalOperator::AND, $node1, $node2);

        $cluster1 = new ClusterVO(['status' => 'active', 'role' => 'admin']);
        $this->assertTrue($group->evaluate($cluster1));

        $cluster2 = new ClusterVO(['status' => 'active', 'role' => 'doctor']);
        $this->assertFalse($group->evaluate($cluster2));

        $cluster3 = new ClusterVO(['status' => 'inactive', 'role' => 'admin']);
        $this->assertFalse($group->evaluate($cluster3));
    }

    public function test_evaluate_or(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $group = new GroupNode(LogicalOperator::OR, $node1, $node2);

        $cluster1 = new ClusterVO(['status' => 'active', 'role' => 'admin']);
        $this->assertTrue($group->evaluate($cluster1));

        $cluster2 = new ClusterVO(['status' => 'active', 'role' => 'doctor']);
        $this->assertTrue($group->evaluate($cluster2));

        $cluster3 = new ClusterVO(['status' => 'inactive', 'role' => 'admin']);
        $this->assertTrue($group->evaluate($cluster3));

        $cluster4 = new ClusterVO(['status' => 'inactive', 'role' => 'guest']);
        $this->assertFalse($group->evaluate($cluster4));
    }

    public function test_evaluate_multiple_children_and(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');
        $node3 = new ConditionNode('verified', ComparisonOperator::EQUAL, 'yes');

        $group = new GroupNode(LogicalOperator::AND, $node1, $node2, $node3);

        $cluster1 = new ClusterVO(['status' => 'active', 'role' => 'admin', 'verified' => 'yes']);
        $this->assertTrue($group->evaluate($cluster1));

        $cluster2 = new ClusterVO(['status' => 'active', 'role' => 'admin', 'verified' => 'no']);
        $this->assertFalse($group->evaluate($cluster2));
    }

    public function test_evaluate_multiple_children_or(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');
        $node3 = new ConditionNode('verified', ComparisonOperator::EQUAL, 'yes');

        $group = new GroupNode(LogicalOperator::OR, $node1, $node2, $node3);

        $cluster1 = new ClusterVO(['status' => 'active', 'role' => 'guest', 'verified' => 'no']);
        $this->assertTrue($group->evaluate($cluster1));

        $cluster2 = new ClusterVO(['status' => 'inactive', 'role' => 'admin', 'verified' => 'no']);
        $this->assertTrue($group->evaluate($cluster2));

        $cluster3 = new ClusterVO(['status' => 'inactive', 'role' => 'guest', 'verified' => 'yes']);
        $this->assertTrue($group->evaluate($cluster3));

        $cluster4 = new ClusterVO(['status' => 'inactive', 'role' => 'guest', 'verified' => 'no']);
        $this->assertFalse($group->evaluate($cluster4));
    }

    public function test_evaluate_empty_children(): void
    {
        $group = new GroupNode(LogicalOperator::AND);
        $cluster = new ClusterVO(['status' => 'active']);

        $this->assertTrue($group->evaluate($cluster));
    }

    public function test_evaluate_nested_groups_and(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $innerGroup = new GroupNode(LogicalOperator::OR, $node1, $node2);

        $node3 = new ConditionNode('verified', ComparisonOperator::EQUAL, 'yes');
        $outerGroup = new GroupNode(LogicalOperator::AND, $innerGroup, $node3);

        $cluster1 = new ClusterVO(['status' => 'active', 'role' => 'guest', 'verified' => 'yes']);
        $this->assertTrue($outerGroup->evaluate($cluster1));

        $cluster2 = new ClusterVO(['status' => 'inactive', 'role' => 'admin', 'verified' => 'yes']);
        $this->assertTrue($outerGroup->evaluate($cluster2));

        $cluster3 = new ClusterVO(['status' => 'inactive', 'role' => 'guest', 'verified' => 'yes']);
        $this->assertFalse($outerGroup->evaluate($cluster3));

        $cluster4 = new ClusterVO(['status' => 'active', 'role' => 'admin', 'verified' => 'no']);
        $this->assertFalse($outerGroup->evaluate($cluster4));
    }

    public function test_to_sql_and(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $group = new GroupNode(LogicalOperator::AND, $node1, $node2);

        $sql = $group->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "(LOWER(JSON_EXTRACT(clusters, '$.\"status\"')) = LOWER('active') AND LOWER(JSON_EXTRACT(clusters, '$.\"role\"')) = LOWER('admin'))";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_or(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $group = new GroupNode(LogicalOperator::OR, $node1, $node2);

        $sql = $group->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "(LOWER(JSON_EXTRACT(clusters, '$.\"status\"')) = LOWER('active') OR LOWER(JSON_EXTRACT(clusters, '$.\"role\"')) = LOWER('admin'))";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_multiple_children(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');
        $node3 = new ConditionNode('verified', ComparisonOperator::EQUAL, 'yes');

        $group = new GroupNode(LogicalOperator::AND, $node1, $node2, $node3);

        $sql = $group->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "(LOWER(JSON_EXTRACT(clusters, '$.\"status\"')) = LOWER('active') AND LOWER(JSON_EXTRACT(clusters, '$.\"role\"')) = LOWER('admin') AND LOWER(JSON_EXTRACT(clusters, '$.\"verified\"')) = LOWER('yes'))";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_single_child(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');

        $group = new GroupNode(LogicalOperator::AND, $node);

        $sql = $group->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_EXTRACT(clusters, '$.\"status\"')) = LOWER('active')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_nested_groups(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $innerGroup = new GroupNode(LogicalOperator::OR, $node1, $node2);

        $node3 = new ConditionNode('verified', ComparisonOperator::EQUAL, 'yes');
        $outerGroup = new GroupNode(LogicalOperator::AND, $innerGroup, $node3);

        $sql = $outerGroup->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "((LOWER(JSON_EXTRACT(clusters, '$.\"status\"')) = LOWER('active') OR LOWER(JSON_EXTRACT(clusters, '$.\"role\"')) = LOWER('admin')) AND LOWER(JSON_EXTRACT(clusters, '$.\"verified\"')) = LOWER('yes'))";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_postgres(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $group = new GroupNode(LogicalOperator::AND, $node1, $node2);

        $sql = $group->toSql('clusters', DatabaseDriver::PGSQL);

        $expected = "(LOWER(clusters->>'status') = LOWER('active') AND LOWER(clusters->>'role') = LOWER('admin'))";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $group = new GroupNode(LogicalOperator::AND, $node1, $node2);

        $sql = $group->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "(LOWER(json_extract(clusters, '$.status')) = LOWER('active') AND LOWER(json_extract(clusters, '$.role')) = LOWER('admin'))";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_eloquent_and(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $group = new GroupNode(LogicalOperator::AND, $node1, $node2);

        $query = TestCluster::query();
        $group->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString(' and ', strtolower($sql));

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_to_eloquent_or(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $group = new GroupNode(LogicalOperator::OR, $node1, $node2);

        $query = TestCluster::query();
        $group->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString(' or ', strtolower($sql));

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_multiple_children(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');
        $node3 = new ConditionNode('verified', ComparisonOperator::EQUAL, 'yes');

        $group = new GroupNode(LogicalOperator::AND, $node1, $node2, $node3);

        $query = TestCluster::query();
        $group->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_to_eloquent_nested_groups(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $innerGroup = new GroupNode(LogicalOperator::OR, $node1, $node2);

        $node3 = new ConditionNode('verified', ComparisonOperator::EQUAL, 'yes');
        $outerGroup = new GroupNode(LogicalOperator::AND, $innerGroup, $node3);

        $query = TestCluster::query();
        $outerGroup->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_with_or_where_nested(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'doctor');

        $group = new GroupNode(LogicalOperator::OR, $node1, $node2);

        $query = TestCluster::query();
        $group->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(4, $results);
    }

    public function test_get_children(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $group = new GroupNode(LogicalOperator::AND, $node1, $node2);

        $children = $group->getChildren();

        $this->assertCount(2, $children);
        $this->assertSame($node1, $children[0]);
        $this->assertSame($node2, $children[1]);
    }

    public function test_get_children_empty(): void
    {
        $group = new GroupNode(LogicalOperator::AND);

        $children = $group->getChildren();

        $this->assertCount(0, $children);
        $this->assertEmpty($children);
    }
}
