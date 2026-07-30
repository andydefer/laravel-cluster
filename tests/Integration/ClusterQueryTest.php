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
use Illuminate\Foundation\Testing\RefreshDatabase;

final class ClusterQueryTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private ClusterQuery $clusterQuery;

    private ClusterVOCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();
        TestCluster::truncate();

        $this->clusterQuery = new ClusterQuery;

        // ✅ Données avec addresses en tableau d'objets et scores en tableau
        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'admin',
                'age' => '25',
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => '85.5',
                'scores' => [85, 90, 82],
                'name' => 'john_doe',
                'addresses' => [
                    ['city' => 'Kinshasa', 'street' => 'Avenue de la Paix'],
                    ['city' => 'Lubumbashi', 'street' => 'Avenue Lumumba'],
                ],
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
                'scores' => [92, 88, 95],
                'name' => 'jane_smith',
                'addresses' => [
                    ['city' => 'Paris', 'street' => 'Rue de Rivoli'],
                ],
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
                'scores' => [75, 80, 79],
                'name' => 'bob_johnson',
                'addresses' => [
                    ['city' => 'Kinshasa', 'street' => 'Boulevard du 30 Juin'],
                    ['city' => 'London', 'street' => 'Oxford Street'],
                    ['city' => 'Paris', 'street' => 'Avenue des Champs-Élysées'],
                ],
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
                'scores' => [30, 35, 28],
                'name' => 'alice_johanson',
                'addresses' => [],
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
                'scores' => [92, 98, 95],
                'name' => 'charlie_doe',
                'addresses' => [
                    ['city' => 'Kinshasa', 'street' => 'Avenue de la Paix'],
                    ['city' => 'Paris', 'street' => 'Avenue des Champs-Élysées'],
                ],
            ],
        ]);

        // Collection en mémoire
        $this->collection = new ClusterVOCollection;
        $this->collection->add(new ClusterVO([
            'status' => 'active',
            'role' => 'admin',
            'age' => '25',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'verified' => 'true',
            'score' => '85.5',
            'scores' => [85, 90, 82],
            'name' => 'john_doe',
            'addresses' => [
                ['city' => 'Kinshasa', 'street' => 'Avenue de la Paix'],
                ['city' => 'Lubumbashi', 'street' => 'Avenue Lumumba'],
            ],
        ]));
        $this->collection->add(new ClusterVO([
            'status' => 'inactive',
            'role' => 'doctor',
            'age' => '30',
            'lang_fr' => 'false',
            'lang_en' => 'true',
            'verified' => 'false',
            'score' => '92.0',
            'scores' => [92, 88, 95],
            'name' => 'jane_smith',
            'addresses' => [
                ['city' => 'Paris', 'street' => 'Rue de Rivoli'],
            ],
        ]));
        $this->collection->add(new ClusterVO([
            'status' => 'active',
            'role' => 'doctor',
            'age' => '35',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'verified' => 'true',
            'score' => '78.0',
            'scores' => [75, 80, 79],
            'name' => 'bob_johnson',
            'addresses' => [
                ['city' => 'Kinshasa', 'street' => 'Boulevard du 30 Juin'],
                ['city' => 'London', 'street' => 'Oxford Street'],
                ['city' => 'Paris', 'street' => 'Avenue des Champs-Élysées'],
            ],
        ]));
        $this->collection->add(new ClusterVO([
            'status' => 'pending',
            'role' => 'guest',
            'age' => '18',
            'lang_fr' => 'false',
            'lang_en' => 'true',
            'verified' => 'false',
            'score' => '30.5',
            'scores' => [30, 35, 28],
            'name' => 'alice_johanson',
            'addresses' => [],
        ]));
        $this->collection->add(new ClusterVO([
            'status' => 'active',
            'role' => 'admin',
            'age' => '40',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'verified' => 'true',
            'score' => '95.0',
            'scores' => [92, 98, 95],
            'name' => 'charlie_doe',
            'addresses' => [
                ['city' => 'Kinshasa', 'street' => 'Avenue de la Paix'],
                ['city' => 'Paris', 'street' => 'Avenue des Champs-Élysées'],
            ],
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

        $query = TestCluster::query();

        // John a Kinshasa, Bob a Kinshasa, Charlie a Kinshasa
        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'addresses[city=Kinshasa]',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(3, $results); // John, Bob, Charlie
    }

    public function test_apply_to_eloquent_subcondition_with_and(): void
    {
        $query = TestCluster::query();

        // ✅ Utiliser city et street qui existent dans les données
        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'addresses[city=Kinshasa & street="Avenue de la Paix"]',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();

        // John (Kinshasa + Avenue de la Paix) et Charlie (Kinshasa + Avenue de la Paix)
        $this->assertCount(2, $results);
        $names = $results->pluck('clusters')->pluck('name')->toArray();
        $this->assertContains('john_doe', $names);
        $this->assertContains('charlie_doe', $names);
    }

    public function test_apply_to_eloquent_subcondition_with_or(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'addresses[city=Kinshasa | city=Paris]',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        // John (Kinshasa + Paris), Jane (Paris), Bob (Kinshasa + Paris), Charlie (Kinshasa + Paris)
        $this->assertCount(4, $results);
    }

    public function test_apply_to_eloquent_subcondition_with_like(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'addresses[city=~kin%]',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(3, $results);
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
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'addresses[]',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(4, $results); // Tous sauf Alice

        $names = $results->pluck('clusters')->pluck('name')->toArray();
        $this->assertContains('john_doe', $names);
        $this->assertContains('jane_smith', $names);
        $this->assertContains('bob_johnson', $names);
        $this->assertContains('charlie_doe', $names);
        $this->assertNotContains('alice_johanson', $names);
    }

    public function test_apply_to_eloquent_subcondition_combined_with_condition(): void
    {

        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status=active & addresses[city=Kinshasa]',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(3, $results); // john_doe, bob_johnson, charlie_doe

        $names = $results->pluck('clusters')->pluck('name')->toArray();
        $this->assertContains('john_doe', $names);
        $this->assertContains('bob_johnson', $names);
        $this->assertContains('charlie_doe', $names);
        $this->assertNotContains('jane_smith', $names);
        $this->assertNotContains('alice_johanson', $names);
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

    // ==================== SQL FUNCTION FILTER TESTS ====================

    public function test_filter_with_count_function(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b', 'c'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => ['a', 'b'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => ['a'],
        ]));

        $result = $this->clusterQuery->filter($collection, 'COUNT(addresses) > 2');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_with_count_equals(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => ['a', 'b', 'c'],
        ]));

        $result = $this->clusterQuery->filter($collection, 'COUNT(addresses) = 2');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_with_sum_function(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'prices' => [100, 200, 300],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'prices' => [50, 75],
        ]));

        $result = $this->clusterQuery->filter($collection, 'SUM(prices) > 500');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_with_avg_function(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => [80, 90, 85],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'scores' => [70, 75, 80],
        ]));

        $result = $this->clusterQuery->filter($collection, 'AVG(scores) >= 85');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_with_min_function(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => [80, 90, 85],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'scores' => [70, 75, 80],
        ]));

        $result = $this->clusterQuery->filter($collection, 'MIN(scores) > 75');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_with_max_function(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => [80, 90, 85],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'scores' => [95, 98, 92],
        ]));

        $result = $this->clusterQuery->filter($collection, 'MAX(scores) > 90');

        $this->assertCount(1, $result);
        $this->assertEquals('Jane', $result->get()[0]->get('name'));
    }

    public function test_filter_with_length_function(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John Doe',
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
        ]));

        $result = $this->clusterQuery->filter($collection, 'LENGTH(name) > 5');

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->get()[0]->get('name'));
    }

    public function test_filter_with_count_function_and_condition(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'status' => 'active',
            'addresses' => ['a', 'b', 'c'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'status' => 'active',
            'addresses' => ['a', 'b'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'status' => 'inactive',
            'addresses' => ['a', 'b', 'c'],
        ]));

        $result = $this->clusterQuery->filter($collection, 'status=active & COUNT(addresses) > 2');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_with_multiple_functions(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b'],
            'prices' => [100, 200, 300],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => ['a', 'b', 'c'],
            'prices' => [50, 75],
        ]));

        $result = $this->clusterQuery->filter($collection, 'COUNT(addresses) > 1 & SUM(prices) > 500');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_with_function_or_condition(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b', 'c'],
            'prices' => [50, 75],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => ['a', 'b'],
            'prices' => [100, 200, 300],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => ['a'],
            'prices' => [50, 75],
        ]));

        $result = $this->clusterQuery->filter($collection, 'COUNT(addresses) > 2 | SUM(prices) > 500');

        $this->assertCount(2, $result);
    }

    public function test_filter_with_nested_path_and_function(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'settings' => [
                'notifications' => [
                    ['email' => 'true'],
                    ['sms' => 'true'],
                ],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'settings' => [
                'notifications' => [
                    ['email' => 'true'],
                ],
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, 'COUNT(settings.notifications) > 1');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_with_function_and_parentheses(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b', 'c'],
            'status' => 'active',
            'prices' => [50, 75],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => ['a', 'b'],
            'status' => 'active',
            'prices' => [100, 200, 300],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => ['a', 'b'],
            'status' => 'inactive',
            'prices' => [100, 200, 300],
        ]));

        $result = $this->clusterQuery->filter(
            $collection,
            '(COUNT(addresses) > 2 | SUM(prices) > 500) & status=active'
        );

        $this->assertCount(2, $result);
    }

    public function test_filter_with_function_no_operator(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => [],
        ]));

        // COUNT(addresses) sans opérateur → COUNT > 0
        $result = $this->clusterQuery->filter($collection, 'COUNT(addresses)');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_filter_with_json_length_function(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b', 'c'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => ['a', 'b'],
        ]));

        $result = $this->clusterQuery->filter($collection, 'JSON_LENGTH(addresses) > 2');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    // ==================== SQL FUNCTION ELOQUENT TESTS ====================

    public function test_apply_to_eloquent_count_function(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'COUNT(addresses) > 2',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        // Bob a 3 adresses (dans les données existantes)
        $this->assertCount(1, $results);
        $this->assertEquals('bob_johnson', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_count_equals(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'COUNT(addresses) = 0',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('alice_johanson', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_count_with_condition(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status=active & COUNT(addresses) > 2',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('bob_johnson', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_sum_function(): void
    {
        // Créer des données avec des prix
        TestCluster::create([
            'clusters' => [
                'name' => 'test1',
                'prices' => [100, 200, 300],
            ],
        ]);
        TestCluster::create([
            'clusters' => [
                'name' => 'test2',
                'prices' => [50, 75],
            ],
        ]);

        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'SUM(prices) > 500',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('test1', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_avg_function(): void
    {
        // ✅ Vider la base pour ce test
        TestCluster::truncate();

        TestCluster::create([
            'clusters' => [
                'name' => 'test1',
                'scores' => [80, 90, 85],
            ],
        ]);
        TestCluster::create([
            'clusters' => [
                'name' => 'test2',
                'scores' => [70, 75, 80],
            ],
        ]);

        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'AVG(scores) >= 85',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('test1', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_length_function(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'LENGTH(name) >= 8', // ✅ >= 8
            DatabaseDriver::SQLITE
        );

        $results = $query->get();
        $this->assertCount(5, $results); // Tous les noms (8, 10, 11, 14, 11)
    }

    public function test_apply_to_eloquent_complex_with_functions(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status=active & COUNT(addresses) > 1 & AVG(scores) >= 80',
            DatabaseDriver::SQLITE
        );

        $results = $query->get();

        // john_doe (active, 2 addresses, AVG=85.66) → ✅
        // bob_johnson (active, 3 addresses, AVG=78) → ❌ (AVG < 80)
        // charlie_doe (active, 2 addresses, AVG=95) → ✅
        $this->assertCount(2, $results);

        $names = $results->pluck('clusters')->pluck('name')->toArray();
        $this->assertContains('john_doe', $names);
        $this->assertContains('charlie_doe', $names);
        $this->assertNotContains('bob_johnson', $names);
    }

    // ==================== SQL FUNCTION MATCHES TESTS ====================

    public function test_matches_with_count_function(): void
    {
        $cluster = new ClusterVO([
            'addresses' => ['a', 'b', 'c'],
        ]);

        $result = $this->clusterQuery->matches($cluster, 'COUNT(addresses) > 2');

        $this->assertTrue($result);
    }

    public function test_matches_with_count_function_false(): void
    {
        $cluster = new ClusterVO([
            'addresses' => ['a', 'b'],
        ]);

        $result = $this->clusterQuery->matches($cluster, 'COUNT(addresses) > 2');

        $this->assertFalse($result);
    }

    public function test_matches_with_sum_function(): void
    {
        $cluster = new ClusterVO([
            'prices' => [100, 200, 300],
        ]);

        $result = $this->clusterQuery->matches($cluster, 'SUM(prices) > 500');

        $this->assertTrue($result);
    }

    public function test_matches_with_avg_function(): void
    {
        $cluster = new ClusterVO([
            'scores' => [80, 90, 85],
        ]);

        $result = $this->clusterQuery->matches($cluster, 'AVG(scores) >= 85');

        $this->assertTrue($result);
    }

    public function test_matches_with_length_function(): void
    {
        $cluster = new ClusterVO([
            'name' => 'John Doe',
        ]);

        $result = $this->clusterQuery->matches($cluster, 'LENGTH(name) > 5');

        $this->assertTrue($result);
    }

    // ==================== SQL FUNCTION TO SQL TESTS ====================

    public function test_to_sql_count_function_sqlite(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'COUNT(addresses) > 2', DatabaseDriver::SQLITE);

        $this->assertStringContainsString('json_array_length(clusters, \'$.addresses\') > 2', $sql);
    }

    public function test_to_sql_count_function_mysql(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'COUNT(addresses) > 2', DatabaseDriver::MYSQL);

        $this->assertStringContainsString('JSON_LENGTH(clusters, \'$.addresses\') > 2', $sql);
    }

    public function test_to_sql_sum_function_sqlite(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'SUM(prices) > 500', DatabaseDriver::SQLITE);

        $this->assertStringContainsString('CAST(json_extract(clusters, \'$.prices\') AS NUMERIC) > 500', $sql);
    }

    public function test_to_sql_avg_function_sqlite(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'AVG(scores) >= 85', DatabaseDriver::SQLITE);

        $this->assertStringContainsString('AVG(CAST(json_extract(clusters, \'$.scores\') AS NUMERIC)) >= 85', $sql);
    }

    public function test_to_sql_length_function_sqlite(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'LENGTH(name) > 5', DatabaseDriver::SQLITE);

        $this->assertStringContainsString('LENGTH(json_extract(clusters, \'$.name\')) > 5', $sql);
    }
}
