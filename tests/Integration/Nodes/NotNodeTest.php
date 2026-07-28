<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\Nodes;

use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\NotNode;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

final class NotNodeTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestCluster::create([
            'clusters' => [
                'id' => 1,
                'status' => 'active',
                'role' => 'admin',
                'age' => 25,
                'lang_fr' => true,
                'lang_en' => false,
                'verified' => true,
                'score' => 85.5,
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'id' => 2,
                'status' => 'inactive',
                'role' => 'doctor',
                'age' => 30,
                'lang_fr' => false,
                'lang_en' => true,
                'verified' => false,
                'score' => 92.0,
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'id' => 3,
                'status' => 'active',
                'role' => 'doctor',
                'age' => 35,
                'lang_fr' => true,
                'lang_en' => false,
                'verified' => true,
                'score' => 78.0,
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'id' => 4,
                'status' => 'pending',
                'role' => 'guest',
                'age' => 18,
                'lang_fr' => false,
                'lang_en' => true,
                'verified' => false,
                'score' => 30.5,
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'id' => 5,
                'status' => 'active',
                'role' => 'admin',
                'age' => 40,
                'lang_fr' => true,
                'lang_en' => false,
                'verified' => true,
                'score' => 95.0,
            ],
        ]);
    }

    // ==================== EVALUATE TESTS ====================

    public function test_evaluate_not(): void
    {
        $condition = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $notNode = new NotNode($condition);

        $cluster1 = new ClusterVO(['status' => 'inactive']);
        $this->assertTrue($notNode->evaluate($cluster1));

        $cluster2 = new ClusterVO(['status' => 'active']);
        $this->assertFalse($notNode->evaluate($cluster2));
    }

    public function test_evaluate_not_with_condition(): void
    {
        $condition = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');
        $notNode = new NotNode($condition);

        $cluster1 = new ClusterVO(['role' => 'doctor']);
        $this->assertTrue($notNode->evaluate($cluster1));

        $cluster2 = new ClusterVO(['role' => 'admin']);
        $this->assertFalse($notNode->evaluate($cluster2));
    }

    public function test_evaluate_not_with_presence(): void
    {
        $condition = new ConditionNode('lang_fr', ComparisonOperator::PRESENCE);
        $notNode = new NotNode($condition);

        $cluster1 = new ClusterVO(['lang_fr' => false]);
        $this->assertTrue($notNode->evaluate($cluster1));

        $cluster2 = new ClusterVO(['lang_fr' => true]);
        $this->assertFalse($notNode->evaluate($cluster2));
    }

    public function test_evaluate_not_with_absence(): void
    {
        $condition = new ConditionNode('lang_en', ComparisonOperator::ABSENCE);
        $notNode = new NotNode($condition);

        $cluster1 = new ClusterVO(['lang_en' => true]);
        $this->assertTrue($notNode->evaluate($cluster1));

        $cluster2 = new ClusterVO(['lang_en' => false]);
        $this->assertFalse($notNode->evaluate($cluster2));
    }

    public function test_evaluate_not_with_complex_condition(): void
    {
        $condition = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '30');
        $notNode = new NotNode($condition);

        $cluster1 = new ClusterVO(['age' => 25]);
        $this->assertTrue($notNode->evaluate($cluster1));

        $cluster2 = new ClusterVO(['age' => 35]);
        $this->assertFalse($notNode->evaluate($cluster2));
    }

    public function test_evaluate_double_not(): void
    {
        $condition = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $notNode1 = new NotNode($condition);
        $notNode2 = new NotNode($notNode1);

        $cluster = new ClusterVO(['status' => 'active']);
        $this->assertTrue($notNode2->evaluate($cluster));

        $cluster2 = new ClusterVO(['status' => 'inactive']);
        $this->assertFalse($notNode2->evaluate($cluster2));
    }

    // ==================== TO SQL TESTS ====================

    public function test_to_sql_mysql_not(): void
    {
        $condition = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $notNode = new NotNode($condition);

        $sql = $notNode->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "NOT (JSON_EXTRACT(clusters, '$.\"status\"') = 'active')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_not_with_presence(): void
    {
        $condition = new ConditionNode('lang_fr', ComparisonOperator::PRESENCE);
        $notNode = new NotNode($condition);

        $sql = $notNode->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "NOT (JSON_EXTRACT(clusters, '$.\"lang_fr\"') IS NOT NULL)";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_not_with_complex_condition(): void
    {
        $condition = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '25');
        $notNode = new NotNode($condition);

        $sql = $notNode->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "NOT (CAST(JSON_EXTRACT(clusters, '$.\"age\"') AS DECIMAL(10,2)) > 25)";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_postgres_not(): void
    {
        $condition = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $notNode = new NotNode($condition);

        $sql = $notNode->toSql('clusters', DatabaseDriver::PGSQL);

        $expected = "NOT (clusters->>'status' = 'active')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_postgres_not_with_presence(): void
    {
        $condition = new ConditionNode('lang_fr', ComparisonOperator::PRESENCE);
        $notNode = new NotNode($condition);

        $sql = $notNode->toSql('clusters', DatabaseDriver::PGSQL);

        $expected = "NOT (clusters->'lang_fr' IS NOT NULL)";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_not(): void
    {
        $condition = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $notNode = new NotNode($condition);

        $sql = $notNode->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "NOT (json_extract(clusters, '$.status') = 'active')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_not_with_presence(): void
    {
        $condition = new ConditionNode('lang_fr', ComparisonOperator::PRESENCE);
        $notNode = new NotNode($condition);

        $sql = $notNode->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "NOT (json_extract(clusters, '$.lang_fr') IS NOT NULL)";
        $this->assertEquals($expected, $sql);
    }

    // ==================== TO ELOQUENT TESTS ====================

    public function test_to_eloquent_not(): void
    {
        $condition = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $notNode = new NotNode($condition);

        $query = TestCluster::query();
        $notNode->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_to_eloquent_not_with_presence(): void
    {
        $condition = new ConditionNode('lang_fr', ComparisonOperator::PRESENCE);
        $notNode = new NotNode($condition);

        $query = TestCluster::query();
        $notNode->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        // NOT lang_fr IS NOT NULL = lang_fr IS NULL
        // Aucun cluster n'a lang_fr = null, donc 0
        $this->assertCount(0, $results);
    }

    public function test_to_eloquent_not_with_absence(): void
    {
        $condition = new ConditionNode('lang_en', ComparisonOperator::ABSENCE);
        $notNode = new NotNode($condition);

        $query = TestCluster::query();
        $notNode->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        // NOT lang_en IS NULL = lang_en IS NOT NULL
        // Tous les clusters ont lang_en, donc 5
        $this->assertCount(5, $results);
    }

    public function test_to_eloquent_not_with_complex_condition(): void
    {
        $condition = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '30');
        $notNode = new NotNode($condition);

        $query = TestCluster::query();
        $notNode->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_not_with_and_condition(): void
    {
        $condition1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $condition2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');
        $andGroup = new GroupNode(
            LogicalOperator::AND,
            $condition1,
            $condition2
        );

        $notNode = new NotNode($andGroup);

        $query = TestCluster::query();
        $notNode->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_not_with_or_condition(): void
    {
        $condition1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $condition2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');
        $orGroup = new GroupNode(
            LogicalOperator::OR,
            $condition1,
            $condition2
        );

        $notNode = new NotNode($orGroup);

        $query = TestCluster::query();
        $notNode->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    // ==================== GET CHILDREN TESTS ====================

    public function test_get_children(): void
    {
        $condition = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $notNode = new NotNode($condition);

        $children = $notNode->getChildren();

        $this->assertCount(1, $children);
        $this->assertSame($condition, $children[0]);
    }
}
