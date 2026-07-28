<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\Nodes;

use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

final class ConditionNodeTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'admin',
                'age' => 25,
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => 85.5,
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'inactive',
                'role' => 'doctor',
                'age' => 30,
                'lang_fr' => 'false',
                'lang_en' => 'true',
                'verified' => 'false',
                'score' => 92.0,
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'doctor',
                'age' => 35,
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => 78.0,
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'pending',
                'role' => 'guest',
                'age' => 18,
                'lang_fr' => 'false',
                'lang_en' => 'true',
                'verified' => 'false',
                'score' => 30.5,
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'admin',
                'age' => 40,
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => 95.0,
            ],
        ]);
    }

    // ==================== EVALUATE TESTS ====================

    public function test_evaluate_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $cluster = new ClusterVO(['status' => 'active']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_equals_false(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $cluster = new ClusterVO(['status' => 'inactive']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_equals_loose(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::EQUAL_LOOSE, '25');
        $cluster = new ClusterVO(['age' => '25']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_equals_strict(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::EQUAL_STRICT, '25');
        $cluster = new ClusterVO(['age' => '25']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_equals_strict_false(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::EQUAL_STRICT, '25');
        $cluster = new ClusterVO(['age' => 25]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_not_equal(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::NOT_EQUAL, 'inactive');
        $cluster = new ClusterVO(['status' => 'active']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_not_equal_false(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::NOT_EQUAL, 'inactive');
        $cluster = new ClusterVO(['status' => 'inactive']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_less_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::LESS_THAN, '30');
        $cluster = new ClusterVO(['age' => '25']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_less_than_false(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::LESS_THAN, '30');
        $cluster = new ClusterVO(['age' => '35']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_less_than_or_equal(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::LESS_THAN_OR_EQUAL, '30');
        $cluster = new ClusterVO(['age' => '30']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '30');
        $cluster = new ClusterVO(['age' => '35']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_greater_than_or_equal(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN_OR_EQUAL, '30');
        $cluster = new ClusterVO(['age' => '30']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_spaceship(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::SPACESHIP, '30');
        $cluster = new ClusterVO(['age' => '25']);

        $result = $node->evaluate($cluster);
        $this->assertEquals(-1, $result);
    }

    public function test_evaluate_presence_true(): void
    {
        // Presence avec valeur string 'true'
        $node = new ConditionNode('lang_fr', ComparisonOperator::EQUAL, 'true');
        $cluster = new ClusterVO(['lang_fr' => 'true']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_presence_false(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EQUAL, 'true');
        $cluster = new ClusterVO(['lang_fr' => 'false']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_absence(): void
    {
        // Absence avec valeur string 'false'
        $node = new ConditionNode('lang_en', ComparisonOperator::EQUAL, 'false');

        $cluster1 = new ClusterVO(['lang_fr' => 'true']);
        $this->assertTrue($node->evaluate($cluster1));

        $cluster2 = new ClusterVO(['lang_fr' => 'true', 'lang_en' => 'false']);
        $this->assertTrue($node->evaluate($cluster2));

        $cluster3 = new ClusterVO(['lang_fr' => 'true', 'lang_en' => 'true']);
        $this->assertFalse($node->evaluate($cluster3));
    }

    public function test_evaluate_key_not_exists(): void
    {
        $node = new ConditionNode('non_existent', ComparisonOperator::EQUAL, 'value');
        $cluster = new ClusterVO(['status' => 'active']);

        $this->assertFalse($node->evaluate($cluster));
    }

    // ==================== TO SQL TESTS ====================

    public function test_to_sql_mysql_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "JSON_EXTRACT(clusters, '$.\"status\"') = 'active'";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_not_equal(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::NOT_EQUAL, 'inactive');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "JSON_EXTRACT(clusters, '$.\"status\"') != 'inactive'";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '25');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "CAST(JSON_EXTRACT(clusters, '$.\"age\"') AS DECIMAL(10,2)) > 25";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_boolean_true(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EQUAL, 'true');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "JSON_EXTRACT(clusters, '$.\"lang_fr\"') = 'true'";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_boolean_false(): void
    {
        $node = new ConditionNode('lang_en', ComparisonOperator::EQUAL, 'false');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "JSON_EXTRACT(clusters, '$.\"lang_en\"') = 'false'";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_postgres_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $sql = $node->toSql('clusters', DatabaseDriver::PGSQL);

        $expected = "clusters->>'status' = 'active'";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_postgres_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '25');
        $sql = $node->toSql('clusters', DatabaseDriver::PGSQL);

        $this->assertStringContainsString("(clusters->>'age')::numeric", $sql);
        $this->assertStringContainsString('> 25', $sql);
    }

    public function test_to_sql_sqlite_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "json_extract(clusters, '$.status') = 'active'";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '25');
        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "CAST(json_extract(clusters, '$.age') AS INTEGER) > 25";
        $this->assertEquals($expected, $sql);
    }

    // ==================== TO ELOQUENT TESTS ====================

    public function test_to_eloquent_mysql_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString('JSON_EXTRACT(clusters', $sql);
        $this->assertStringContainsString('= ?', $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_mysql_not_equal(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::NOT_EQUAL, 'inactive');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(4, $results);
    }

    public function test_to_eloquent_mysql_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '25');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_mysql_boolean_true(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EQUAL, 'true');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        // lang_fr = 'true' : ID 1, 3, 5
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_mysql_boolean_false(): void
    {
        $node = new ConditionNode('lang_en', ComparisonOperator::EQUAL, 'false');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        // lang_en = 'false' : ID 1, 3, 5
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_postgres_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::PGSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString("clusters->>'status'", $sql);
        $this->assertStringContainsString('= ?', $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_sqlite_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $sql = $query->toSql();
        $this->assertStringContainsString("json_extract(clusters, '$.status')", $sql);
        $this->assertStringContainsString('= ?', $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_sqlite_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '25');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $sql = $query->toSql();
        $this->assertStringContainsString("json_extract(clusters, '$.age')", $sql);
        $this->assertStringContainsString('> ?', $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    // ==================== COMPLEX CONDITIONS TESTS ====================

    public function test_to_eloquent_multiple_conditions(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $query = TestCluster::query();
        $node1->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
        $node2->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_to_eloquent_with_or_condition(): void
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

    public function test_to_eloquent_with_numeric_comparison(): void
    {
        $node = new ConditionNode('score', ComparisonOperator::GREATER_THAN, '80');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        // score > 80 : id 1 (85.5), id 2 (92.0), id 5 (95.0)
        $this->assertCount(3, $results);
    }

    // ==================== EDGE CASES TESTS ====================

    public function test_to_eloquent_with_null_value(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, null);
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(0, $results);
    }

    public function test_to_eloquent_default_driver(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString('JSON_EXTRACT', $sql);
    }

    public function test_get_json_path(): void
    {
        $node = new ConditionNode('test.key', ComparisonOperator::EQUAL, 'value');
        $reflection = new \ReflectionClass($node);
        $method = $reflection->getMethod('getJsonPath');
        $method->setAccessible(true);

        $result = $method->invoke($node);
        $this->assertEquals('$."test.key"', $result);
    }
}
