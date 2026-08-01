<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\Providers;

use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use Illuminate\Support\Collection;

final class ClusterMacroCollectionTest extends IntegrationTestCase
{
    private string $column = 'clusters';

    protected function setUp(): void
    {
        parent::setUp();

        TestCluster::truncate();

        $this->createTestData();
    }

    private function createTestData(): void
    {
        $data = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'clusters' => [
                    'status' => 'active',
                    'name' => 'John Doe',
                    'role' => 'admin',
                    'age' => 30,
                    'verified' => 'yes',
                    'addresses' => [
                        ['city' => 'Kinshasa', 'country' => 'RDC'],
                        ['city' => 'Paris', 'country' => 'France'],
                    ],
                    'tags' => ['php', 'js', 'docker'],
                    'scores' => [80, 90, 85],
                ],
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'clusters' => [
                    'status' => 'inactive',
                    'role' => 'doctor',
                    'age' => 25,
                    'verified' => 'no',
                    'addresses' => [
                        ['city' => 'Paris', 'country' => 'France'],
                    ],
                    'tags' => ['python', 'react'],
                    'scores' => [70, 75, 80],
                ],
            ],
            [
                'name' => 'Bob Johnson',
                'email' => 'bob@example.com',
                'clusters' => [
                    'status' => 'active',
                    'role' => 'doctor',
                    'age' => 35,
                    'verified' => 'yes',
                    'addresses' => [
                        ['city' => 'Kinshasa', 'country' => 'RDC'],
                        ['city' => 'London', 'country' => 'UK'],
                        ['city' => 'Paris', 'country' => 'France'],
                    ],
                    'tags' => ['php', 'laravel', 'vuejs'],
                    'scores' => [95, 98, 92],
                ],
            ],
            [
                'name' => 'Alice Wonder',
                'email' => 'alice@example.com',
                'clusters' => [
                    'status' => 'pending',
                    'role' => 'guest',
                    'age' => 28,
                    'verified' => 'no',
                    'addresses' => [],
                    'tags' => ['go', 'rust'],
                    'scores' => [60, 65, 70],
                ],
            ],
        ];

        foreach ($data as $item) {
            TestCluster::create($item);
        }
    }

    public function test_where_cluster_on_collection_simple_condition(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, 'status=active');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    public function test_where_cluster_on_collection_with_and(): void
    {
        $users = TestCluster::all();

        $result = $users
            ->whereCluster($this->column, 'status=active')
            ->whereCluster($this->column, 'role=admin');

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    public function test_where_cluster_on_collection_with_comparison(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, 'age>30');

        $this->assertCount(1, $result);
        $this->assertEquals('Bob Johnson', $result->first()->name);
    }

    public function test_where_cluster_on_collection_with_like(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, 'name=~John%');

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    public function test_where_cluster_on_collection_with_subcondition(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, 'addresses[city=Kinshasa]');

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_where_cluster_on_collection_with_parentheses(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, '(status=active | status=pending) & role=admin');

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    public function test_where_cluster_on_collection_with_complex_expression(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster(
            $this->column,
            '(status=active | status=pending) & (role=admin | role=doctor) & age>=25'
        );

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_where_cluster_on_collection_with_not(): void
    {
        $users = TestCluster::all();

        $result = $users
            ->whereCluster($this->column, 'status=active')
            ->whereCluster($this->column, 'role!=admin');

        $this->assertCount(1, $result);
        $this->assertEquals('Bob Johnson', $result->first()->name);
    }

    public function test_where_cluster_on_collection_with_array_exists(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, 'addresses[]');

        $this->assertCount(3, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_cluster_on_collection_with_count_aggregation(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, '{COUNT(addresses) > 2}');

        $this->assertCount(1, $result);
        $this->assertEquals('Bob Johnson', $result->first()->name);
    }

    public function test_where_cluster_on_collection_with_avg_aggregation(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, '{AVG(scores) >= 90}');

        $this->assertCount(1, $result);
        $this->assertEquals('Bob Johnson', $result->first()->name);
    }

    public function test_where_cluster_on_collection_with_sum_aggregation(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, '{SUM(scores) > 250}');

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_where_cluster_on_collection_with_min_aggregation(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, '{MIN(scores) > 75}');

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_where_cluster_on_collection_with_max_aggregation(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, '{MAX(scores) >= 95}');

        $this->assertCount(1, $result);
        $this->assertEquals('Bob Johnson', $result->first()->name);
    }

    public function test_where_cluster_on_collection_with_has_aggregation(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, '{HAS(tags, "php")}');

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_where_cluster_on_collection_with_exists_aggregation(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, '{EXISTS(addresses)}');

        $this->assertCount(3, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_cluster_on_collection_with_complex_aggregate_expression(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster(
            $this->column,
            'status=active & {COUNT(addresses) > 1} & {AVG(scores) >= 85}'
        );

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_where_cluster_on_collection_chained_with_other_collection_methods(): void
    {
        $users = TestCluster::all();

        $result = $users
            ->whereCluster($this->column, 'status=active')
            ->whereCluster($this->column, '{COUNT(addresses) > 1}')
            ->pluck('name')
            ->toArray();

        $this->assertCount(2, $result);
        $this->assertContains('John Doe', $result);
        $this->assertContains('Bob Johnson', $result);
    }

    public function test_where_cluster_on_collection_chained_with_filter(): void
    {
        $users = TestCluster::all();

        $result = $users
            ->whereCluster($this->column, 'status=active')
            ->filter(function ($user) {
                return $user->name === 'John Doe';
            });

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    public function test_where_cluster_on_collection_preserves_keys(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, 'status=active');

        $this->assertCount(2, $result);
        $this->assertArrayHasKey(0, $result->toArray());
        $this->assertArrayHasKey(2, $result->toArray());
        $this->assertArrayNotHasKey(1, $result->toArray());
        $this->assertArrayNotHasKey(3, $result->toArray());
    }

    public function test_where_cluster_on_empty_collection(): void
    {
        $empty = new Collection;

        $result = $empty->whereCluster($this->column, 'status=active');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    public function test_where_cluster_on_collection_with_empty_result(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, 'status=super_admin');

        $this->assertCount(0, $result);
    }

    public function test_where_cluster_on_collection_with_non_existent_json_key(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, 'non_existent=active');

        $this->assertCount(0, $result);
    }

    public function test_where_cluster_on_collection_with_invalid_query(): void
    {
        $users = TestCluster::all();

        $result = $users->whereCluster($this->column, 'invalid query');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }
}
