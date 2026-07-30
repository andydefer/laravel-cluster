<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Nodes\SubConditionNode;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

final class ClusterQueryTest extends IntegrationTestCase
{
    private ClusterQuery $clusterQuery;

    private ClusterVOCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clusterQuery = new ClusterQuery;

        // Données avec des strings pour éviter les problèmes de types booléens
        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'admin',
                'age' => '25',
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => '85.5',
                'name' => 'john_doe',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'inactive',
                'role' => 'doctor',
                'age' => '30',
                'lang_fr' => 'false',
                'lang_en' => 'true',
                'verified' => 'false',
                'score' => '92.0',
                'name' => 'jane_smith',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'doctor',
                'age' => '35',
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => '78.0',
                'name' => 'bob_johnson',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'pending',
                'role' => 'guest',
                'age' => '18',
                'lang_fr' => 'false',
                'lang_en' => 'true',
                'verified' => 'false',
                'score' => '30.5',
                'name' => 'alice_johanson',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'admin',
                'age' => '40',
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => '95.0',
                'name' => 'charlie_doe',
            ],
        ]);

        // Collection en mémoire avec des strings
        $this->collection = new ClusterVOCollection;
        $this->collection->add(new ClusterVO([
            'status' => 'active',
            'role' => 'admin',
            'age' => '25',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'verified' => 'true',
            'score' => '85.5',
            'name' => 'john_doe',
        ]));
        $this->collection->add(new ClusterVO([
            'status' => 'inactive',
            'role' => 'doctor',
            'age' => '30',
            'lang_fr' => 'false',
            'lang_en' => 'true',
            'verified' => 'false',
            'score' => '92.0',
            'name' => 'jane_smith',
        ]));
        $this->collection->add(new ClusterVO([
            'status' => 'active',
            'role' => 'doctor',
            'age' => '35',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'verified' => 'true',
            'score' => '78.0',
            'name' => 'bob_johnson',
        ]));
        $this->collection->add(new ClusterVO([
            'status' => 'pending',
            'role' => 'guest',
            'age' => '18',
            'lang_fr' => 'false',
            'lang_en' => 'true',
            'verified' => 'false',
            'score' => '30.5',
            'name' => 'alice_johanson',
        ]));
        $this->collection->add(new ClusterVO([
            'status' => 'active',
            'role' => 'admin',
            'age' => '40',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'verified' => 'true',
            'score' => '95.0',
            'name' => 'charlie_doe',
        ]));
    }

    // ==================== PARSE TESTS ====================

    public function test_parse_returns_node(): void
    {
        $result = $this->clusterQuery->parse('status=active');

        $this->assertInstanceOf(NodeInterface::class, $result);
        $this->assertInstanceOf(ConditionNode::class, $result);
    }

    public function test_parse_complex_expression_returns_group(): void
    {
        $result = $this->clusterQuery->parse('status=active & role=admin');

        $this->assertInstanceOf(GroupNode::class, $result);
    }

    public function test_parse_caches_result(): void
    {
        $ast1 = $this->clusterQuery->parse('status=active');
        $ast2 = $this->clusterQuery->parse('status=active');

        $this->assertSame($ast1, $ast2);
    }

    // ==================== FILTER TESTS (MEMORY) ====================

    public function test_filter_returns_filtered_collection(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'status=active');

        $this->assertInstanceOf(ClusterVOCollection::class, $result);
        $this->assertCount(3, $result);
    }

    public function test_filter_with_complex_query(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'status=active & role=admin'
        );

        $this->assertCount(2, $result);
    }

    public function test_filter_with_or(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'status=active | status=pending'
        );

        $this->assertCount(4, $result);
    }

    public function test_filter_with_parentheses(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            '(status=active | status=pending) & role=admin'
        );

        $this->assertCount(2, $result);
    }

    public function test_filter_with_presence(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'lang_fr'
        );

        $this->assertCount(3, $result);
    }

    public function test_filter_with_absence(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            '!lang_fr'
        );

        $this->assertCount(2, $result);
    }

    public function test_filter_with_true_value(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'lang_fr=true'
        );

        $this->assertCount(3, $result);
    }

    public function test_filter_with_false_value(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'lang_fr=false'
        );

        $this->assertCount(2, $result);
    }

    public function test_filter_with_gt_operator(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'age>30'
        );

        $this->assertCount(2, $result);
    }

    public function test_filter_with_gte_operator(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'age>=35'
        );

        $this->assertCount(2, $result);
    }

    public function test_filter_with_lt_operator(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'age<30'
        );

        $this->assertCount(2, $result);
    }

    public function test_filter_with_lte_operator(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'age<=25'
        );

        $this->assertCount(2, $result);
    }

    public function test_filter_with_not_equal(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'status!=active'
        );

        $this->assertCount(2, $result);
    }

    public function test_filter_with_empty_result(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'status=active & role=guest'
        );

        $this->assertCount(0, $result);
    }

    // ==================== SUB CONDITION FILTER TESTS ====================

    public function test_filter_subcondition_simple(): void
    {

        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => [
                ['city' => 'Kinshasa', 'street' => 'Avenue de la Paix'],
                ['city' => 'Lubumbashi', 'street' => 'Avenue Lumumba'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => [
                ['city' => 'Paris', 'street' => 'Rue de Rivoli'],
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, 'addresses[city=Kinshasa]');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_subcondition_with_and(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => [
                ['city' => 'Kinshasa', 'country' => 'RDC'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => [
                ['city' => 'Kinshasa', 'country' => 'France'],
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, 'addresses[city=kinshasa & country=rdc]');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_subcondition_with_or(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => [
                ['city' => 'Kinshasa'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => [
                ['city' => 'Paris'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => [
                ['city' => 'London'],
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, 'addresses[city=kinshasa | city=paris]');

        $this->assertCount(2, $result);
    }

    public function test_filter_subcondition_with_like(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => [
                ['city' => 'Kinshasa'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => [
                ['city' => 'Paris'],
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, 'addresses[city=~kin%]');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_subcondition_with_not_like(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => [
                ['city' => 'Kinshasa'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => [
                ['city' => 'Paris'],
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, 'addresses[city!~kin%]');

        $this->assertCount(1, $result);
        $this->assertEquals('Jane', $result->get()[0]->get('name'));
    }

    public function test_filter_subcondition_with_nested_path(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'settings' => [
                'notifications' => [
                    ['email' => 'true', 'sms' => 'false'],
                ],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'settings' => [
                'notifications' => [
                    ['email' => 'false', 'sms' => 'true'],
                ],
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, 'settings.notifications[email=true]');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_subcondition_with_exists(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => [
                ['city' => 'Kinshasa'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => [],
        ]));

        $result = $this->clusterQuery->filter($collection, 'addresses[]');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_subcondition_with_not_exists(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => [
                ['city' => 'Kinshasa'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => [],
        ]));

        $result = $this->clusterQuery->filter($collection, 'addresses[#city]');

        $this->assertCount(1, $result);
        $this->assertEquals('Jane', $result->get()[0]->get('name'));
    }

    public function test_filter_subcondition_with_complex_path(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => [
                [
                    'city' => 'Kinshasa',
                    'details' => [
                        'postal_code' => '1000',
                        'active' => 'true',
                    ],
                ],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => [
                [
                    'city' => 'Paris',
                    'details' => [
                        'postal_code' => '75001',
                        'active' => 'false',
                    ],
                ],
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, 'addresses[details.active=true]');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_subcondition_combined_with_condition(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'status' => 'active',
            'addresses' => [
                ['city' => 'Kinshasa'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'status' => 'active',
            'addresses' => [
                ['city' => 'Paris'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'status' => 'inactive',
            'addresses' => [
                ['city' => 'Kinshasa'],
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, 'status=active & addresses[city=kinshasa]');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_subcondition_with_or_and_parentheses(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'status' => 'active',
            'addresses' => [
                ['city' => 'Kinshasa'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'status' => 'pending',
            'addresses' => [
                ['city' => 'Paris'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'status' => 'active',
            'addresses' => [
                ['city' => 'London'],
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, '(status=active | status=pending) & addresses[city=kinshasa | city=paris]');

        $this->assertCount(2, $result);
    }

    // ==================== SUB CONDITION FILTER TESTS (ELOQUENT) ====================

    public function test_apply_to_eloquent_subcondition_simple(): void
    {
        TestCluster::create([
            'clusters' => [
                'name' => 'John',
                'addresses' => [
                    ['city' => 'Kinshasa', 'street' => 'Avenue de la Paix'],
                ],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Jane',
                'addresses' => [
                    ['city' => 'Paris', 'street' => 'Rue de Rivoli'],
                ],
            ],
        ]);

        $query = TestCluster::query();

        // Utiliser la bonne casse : "Kinshasa" au lieu de "kinshasa"
        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'addresses[city=Kinshasa]',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_subcondition_with_and(): void
    {
        TestCluster::create([
            'clusters' => [
                'name' => 'John',
                'addresses' => [
                    ['city' => 'Kinshasa', 'country' => 'RDC'],
                ],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Jane',
                'addresses' => [
                    ['city' => 'Kinshasa', 'country' => 'France'],
                ],
            ],
        ]);

        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'addresses[city=Kinshasa & country=RDC]',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_subcondition_with_or(): void
    {
        TestCluster::create([
            'clusters' => [
                'name' => 'John',
                'addresses' => [
                    ['city' => 'Kinshasa'],
                ],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Jane',
                'addresses' => [
                    ['city' => 'Paris'],
                ],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Bob',
                'addresses' => [
                    ['city' => 'London'],
                ],
            ],
        ]);

        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'addresses[city=Kinshasa | city=Paris]',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_subcondition_with_like(): void
    {
        TestCluster::create([
            'clusters' => [
                'name' => 'John',
                'addresses' => [
                    ['city' => 'Kinshasa'],
                ],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Jane',
                'addresses' => [
                    ['city' => 'Paris'],
                ],
            ],
        ]);

        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'addresses[city=~kin%]',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_subcondition_with_nested_path(): void
    {
        // Créer des données spécifiques avec notifications en tableau
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

    public function test_apply_to_eloquent_subcondition_with_exists(): void
    {
        TestCluster::create([
            'clusters' => [
                'name' => 'John',
                'addresses' => [
                    ['city' => 'Kinshasa'],
                ],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Jane',
                'addresses' => [],
            ],
        ]);

        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'addresses[]',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_subcondition_combined_with_condition(): void
    {
        TestCluster::create([
            'clusters' => [
                'name' => 'John',
                'status' => 'active',
                'addresses' => [
                    ['city' => 'Kinshasa'],
                ],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Jane',
                'status' => 'active',
                'addresses' => [
                    ['city' => 'Paris'],
                ],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Bob',
                'status' => 'inactive',
                'addresses' => [
                    ['city' => 'Kinshasa'],
                ],
            ],
        ]);

        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status=active & addresses[city=Kinshasa]',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John', $results->first()->clusters['name']);
    }

    // ==================== FILTER TESTS (ELOQUENT) ====================

    public function test_apply_to_eloquent_simple(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent($query, 'clusters', 'status=active', DatabaseDriver::SQLITE);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_complex_and(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status=active & role=admin',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_or(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status=active | status=pending',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(4, $results);
    }

    public function test_apply_to_eloquent_with_parentheses(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            '(status=active | status=pending) & role=admin',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_true_value(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'lang_fr=true',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_false_value(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'lang_fr=false',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_presence(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'lang_fr',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_absence(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            '!lang_fr',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_gt(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'age>30',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_gte(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'age>=35',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_not_equal(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status!=active',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_complex_nested(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            '(status=active | status=pending) & lang_fr & age>=25',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_empty_result(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status=active & role=guest',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(0, $results);
    }

    // ==================== MATCHES TESTS ====================

    public function test_matches_returns_true(): void
    {
        $cluster = new ClusterVO(['status' => 'active', 'role' => 'admin']);

        $result = $this->clusterQuery->matches($cluster, 'status=active & role=admin');

        $this->assertTrue($result);
    }

    public function test_matches_returns_false(): void
    {
        $cluster = new ClusterVO(['status' => 'inactive', 'role' => 'admin']);

        $result = $this->clusterQuery->matches($cluster, 'status=active & role=admin');

        $this->assertFalse($result);
    }

    public function test_matches_with_presence(): void
    {
        $cluster = new ClusterVO(['lang_fr' => 'true']);

        $result = $this->clusterQuery->matches($cluster, 'lang_fr');

        $this->assertTrue($result);
    }

    public function test_matches_with_absence(): void
    {
        $cluster = new ClusterVO(['lang_fr' => 'false']);

        $result = $this->clusterQuery->matches($cluster, '!lang_fr');

        $this->assertTrue($result);
    }

    public function test_matches_with_true_value(): void
    {
        $cluster = new ClusterVO(['lang_fr' => 'true']);

        $result = $this->clusterQuery->matches($cluster, 'lang_fr=true');

        $this->assertTrue($result);
    }

    public function test_matches_with_false_value(): void
    {
        $cluster = new ClusterVO(['lang_fr' => 'false']);

        $result = $this->clusterQuery->matches($cluster, 'lang_fr=false');

        $this->assertTrue($result);
    }

    // ==================== TO SQL TESTS ====================

    public function test_to_sql_sqlite(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'status=active', DatabaseDriver::SQLITE);

        $this->assertStringContainsString('json_extract', $sql);
    }

    public function test_to_sql_with_presence(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'lang_fr', DatabaseDriver::SQLITE);

        $this->assertStringContainsString("LOWER(json_extract(clusters, '$.lang_fr')) = LOWER('true')", $sql);
    }

    public function test_to_sql_with_absence(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', '!lang_fr', DatabaseDriver::SQLITE);

        $this->assertStringContainsString("LOWER(json_extract(clusters, '$.lang_fr')) = LOWER('false')", $sql);
    }

    public function test_to_sql_with_true_value(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'lang_fr=true', DatabaseDriver::SQLITE);

        $this->assertStringContainsString("LOWER(json_extract(clusters, '$.lang_fr')) = LOWER('true')", $sql);
    }

    public function test_to_sql_with_false_value(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'lang_fr=false', DatabaseDriver::SQLITE);

        $this->assertStringContainsString("LOWER(json_extract(clusters, '$.lang_fr')) = LOWER('false')", $sql);
    }

    // ==================== EDGE CASES TESTS ====================

    public function test_filter_with_empty_collection(): void
    {
        $emptyCollection = new ClusterVOCollection;

        $result = $this->clusterQuery->filter($emptyCollection, 'status=active');

        $this->assertCount(0, $result);
    }

    public function test_filter_with_non_existent_key(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'non_existent=value'
        );

        $this->assertCount(0, $result);
    }

    public function test_matches_with_non_existent_key(): void
    {
        $cluster = new ClusterVO(['status' => 'active']);

        $result = $this->clusterQuery->matches($cluster, 'non_existent=value');

        $this->assertFalse($result);
    }

    public function test_parse_empty_query_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->clusterQuery->parse('');
    }

    public function test_parse_invalid_query_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->clusterQuery->parse('status active');
    }

    // ==================== PERFORMANCE TESTS ====================

    public function test_filter_large_collection_performance(): void
    {
        $collection = new ClusterVOCollection;

        for ($i = 0; $i < 100; $i++) {
            $collection->add(new ClusterVO([
                'id' => $i,
                'status' => $i % 2 === 0 ? 'active' : 'inactive',
                'role' => $i % 3 === 0 ? 'admin' : 'user',
            ]));
        }

        $start = microtime(true);
        $result = $this->clusterQuery->filter($collection, 'status=active & role=admin');
        $end = microtime(true);

        $this->assertCount(17, $result);
        $this->assertLessThan(0.1, $end - $start);
    }

    // ==================== EXISTS / NOT_EXISTS FILTER TESTS ====================

    public function test_filter_with_exists_operator(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            '*lang_fr'
        );

        $this->assertCount(5, $result);
    }

    public function test_filter_with_not_exists_operator(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            '#lang_es'
        );

        $this->assertCount(5, $result);
    }

    public function test_filter_with_exists_and_condition(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            '*verified & status=active'
        );

        $this->assertCount(3, $result);
    }

    public function test_filter_with_not_exists_or_condition(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            '#lang_es | status=active'
        );

        $this->assertCount(5, $result);
    }

    public function test_filter_with_exists_complex(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            '(*lang_fr | #lang_en) & age>=25'
        );

        $this->assertCount(4, $result);
    }

    // ==================== LIKE / NOT_LIKE FILTER TESTS ====================

    public function test_filter_with_like_simple_contains(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_doe']));
        $collection->add(new ClusterVO(['name' => 'bob']));

        $result = $this->clusterQuery->filter($collection, 'name=~john');

        $this->assertCount(1, $result);
    }

    public function test_filter_with_like_case_insensitive(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'John_Doe']));
        $collection->add(new ClusterVO(['name' => 'Jane_doe']));

        $result = $this->clusterQuery->filter($collection, 'name=~JOHN');

        $this->assertCount(1, $result);
    }

    public function test_filter_with_like_starts_with(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_doe']));

        $result = $this->clusterQuery->filter($collection, 'name=~john%');

        $this->assertCount(1, $result);
    }

    public function test_filter_with_like_ends_with(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'john_doe_smith']));

        $result = $this->clusterQuery->filter($collection, 'name=~%doe');

        $this->assertCount(1, $result);
    }

    public function test_filter_with_not_like_operator(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_doe']));
        $collection->add(new ClusterVO(['name' => 'bob']));

        $result = $this->clusterQuery->filter($collection, 'name!~john');

        $this->assertCount(2, $result);
    }

    public function test_filter_with_like_and_condition(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe', 'status' => 'active']));
        $collection->add(new ClusterVO(['name' => 'john_doe', 'status' => 'inactive']));
        $collection->add(new ClusterVO(['name' => 'jane_doe', 'status' => 'active']));

        $result = $this->clusterQuery->filter($collection, 'name=~john & status=active');

        $this->assertCount(1, $result);
    }

    public function test_filter_with_like_or_condition(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_smith']));
        $collection->add(new ClusterVO(['name' => 'bob_johnson']));
        $collection->add(new ClusterVO(['name' => 'bob_dylan']));

        $result = $this->clusterQuery->filter($collection, 'name=~john | name=~jane');

        $this->assertCount(3, $result);
    }

    public function test_filter_with_like_and_eloquent(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'name=~john%',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('john_doe', $results->first()->clusters['name']);
    }

    public function test_filter_with_not_like_eloquent(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'name!~john%',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(4, $results);
    }

    public function test_matches_with_like(): void
    {
        $cluster = new ClusterVO(['name' => 'john_doe']);

        $result = $this->clusterQuery->matches($cluster, 'name=~john');

        $this->assertTrue($result);
    }

    public function test_matches_with_like_false(): void
    {
        $cluster = new ClusterVO(['name' => 'jane_doe']);

        $result = $this->clusterQuery->matches($cluster, 'name=~john');

        $this->assertFalse($result);
    }

    public function test_matches_with_not_like(): void
    {
        $cluster = new ClusterVO(['name' => 'jane_doe']);

        $result = $this->clusterQuery->matches($cluster, 'name!~john');

        $this->assertTrue($result);
    }
}
