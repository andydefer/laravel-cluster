<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\SqliteTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class ClusterQuerySqliteTest extends SqliteTestCase
{
    use RefreshDatabase;

    private ClusterQuery $clusterQuery;

    private ClusterVOCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();
        TestCluster::truncate();

        $this->clusterQuery = new ClusterQuery;

        $this->createTestData();
        $this->createCollectionData();
    }

    private function createTestData(): void
    {
        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'admin',
                'age' => '25',
                'lang_fr' => 'yes',
                'lang_en' => 'no',
                'verified' => 'yes',
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
                'lang_fr' => 'no',
                'lang_en' => 'yes',
                'verified' => 'no',
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
                'lang_fr' => 'yes',
                'lang_en' => 'no',
                'verified' => 'yes',
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
                'lang_fr' => 'no',
                'lang_en' => 'yes',
                'verified' => 'no',
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
                'lang_fr' => 'yes',
                'lang_en' => 'no',
                'verified' => 'yes',
                'score' => '95.0',
                'scores' => [92, 98, 95],
                'name' => 'charlie_doe',
                'addresses' => [
                    ['city' => 'Kinshasa', 'street' => 'Avenue de la Paix'],
                    ['city' => 'Paris', 'street' => 'Avenue des Champs-Élysées'],
                ],
            ],
        ]);
    }

    private function createCollectionData(): void
    {
        $this->collection = new ClusterVOCollection;
        $this->collection->add(new ClusterVO([
            'status' => 'active',
            'role' => 'admin',
            'age' => '25',
            'lang_fr' => 'yes',
            'lang_en' => 'no',
            'verified' => 'yes',
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
            'lang_fr' => 'no',
            'lang_en' => 'yes',
            'verified' => 'no',
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
            'lang_fr' => 'yes',
            'lang_en' => 'no',
            'verified' => 'yes',
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
            'lang_fr' => 'no',
            'lang_en' => 'yes',
            'verified' => 'no',
            'score' => '30.5',
            'scores' => [30, 35, 28],
            'name' => 'alice_johanson',
            'addresses' => [],
        ]));
        $this->collection->add(new ClusterVO([
            'status' => 'active',
            'role' => 'admin',
            'age' => '40',
            'lang_fr' => 'yes',
            'lang_en' => 'no',
            'verified' => 'yes',
            'score' => '95.0',
            'scores' => [92, 98, 95],
            'name' => 'charlie_doe',
            'addresses' => [
                ['city' => 'Kinshasa', 'street' => 'Avenue de la Paix'],
                ['city' => 'Paris', 'street' => 'Avenue des Champs-Élysées'],
            ],
        ]));
    }

    // ============================================================
    // PARSE TESTS
    // ============================================================

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

    // ============================================================
    // FILTER TESTS
    // ============================================================

    public function test_filter_returns_filtered_collection(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'status=active');
        $this->assertInstanceOf(ClusterVOCollection::class, $result);
        $this->assertCount(3, $result);
    }

    public function test_filter_with_complex_query(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'status=active & role=admin');
        $this->assertCount(2, $result);
    }

    public function test_filter_with_or(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'status=active | status=pending');
        $this->assertCount(4, $result);
    }

    public function test_filter_with_parentheses(): void
    {
        $result = $this->clusterQuery->filter($this->collection, '(status=active | status=pending) & role=admin');
        $this->assertCount(2, $result);
    }

    public function test_filter_with_presence(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'lang_fr');
        $this->assertCount(3, $result);
    }

    public function test_filter_with_absence(): void
    {
        $result = $this->clusterQuery->filter($this->collection, '!lang_fr');
        $this->assertCount(2, $result);
    }

    public function test_filter_with_yes_value(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'lang_fr=yes');
        $this->assertCount(3, $result);
    }

    public function test_filter_with_no_value(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'lang_fr=no');
        $this->assertCount(2, $result);
    }

    public function test_filter_with_gt_operator(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'age>30');
        $this->assertCount(2, $result);
    }

    public function test_filter_with_gte_operator(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'age>=35');
        $this->assertCount(2, $result);
    }

    public function test_filter_with_lt_operator(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'age<30');
        $this->assertCount(2, $result);
    }

    public function test_filter_with_lte_operator(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'age<=25');
        $this->assertCount(2, $result);
    }

    public function test_filter_with_not_equal(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'status!=active');
        $this->assertCount(2, $result);
    }

    public function test_filter_with_empty_result(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'status=active & role=guest');
        $this->assertCount(0, $result);
    }

    public function test_filter_with_empty_collection(): void
    {
        $emptyCollection = new ClusterVOCollection;
        $result = $this->clusterQuery->filter($emptyCollection, 'status=active');
        $this->assertCount(0, $result);
    }

    public function test_filter_with_non_existent_key(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'non_existent=value');
        $this->assertCount(0, $result);
    }

    // ============================================================
    // SUB CONDITION TESTS - SQLite
    // ============================================================

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
                    ['email' => 'yes', 'sms' => 'no'],
                ],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'settings' => [
                'notifications' => [
                    ['email' => 'no', 'sms' => 'yes'],
                ],
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, 'settings.notifications[email=yes]');

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

    // ============================================================
    // APPLY TO ELOQUENT TESTS - SQLite
    // ============================================================

    public function test_apply_to_eloquent_subcondition_simple(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'addresses[city=Kinshasa]',
            DatabaseDriver::SQLITE
        );
        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_subcondition_with_and(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'addresses[city=Kinshasa & street="Avenue de la Paix"]',
            DatabaseDriver::SQLITE
        );
        $results = $query->get();
        $this->assertCount(2, $results);
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
        TestCluster::truncate();

        TestCluster::create([
            'clusters' => [
                'name' => 'John',
                'settings' => [
                    'notifications' => [
                        ['email' => 'yes', 'sms' => 'no', 'push' => 'yes'],
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
                        ['email' => 'no', 'sms' => 'yes', 'push' => 'no'],
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
                        ['email' => 'yes', 'sms' => 'yes', 'push' => 'yes'],
                    ],
                    'theme' => 'dark',
                ],
            ],
        ]);

        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'settings.notifications[email=yes]',
            DatabaseDriver::SQLITE
        );
        $results = $query->get();
        $this->assertCount(2, $results);
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
        $this->assertCount(4, $results);
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
        $this->assertCount(3, $results);
    }

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
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'status=active & role=admin', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_or(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'status=active | status=pending', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(4, $results);
    }

    public function test_apply_to_eloquent_with_parentheses(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', '(status=active | status=pending) & role=admin', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_yes_value(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'lang_fr=yes', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_no_value(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'lang_fr=no', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_presence(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'lang_fr', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_absence(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', '!lang_fr', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_gt(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'age>30', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_gte(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'age>=35', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_not_equal(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'status!=active', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_complex_nested(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', '(status=active | status=pending) & lang_fr & age>=25', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_empty_result(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'status=active & role=guest', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(0, $results);
    }

    public function test_apply_to_eloquent_count_function(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'COUNT(addresses) > 2', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('bob_johnson', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_count_equals(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'COUNT(addresses) = 0', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('alice_johanson', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_count_with_condition(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'status=active & COUNT(addresses) > 2', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('bob_johnson', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_sum_function(): void
    {
        TestCluster::truncate();

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
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'SUM(prices) > 500', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('test1', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_avg_function(): void
    {
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
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'AVG(scores) >= 85', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('test1', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_length_function(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'LENGTH(name) >= 8', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(5, $results);
    }

    public function test_apply_to_eloquent_complex_with_functions(): void
    {
        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'status=active & COUNT(addresses) > 1 & AVG(scores) >= 80', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(2, $results);
    }

    // ============================================================
    // MATCHES TESTS
    // ============================================================

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
        $cluster = new ClusterVO(['lang_fr' => 'yes']);
        $result = $this->clusterQuery->matches($cluster, 'lang_fr');
        $this->assertTrue($result);
    }

    public function test_matches_with_absence(): void
    {
        $cluster = new ClusterVO(['lang_fr' => 'no']);
        $result = $this->clusterQuery->matches($cluster, '!lang_fr');
        $this->assertTrue($result);
    }

    public function test_matches_with_yes_value(): void
    {
        $cluster = new ClusterVO(['lang_fr' => 'yes']);
        $result = $this->clusterQuery->matches($cluster, 'lang_fr=yes');
        $this->assertTrue($result);
    }

    public function test_matches_with_no_value(): void
    {
        $cluster = new ClusterVO(['lang_fr' => 'no']);
        $result = $this->clusterQuery->matches($cluster, 'lang_fr=no');
        $this->assertTrue($result);
    }

    public function test_matches_with_non_existent_key(): void
    {
        $cluster = new ClusterVO(['status' => 'active']);
        $result = $this->clusterQuery->matches($cluster, 'non_existent=value');
        $this->assertFalse($result);
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

    public function test_matches_with_count_function(): void
    {
        $cluster = new ClusterVO(['addresses' => ['a', 'b', 'c']]);
        $result = $this->clusterQuery->matches($cluster, 'COUNT(addresses) > 2');
        $this->assertTrue($result);
    }

    public function test_matches_with_count_function_false(): void
    {
        $cluster = new ClusterVO(['addresses' => ['a', 'b']]);
        $result = $this->clusterQuery->matches($cluster, 'COUNT(addresses) > 2');
        $this->assertFalse($result);
    }

    public function test_matches_with_sum_function(): void
    {
        $cluster = new ClusterVO(['prices' => [100, 200, 300]]);
        $result = $this->clusterQuery->matches($cluster, 'SUM(prices) > 500');
        $this->assertTrue($result);
    }

    public function test_matches_with_avg_function(): void
    {
        $cluster = new ClusterVO(['scores' => [80, 90, 85]]);
        $result = $this->clusterQuery->matches($cluster, 'AVG(scores) >= 85');
        $this->assertTrue($result);
    }

    public function test_matches_with_length_function(): void
    {
        $cluster = new ClusterVO(['name' => 'John Doe']);
        $result = $this->clusterQuery->matches($cluster, 'LENGTH(name) > 5');
        $this->assertTrue($result);
    }

    // ============================================================
    // TO SQL TESTS - SQLite
    // ============================================================

    public function test_to_sql_sqlite(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'status=active', DatabaseDriver::SQLITE);
        $this->assertStringContainsString('json_extract', $sql);
    }

    public function test_to_sql_with_presence(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'lang_fr', DatabaseDriver::SQLITE);
        $this->assertStringContainsString("json_extract(clusters, '$.lang_fr') = 'yes'", $sql);
    }

    public function test_to_sql_with_absence(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', '!lang_fr', DatabaseDriver::SQLITE);
        $this->assertStringContainsString("json_extract(clusters, '$.lang_fr') = 'no'", $sql);
    }

    public function test_to_sql_with_yes_value(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'lang_fr=yes', DatabaseDriver::SQLITE);
        $this->assertStringContainsString("json_extract(clusters, '$.lang_fr') = 'yes'", $sql);
    }

    public function test_to_sql_with_no_value(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'lang_fr=no', DatabaseDriver::SQLITE);
        $this->assertStringContainsString("json_extract(clusters, '$.lang_fr') = 'no'", $sql);
    }

    public function test_to_sql_count_function_sqlite(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'COUNT(addresses) > 2', DatabaseDriver::SQLITE);
        $this->assertStringContainsString('json_array_length(clusters, \'$.addresses\') > 2', $sql);
    }

    public function test_to_sql_sum_function_sqlite(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'SUM(prices) > 500', DatabaseDriver::SQLITE);
        $this->assertStringContainsString('(SELECT SUM(json_extract(value, \'$\')) FROM json_each(clusters, \'$.prices\')) > 500', $sql);
    }

    public function test_to_sql_avg_function_sqlite(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'AVG(scores) >= 85', DatabaseDriver::SQLITE);
        $this->assertStringContainsString('(SELECT AVG(json_extract(value, \'$\')) FROM json_each(clusters, \'$.scores\')) >= 85', $sql);
    }

    // ============================================================
    // DOT NOTATION TESTS - SQLite
    // ============================================================

    public function test_filter_with_dot_notation_simple(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'settings' => [
                'theme' => 'dark',
                'language' => 'fr',
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'settings' => [
                'theme' => 'light',
                'language' => 'en',
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, 'settings.theme=dark');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->get()[0]->get('name'));
    }

    public function test_apply_to_eloquent_dot_notation_simple(): void
    {
        TestCluster::truncate();

        TestCluster::create([
            'clusters' => [
                'name' => 'John',
                'settings' => [
                    'theme' => 'dark',
                    'language' => 'fr',
                ],
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'name' => 'Jane',
                'settings' => [
                    'theme' => 'light',
                    'language' => 'en',
                ],
            ],
        ]);

        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'settings.theme=dark', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John', $results->first()->clusters['name']);
    }

    public function test_apply_to_eloquent_dot_notation_with_subcondition(): void
    {
        TestCluster::truncate();

        TestCluster::create([
            'clusters' => [
                'name' => 'John',
                'settings' => [
                    'notifications' => [
                        ['email' => 'yes', 'sms' => 'no'],
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
                        ['email' => 'no', 'sms' => 'yes'],
                    ],
                    'theme' => 'light',
                ],
            ],
        ]);

        $query = TestCluster::query();
        $this->clusterQuery->applyToEloquent($query, 'clusters', 'settings.notifications[email=yes] & settings.theme=dark', DatabaseDriver::SQLITE);
        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John', $results->first()->clusters['name']);
    }

    public function test_matches_dot_notation_true(): void
    {
        $cluster = new ClusterVO(['settings' => ['theme' => 'dark']]);
        $result = $this->clusterQuery->matches($cluster, 'settings.theme=dark');
        $this->assertTrue($result);
    }

    public function test_matches_dot_notation_false(): void
    {
        $cluster = new ClusterVO(['settings' => ['theme' => 'light']]);
        $result = $this->clusterQuery->matches($cluster, 'settings.theme=dark');
        $this->assertFalse($result);
    }

    // ============================================================
    // EXISTS / NOT EXISTS OPERATORS
    // ============================================================

    public function test_filter_with_exists_operator(): void
    {
        $result = $this->clusterQuery->filter($this->collection, '*lang_fr');
        $this->assertCount(5, $result);
    }

    public function test_filter_with_not_exists_operator(): void
    {
        $result = $this->clusterQuery->filter($this->collection, '#lang_es');
        $this->assertCount(5, $result);
    }

    public function test_filter_with_exists_and_condition(): void
    {
        $result = $this->clusterQuery->filter($this->collection, '*verified & status=active');
        $this->assertCount(3, $result);
    }

    public function test_filter_with_not_exists_or_condition(): void
    {
        $result = $this->clusterQuery->filter($this->collection, '#lang_es | status=active');
        $this->assertCount(5, $result);
    }
}
