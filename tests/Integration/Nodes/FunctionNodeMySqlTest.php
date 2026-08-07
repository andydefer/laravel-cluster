<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\Nodes;

use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\FunctionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\MySqlTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class FunctionNodeMySqlTest extends MySqlTestCase
{
    use RefreshDatabase;

    private SqlFunctionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = app(SqlFunctionRegistry::class);

        TestCluster::truncate();

        TestCluster::create([
            'clusters' => [
                'name' => 'John Doe',
                'age' => 30,
                'status' => 'active',
                'addresses' => [
                    ['city' => 'Kinshasa', 'country' => 'RDC'],
                    ['city' => 'Paris', 'country' => 'France'],
                ],
                'scores' => [80, 90, 85],
                'prices' => [100, 200, 300],
                'tags' => ['php', 'js', 'docker'],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Jane Smith',
                'age' => 25,
                'status' => 'inactive',
                'addresses' => [
                    ['city' => 'Paris', 'country' => 'France'],
                ],
                'scores' => [70, 75, 80],
                'prices' => [50, 75],
                'tags' => ['python', 'react'],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Bob Johnson',
                'age' => 35,
                'status' => 'active',
                'addresses' => [
                    ['city' => 'Kinshasa', 'country' => 'RDC'],
                    ['city' => 'Paris', 'country' => 'France'],
                    ['city' => 'London', 'country' => 'UK'],
                ],
                'scores' => [95, 98, 92],
                'prices' => [500, 600, 700],
                'tags' => ['php', 'laravel', 'vuejs'],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Alice Wonder',
                'age' => 28,
                'status' => 'pending',
                'addresses' => [],
                'scores' => [60, 65, 70],
                'prices' => [],
                'tags' => ['go', 'rust'],
            ],
        ]);
    }

    // ============================================================
    // EVALUATE TESTS
    // ============================================================

    public function test_evaluate_count_greater_than(): void
    {
        $node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');

        $cluster = new ClusterVO([
            'addresses' => ['a', 'b', 'c'],
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_count_greater_than_false(): void
    {
        $node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');

        $cluster = new ClusterVO([
            'addresses' => ['a', 'b'],
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_count_equals(): void
    {
        $node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::EQUAL, '2');

        $cluster = new ClusterVO([
            'addresses' => ['a', 'b'],
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_count_equals_false(): void
    {
        $node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::EQUAL, '2');

        $cluster = new ClusterVO([
            'addresses' => ['a', 'b', 'c'],
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_sum_greater_than(): void
    {
        $node = new FunctionNode('SUM', 'prices', ComparisonOperator::GREATER_THAN, '500');

        $cluster = new ClusterVO([
            'prices' => [100, 200, 300],
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_sum_greater_than_false(): void
    {
        $node = new FunctionNode('SUM', 'prices', ComparisonOperator::GREATER_THAN, '500');

        $cluster = new ClusterVO([
            'prices' => [50, 75],
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_avg_greater_than_or_equal(): void
    {
        $node = new FunctionNode('AVG', 'scores', ComparisonOperator::GREATER_THAN_OR_EQUAL, '85');

        $cluster = new ClusterVO([
            'scores' => [80, 90, 85],
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_avg_greater_than_or_equal_false(): void
    {
        $node = new FunctionNode('AVG', 'scores', ComparisonOperator::GREATER_THAN_OR_EQUAL, '85');

        $cluster = new ClusterVO([
            'scores' => [70, 75, 80],
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_min_less_than(): void
    {
        $node = new FunctionNode('MIN', 'scores', ComparisonOperator::LESS_THAN, '75');

        $cluster = new ClusterVO([
            'scores' => [80, 90, 70, 85],
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_max_greater_than(): void
    {
        $node = new FunctionNode('MAX', 'scores', ComparisonOperator::GREATER_THAN, '95');

        $cluster = new ClusterVO([
            'scores' => [80, 90, 98, 85],
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_length_greater_than(): void
    {
        $node = new FunctionNode('LENGTH', 'name', ComparisonOperator::GREATER_THAN, '5');

        $cluster = new ClusterVO([
            'name' => 'John Doe',
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_length_greater_than_false(): void
    {
        $node = new FunctionNode('LENGTH', 'name', ComparisonOperator::GREATER_THAN, '10');

        $cluster = new ClusterVO([
            'name' => 'John',
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_with_nested_path(): void
    {
        $node = new FunctionNode('COUNT', 'settings.notifications', ComparisonOperator::GREATER_THAN, '1');

        $cluster = new ClusterVO([
            'settings' => [
                'notifications' => [
                    ['email' => 'yes'],
                    ['sms' => 'yes'],
                ],
            ],
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_with_null_value(): void
    {
        $node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::EQUAL, null);

        $cluster = new ClusterVO([
            'addresses' => null,
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_with_missing_path(): void
    {
        $node = new FunctionNode('COUNT', 'non_existent', ComparisonOperator::GREATER_THAN, '0');

        $cluster = new ClusterVO([
            'name' => 'John',
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_unknown_function(): void
    {
        $node = new FunctionNode('UNKNOWN', 'addresses', ComparisonOperator::GREATER_THAN, '0');

        $cluster = new ClusterVO([
            'addresses' => ['a', 'b'],
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_with_empty_array(): void
    {
        $node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '0');

        $cluster = new ClusterVO([
            'addresses' => [],
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_with_non_array_value(): void
    {
        $node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '0');

        $cluster = new ClusterVO([
            'addresses' => 'not_an_array',
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    // ============================================================
    // TO SQL TESTS - MySQL
    // ============================================================

    public function test_mysql_to_sql_count(): void
    {
        $node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');

        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "JSON_LENGTH(clusters, '$.addresses') > 2";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_sum(): void
    {
        $node = new FunctionNode('SUM', 'prices', ComparisonOperator::GREATER_THAN, '500');

        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "(SELECT SUM(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(clusters, '$.\"prices\"[*]' COLUMNS(value JSON PATH '$')) AS jt) > 500";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_avg(): void
    {
        $node = new FunctionNode('AVG', 'scores', ComparisonOperator::GREATER_THAN_OR_EQUAL, '85');

        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "(SELECT AVG(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(clusters, '$.\"scores\"[*]' COLUMNS(value JSON PATH '$')) AS jt) >= 85";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_length(): void
    {
        $node = new FunctionNode('LENGTH', 'name', ComparisonOperator::GREATER_THAN, '5');

        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LENGTH(JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.name'))) > 5";
        $this->assertEquals($expected, $sql);
    }
    // ============================================================
    // TO ELOQUENT TESTS - MySQL
    // ============================================================

    public function test_mysql_to_eloquent_count(): void
    {
        $node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');

        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results->first()->clusters['name']);
    }

    public function test_mysql_to_eloquent_sum(): void
    {
        $node = new FunctionNode('SUM', 'prices', ComparisonOperator::GREATER_THAN, '500');

        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
        $names = $results->pluck('clusters')->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_mysql_to_eloquent_avg(): void
    {
        $node = new FunctionNode('AVG', 'scores', ComparisonOperator::GREATER_THAN_OR_EQUAL, '85');

        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
        $names = $results->pluck('clusters')->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_mysql_to_eloquent_length(): void
    {
        $node = new FunctionNode('LENGTH', 'name', ComparisonOperator::GREATER_THAN, '5');

        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(4, $results);
    }

    public function test_mysql_to_eloquent_count_equals(): void
    {
        $node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::EQUAL, '2');

        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->clusters['name']);
    }

    public function test_mysql_to_eloquent_combined_with_condition(): void
    {
        $statusNode = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $countNode = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');

        $query = TestCluster::query();
        $statusNode->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
        $countNode->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results->first()->clusters['name']);
    }

    public function test_mysql_to_eloquent_with_group_node(): void
    {
        $countNode = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '1');
        $statusNode = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');

        $groupNode = new GroupNode(LogicalOperator::AND, $countNode, $statusNode);

        $query = TestCluster::query();
        $groupNode->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
        $names = $results->pluck('clusters')->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_mysql_get_children_returns_empty_array(): void
    {
        $node = new FunctionNode('COUNT', 'addresses', ComparisonOperator::GREATER_THAN, '2');

        $children = $node->getChildren();

        $this->assertIsArray($children);
        $this->assertEmpty($children);
    }
}
