<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\Nodes;

use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\MySqlTestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class ConditionNodeMySqlTest extends MySqlTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    private function createTestData(): void
    {
        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'admin',
                'age' => 25,
                'lang_fr' => 'yes',
                'lang_en' => 'no',
                'verified' => 'yes',
                'score' => 85.5,
                'name' => 'john_doe',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'inactive',
                'role' => 'doctor',
                'age' => 30,
                'lang_fr' => 'no',
                'lang_en' => 'yes',
                'verified' => 'no',
                'score' => 92.0,
                'name' => 'jane_smith',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'doctor',
                'age' => 35,
                'lang_fr' => 'yes',
                'lang_en' => 'no',
                'verified' => 'yes',
                'score' => 78.0,
                'name' => 'bob_johnson',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'pending',
                'role' => 'guest',
                'age' => 18,
                'lang_fr' => 'no',
                'lang_en' => 'yes',
                'verified' => 'no',
                'score' => 30.5,
                'name' => 'alice_johanson',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'admin',
                'age' => 40,
                'lang_fr' => 'yes',
                'lang_en' => 'no',
                'verified' => 'yes',
                'score' => 95.0,
                'name' => 'charlie_doe',
            ],
        ]);
    }

    // ============================================================
    // TO SQL TESTS - MySQL
    // ============================================================

    public function test_mysql_to_sql_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.\"status\"')) = 'active'";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_not_equal(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::NOT_EQUAL, 'inactive');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.\"status\"')) != 'inactive'";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '25');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "CAST(JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.\"age\"')) AS DECIMAL(10,2)) > 25";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_greater_than_or_equal(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN_OR_EQUAL, '30');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "CAST(JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.\"age\"')) AS DECIMAL(10,2)) >= 30";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_less_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::LESS_THAN, '30');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "CAST(JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.\"age\"')) AS DECIMAL(10,2)) < 30";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_less_than_or_equal(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::LESS_THAN_OR_EQUAL, '25');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "CAST(JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.\"age\"')) AS DECIMAL(10,2)) <= 25";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_boolean_yes(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EQUAL, 'yes');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.\"lang_fr\"')) = 'yes'";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_boolean_no(): void
    {
        $node = new ConditionNode('lang_en', ComparisonOperator::EQUAL, 'no');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.\"lang_en\"')) = 'no'";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_exists(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EXISTS);
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "JSON_EXTRACT(clusters, '$.\"lang_fr\"') IS NOT NULL";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_not_exists(): void
    {
        $node = new ConditionNode('lang_es', ComparisonOperator::NOT_EXISTS);
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "JSON_EXTRACT(clusters, '$.\"lang_es\"') IS NULL";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_like_contains(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.\"name\"'))) LIKE LOWER('%john%')";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_like_pattern_starts(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john%');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.\"name\"'))) LIKE LOWER('john%')";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_like_pattern_ends(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%doe');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.\"name\"'))) LIKE LOWER('%doe')";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_not_like(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::NOT_LIKE, 'john');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.\"name\"'))) NOT LIKE LOWER('%john%')";
        $this->assertEquals($expected, $sql);
    }

    public function test_mysql_to_sql_like_multiple_patterns(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%j%h%n');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.\"name\"'))) LIKE LOWER('%j%h%n')";
        $this->assertEquals($expected, $sql);
    }

    // ============================================================
    // TO ELOQUENT TESTS - MySQL
    // ============================================================

    public function test_mysql_to_eloquent_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $sql = $query->toSql();

        // ✅ Le code génère JSON_UNQUOTE(...) = ?
        $this->assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(clusters', $sql);
        $this->assertStringContainsString('= ?', $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_mysql_to_eloquent_not_equal(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::NOT_EQUAL, 'inactive');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(4, $results);
    }

    public function test_mysql_to_eloquent_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '25');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_mysql_to_eloquent_greater_than_or_equal(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN_OR_EQUAL, '30');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_mysql_to_eloquent_less_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::LESS_THAN, '30');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_mysql_to_eloquent_less_than_or_equal(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::LESS_THAN_OR_EQUAL, '25');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_mysql_to_eloquent_boolean_yes(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EQUAL, 'yes');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_mysql_to_eloquent_boolean_no(): void
    {
        $node = new ConditionNode('lang_en', ComparisonOperator::EQUAL, 'no');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_mysql_to_eloquent_exists(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EXISTS);
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString('IS NOT NULL', $sql);
        $this->assertStringContainsString('lang_fr', $sql);

        $results = $query->get();
        $this->assertCount(5, $results);
    }

    public function test_mysql_to_eloquent_not_exists(): void
    {
        $node = new ConditionNode('lang_es', ComparisonOperator::NOT_EXISTS);
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString('IS NULL', $sql);
        $this->assertStringContainsString('lang_es', $sql);

        $results = $query->get();
        $this->assertCount(5, $results);
    }

    public function test_mysql_to_eloquent_like(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john%');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(1, $results);
    }

    public function test_mysql_to_eloquent_not_like(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::NOT_LIKE, 'john%');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(4, $results);
    }

    public function test_mysql_to_eloquent_like_contains_multiple_patterns(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%j%h%n');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_mysql_to_eloquent_like_multiple_patterns_strict(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%j%n%h');
        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertEquals('jane_smith', $results->first()->clusters['name']);
    }

    public function test_mysql_to_eloquent_multiple_conditions(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $query = TestCluster::query();
        $node1->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
        $node2->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_mysql_to_eloquent_with_or_condition(): void
    {
        $query = TestCluster::query();

        $query->where(function (Builder $q) {
            $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
            $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'doctor');

            $node1->toEloquent($q, 'clusters', DatabaseDriver::MYSQL);
            $q->orWhere(function (Builder $sub) use ($node2) {
                $node2->toEloquent($sub, 'clusters', DatabaseDriver::MYSQL);
            });
        });

        $results = $query->get();
        $this->assertCount(4, $results);
    }

    public function test_mysql_to_eloquent_with_numeric_comparison(): void
    {
        $node = new ConditionNode('score', ComparisonOperator::GREATER_THAN, '80');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_mysql_to_eloquent_with_null_value(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, null);
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(0, $results);
    }

    public function test_mysql_to_eloquent_default_driver(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(clusters', $sql);
    }

    public function test_mysql_get_json_path(): void
    {
        $node = new ConditionNode('test_key', ComparisonOperator::EQUAL, 'value');
        $reflection = new \ReflectionClass($node);
        $method = $reflection->getMethod('getJsonPath');

        $result = $method->invoke($node);
        $this->assertEquals('$."test_key"', $result);
    }
}
