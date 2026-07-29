<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\Nodes;

use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Enums\LogicalOperator;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

final class SubConditionNodeTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cluster 1: John avec 2 adresses
        TestCluster::create([
            'clusters' => [
                'name' => 'John Doe',
                'age' => 30,
                'status' => 'active',
                'addresses' => [
                    [
                        'city' => 'Kinshasa',
                        'street' => 'Avenue de la Paix',
                        'country' => 'RDC',
                        'postal_code' => '1000',
                        'is_primary' => 'true',
                    ],
                    [
                        'city' => 'Lubumbashi',
                        'street' => 'Avenue Lumumba',
                        'country' => 'RDC',
                        'postal_code' => '2000',
                        'is_primary' => 'false',
                    ],
                ],
                'tags' => ['php', 'js', 'docker'],
                'settings' => [
                    'notifications' => [
                        'email' => 'true',
                        'sms' => 'false',
                        'push' => 'true',
                    ],
                    'theme' => 'dark',
                ],
            ],
        ]);

        // Cluster 2: Jane avec 1 adresse
        TestCluster::create([
            'clusters' => [
                'name' => 'Jane Smith',
                'age' => 25,
                'status' => 'inactive',
                'addresses' => [
                    [
                        'city' => 'Paris',
                        'street' => 'Rue de Rivoli',
                        'country' => 'France',
                        'postal_code' => '75001',
                        'is_primary' => 'true',
                    ],
                ],
                'tags' => ['python', 'react'],
                'settings' => [
                    'notifications' => [
                        'email' => 'false',
                        'sms' => 'true',
                        'push' => 'false',
                    ],
                    'theme' => 'light',
                ],
            ],
        ]);

        // Cluster 3: Bob avec 3 adresses
        TestCluster::create([
            'clusters' => [
                'name' => 'Bob Johnson',
                'age' => 35,
                'status' => 'active',
                'addresses' => [
                    [
                        'city' => 'Kinshasa',
                        'street' => 'Boulevard du 30 Juin',
                        'country' => 'RDC',
                        'postal_code' => '1000',
                        'is_primary' => 'true',
                    ],
                    [
                        'city' => 'Paris',
                        'street' => 'Avenue des Champs-Élysées',
                        'country' => 'France',
                        'postal_code' => '75008',
                        'is_primary' => 'false',
                    ],
                    [
                        'city' => 'London',
                        'street' => 'Oxford Street',
                        'country' => 'UK',
                        'postal_code' => 'W1D 1BS',
                        'is_primary' => 'false',
                    ],
                ],
                'tags' => ['php', 'laravel', 'vuejs'],
                'settings' => [
                    'notifications' => [
                        'email' => 'true',
                        'sms' => 'true',
                        'push' => 'true',
                    ],
                    'theme' => 'dark',
                ],
            ],
        ]);

        // Cluster 4: Alice sans adresses
        TestCluster::create([
            'clusters' => [
                'name' => 'Alice Wonder',
                'age' => 28,
                'status' => 'pending',
                'addresses' => [],
                'tags' => ['go', 'rust'],
                'settings' => [
                    'notifications' => [
                        'email' => 'false',
                        'sms' => 'false',
                        'push' => 'false',
                    ],
                    'theme' => 'light',
                ],
            ],
        ]);
    }

    // ==================== EVALUATE TESTS ====================

    public function test_evaluate_subcondition_simple(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
        $node = new SubConditionNode('addresses', $condition);

        $cluster = new ClusterVO([
            'addresses' => [
                ['city' => 'kinshasa', 'street' => 'Avenue de la Paix'],
            ],
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_subcondition_simple_false(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
        $node = new SubConditionNode('addresses', $condition);

        $cluster = new ClusterVO([
            'addresses' => [
                ['city' => 'paris', 'street' => 'Rue de Rivoli'],
            ],
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_subcondition_with_and(): void
    {
        $cityCondition = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
        $countryCondition = new ConditionNode('country', ComparisonOperator::EQUAL, 'rdc');
        $andNode = new GroupNode(LogicalOperator::AND, $cityCondition, $countryCondition);
        $node = new SubConditionNode('addresses', $andNode);

        $cluster = new ClusterVO([
            'addresses' => [
                ['city' => 'kinshasa', 'country' => 'rdc'],
            ],
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_subcondition_with_and_false(): void
    {
        $cityCondition = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
        $countryCondition = new ConditionNode('country', ComparisonOperator::EQUAL, 'france');
        $andNode = new GroupNode(LogicalOperator::AND, $cityCondition, $countryCondition);
        $node = new SubConditionNode('addresses', $andNode);

        $cluster = new ClusterVO([
            'addresses' => [
                ['city' => 'kinshasa', 'country' => 'rdc'],
            ],
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_subcondition_with_or(): void
    {
        $condition1 = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
        $condition2 = new ConditionNode('city', ComparisonOperator::EQUAL, 'paris');
        $orNode = new GroupNode(LogicalOperator::OR, $condition1, $condition2);
        $node = new SubConditionNode('addresses', $orNode);

        $cluster = new ClusterVO([
            'addresses' => [
                ['city' => 'kinshasa', 'street' => 'Avenue de la Paix'],
            ],
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_subcondition_with_like(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::LIKE, 'kin%');
        $node = new SubConditionNode('addresses', $condition);

        $cluster = new ClusterVO([
            'addresses' => [
                ['city' => 'kinshasa', 'street' => 'Avenue de la Paix'],
            ],
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_subcondition_with_like_false(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::LIKE, 'kin%');
        $node = new SubConditionNode('addresses', $condition);

        $cluster = new ClusterVO([
            'addresses' => [
                ['city' => 'paris', 'street' => 'Rue de Rivoli'],
            ],
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_subcondition_with_nested_path(): void
    {
        $condition = new ConditionNode('email', ComparisonOperator::EQUAL, 'true');
        $node = new SubConditionNode('settings.notifications', $condition);

        $cluster = new ClusterVO([
            'settings' => [
                'notifications' => [
                    ['email' => 'true', 'sms' => 'false'],
                ],
            ],
        ]);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_subcondition_with_nested_path_false(): void
    {
        $condition = new ConditionNode('email', ComparisonOperator::EQUAL, 'true');
        $node = new SubConditionNode('settings.notifications', $condition);

        $cluster = new ClusterVO([
            'settings' => [
                'notifications' => [
                    ['email' => 'false', 'sms' => 'true'],
                ],
            ],
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_subcondition_with_no_addresses(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
        $node = new SubConditionNode('addresses', $condition);

        $cluster = new ClusterVO([
            'addresses' => [],
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_subcondition_with_path_not_exists(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
        $node = new SubConditionNode('addresses', $condition);

        $cluster = new ClusterVO([
            'name' => 'John Doe',
        ]);

        $this->assertFalse($node->evaluate($cluster));
    }

    // ==================== TO SQL TESTS ====================

    public function test_to_sql_sqlite_subcondition_simple(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
        $node = new SubConditionNode('addresses', $condition);

        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE json_extract(value, '$.city') = 'kinshasa')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_subcondition_with_and(): void
    {
        $cityCondition = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
        $countryCondition = new ConditionNode('country', ComparisonOperator::EQUAL, 'rdc');
        $andNode = new GroupNode(LogicalOperator::AND, $cityCondition, $countryCondition);
        $node = new SubConditionNode('addresses', $andNode);

        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE json_extract(value, '$.city') = 'kinshasa' AND json_extract(value, '$.country') = 'rdc')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_subcondition_with_or(): void
    {
        $condition1 = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
        $condition2 = new ConditionNode('city', ComparisonOperator::EQUAL, 'paris');
        $orNode = new GroupNode(LogicalOperator::OR, $condition1, $condition2);
        $node = new SubConditionNode('addresses', $orNode);

        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE json_extract(value, '$.city') = 'kinshasa' OR json_extract(value, '$.city') = 'paris')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_subcondition_with_like(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::LIKE, 'kin%');
        $node = new SubConditionNode('addresses', $condition);

        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE json_extract(value, '$.city') LIKE 'kin%')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_subcondition_with_not_like(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::NOT_LIKE, 'kin%');
        $node = new SubConditionNode('addresses', $condition);

        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE json_extract(value, '$.city') NOT LIKE 'kin%')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_subcondition_with_exists(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::EXISTS);
        $node = new SubConditionNode('addresses', $condition);

        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE json_extract(value, '$.city') IS NOT NULL)";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_subcondition_with_not_exists(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::NOT_EXISTS);
        $node = new SubConditionNode('addresses', $condition);

        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        // NOT EXISTS avec IS NOT NULL à l'intérieur
        $expected = "NOT EXISTS (SELECT 1 FROM json_each(clusters, '$.addresses') WHERE json_extract(value, '$.city') IS NOT NULL)";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_subcondition_with_nested_path(): void
    {
        $condition = new ConditionNode('email', ComparisonOperator::EQUAL, 'true');
        $node = new SubConditionNode('settings.notifications', $condition);

        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "EXISTS (SELECT 1 FROM json_each(clusters, '$.settings.notifications') WHERE json_extract(value, '$.email') = 'true')";
        $this->assertEquals($expected, $sql);
    }

    // ==================== TO ELOQUENT TESTS ====================

    public function test_to_eloquent_sqlite_subcondition_simple(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa');
        $node = new SubConditionNode('addresses', $condition);

        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $results = $query->get();
        $this->assertCount(2, $results); // John et Bob
    }

    public function test_to_eloquent_sqlite_subcondition_with_and(): void
    {
        $cityCondition = new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa');
        $countryCondition = new ConditionNode('country', ComparisonOperator::EQUAL, 'RDC');
        $andNode = new GroupNode(LogicalOperator::AND, $cityCondition, $countryCondition);
        $node = new SubConditionNode('addresses', $andNode);

        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $results = $query->get();
        $this->assertCount(2, $results); // John et Bob
    }

    public function test_to_eloquent_sqlite_subcondition_with_or(): void
    {
        $condition1 = new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa');
        $condition2 = new ConditionNode('city', ComparisonOperator::EQUAL, 'Paris');
        $orNode = new GroupNode(LogicalOperator::OR, $condition1, $condition2);
        $node = new SubConditionNode('addresses', $orNode);

        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $results = $query->get();
        $this->assertCount(3, $results); // John, Jane, Bob
    }

    public function test_to_eloquent_sqlite_subcondition_with_like(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::LIKE, 'Kin%');
        $node = new SubConditionNode('addresses', $condition);

        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $results = $query->get();
        $this->assertCount(2, $results); // John et Bob
    }

    public function test_to_eloquent_sqlite_subcondition_with_nested_path(): void
    {
        // Créer des données avec notifications en tableau
        TestCluster::create([
            'clusters' => [
                'name' => 'John',
                'settings' => [
                    'notifications' => [
                        ['email' => 'true', 'sms' => 'false', 'push' => 'true'],
                    ],
                    'theme' => 'dark',
                ],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Jane',
                'settings' => [
                    'notifications' => [
                        ['email' => 'false', 'sms' => 'true', 'push' => 'false'],
                    ],
                    'theme' => 'light',
                ],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Bob',
                'settings' => [
                    'notifications' => [
                        ['email' => 'true', 'sms' => 'true', 'push' => 'true'],
                    ],
                    'theme' => 'dark',
                ],
            ],
        ]);

        $condition = new ConditionNode('email', ComparisonOperator::EQUAL, 'true');
        $node = new SubConditionNode('settings.notifications', $condition);

        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $results = $query->get();
        $this->assertCount(2, $results); // John et Bob (email=true)
    }

    public function test_to_eloquent_sqlite_subcondition_with_exists(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::EXISTS);
        $node = new SubConditionNode('addresses', $condition);

        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $results = $query->get();
        $this->assertCount(3, $results); // John, Jane, Bob
    }

    public function test_to_eloquent_sqlite_subcondition_with_not_exists(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::NOT_EXISTS);
        $node = new SubConditionNode('addresses', $condition);

        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $results = $query->get();
        $this->assertCount(1, $results); // Alice
    }

    public function test_full_query_with_subcondition_and_status(): void
    {
        $statusNode = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $cityNode = new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa');
        $subNode = new SubConditionNode('addresses', $cityNode);
        $andNode = new GroupNode(LogicalOperator::AND, $statusNode, $subNode);

        $query = TestCluster::query();
        $andNode->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $results = $query->get();
        $this->assertCount(2, $results); // John et Bob
    }

    public function test_full_query_with_subcondition_or_status(): void
    {
        $statusNode = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $cityNode = new ConditionNode('city', ComparisonOperator::EQUAL, 'Paris');
        $subNode = new SubConditionNode('addresses', $cityNode);
        $orNode = new GroupNode(LogicalOperator::OR, $statusNode, $subNode);

        $query = TestCluster::query();
        $orNode->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $results = $query->get();
        $this->assertCount(3, $results); // John, Jane, Bob
    }

    public function test_full_query_with_complex_subcondition(): void
    {
        $city1 = new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa');
        $country1 = new ConditionNode('country', ComparisonOperator::EQUAL, 'RDC');
        $and1 = new GroupNode(LogicalOperator::AND, $city1, $country1);

        $city2 = new ConditionNode('city', ComparisonOperator::EQUAL, 'Paris');
        $country2 = new ConditionNode('country', ComparisonOperator::EQUAL, 'France');
        $and2 = new GroupNode(LogicalOperator::AND, $city2, $country2);

        $orNode = new GroupNode(LogicalOperator::OR, $and1, $and2);
        $subNode = new SubConditionNode('addresses', $orNode);

        $query = TestCluster::query();
        $subNode->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $results = $query->get();
        $this->assertCount(3, $results); // John, Jane, Bob
    }

    public function test_to_eloquent_sqlite_subcondition_combined_with_condition(): void
    {
        $statusCondition = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $addressCondition = new ConditionNode('city', ComparisonOperator::EQUAL, 'Kinshasa');
        $subNode = new SubConditionNode('addresses', $addressCondition);

        $query = TestCluster::query();
        $statusCondition->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);
        $subNode->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $results = $query->get();
        $this->assertCount(2, $results); // John et Bob
    }

    // ==================== GET CHILDREN TESTS ====================

    public function test_get_children(): void
    {
        $condition = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
        $node = new SubConditionNode('addresses', $condition);

        $children = $node->getChildren();
        $this->assertCount(1, $children);
        $this->assertInstanceOf(ConditionNode::class, $children[0]);
    }

    public function test_get_children_with_group(): void
    {
        $cityCondition = new ConditionNode('city', ComparisonOperator::EQUAL, 'kinshasa');
        $countryCondition = new ConditionNode('country', ComparisonOperator::EQUAL, 'rdc');
        $andNode = new GroupNode(LogicalOperator::AND, $cityCondition, $countryCondition);
        $node = new SubConditionNode('addresses', $andNode);

        $children = $node->getChildren();
        $this->assertCount(1, $children);
        $this->assertInstanceOf(GroupNode::class, $children[0]);
    }
}
