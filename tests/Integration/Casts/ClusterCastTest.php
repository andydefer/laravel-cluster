<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\Casts;

use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

final class ClusterCastTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestCluster::truncate();
    }

    public function test_casting_returns_cluster_vo_from_array(): void
    {
        $model = TestCluster::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'clusters' => ['status' => 'active', 'role' => 'admin', 'age' => 30],
        ]);

        $fresh = TestCluster::find($model->id);

        $this->assertInstanceOf(ClusterVO::class, $fresh->clusters);
        $this->assertSame('active', $fresh->clusters->get('status'));
        $this->assertSame('admin', $fresh->clusters->get('role'));
        $this->assertSame(30, $fresh->clusters->get('age'));
    }

    public function test_casting_returns_cluster_vo_from_json_string(): void
    {
        $model = TestCluster::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'clusters' => '{"status":"active","role":"doctor","age":25}',
        ]);

        $fresh = TestCluster::find($model->id);

        $this->assertInstanceOf(ClusterVO::class, $fresh->clusters);
        $this->assertSame('active', $fresh->clusters->get('status'));
        $this->assertSame('doctor', $fresh->clusters->get('role'));
        $this->assertSame(25, $fresh->clusters->get('age'));
    }

    public function test_casting_returns_cluster_vo_from_cluster_vo_instance(): void
    {
        $cluster = new ClusterVO(['status' => 'active', 'role' => 'admin']);

        $model = TestCluster::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'clusters' => $cluster,
        ]);

        $fresh = TestCluster::find($model->id);

        $this->assertInstanceOf(ClusterVO::class, $fresh->clusters);
        $this->assertSame('active', $fresh->clusters->get('status'));
        $this->assertSame('admin', $fresh->clusters->get('role'));
        $this->assertNotSame($cluster, $fresh->clusters);
    }

    public function test_stores_array_as_json_in_database(): void
    {
        $model = TestCluster::create([
            'name' => 'Alice Wonder',
            'email' => 'alice@example.com',
            'clusters' => ['status' => 'active', 'role' => 'admin'],
        ]);

        $this->assertDatabaseHas('test_clusters', [
            'id' => $model->id,
            'name' => 'Alice Wonder',
            'email' => 'alice@example.com',
        ]);

        $raw = TestCluster::find($model->id)->getAttributes();
        $this->assertIsString($raw['clusters']);

        $decoded = json_decode($raw['clusters'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('active', $decoded['status']);
        $this->assertSame('admin', $decoded['role']);
    }

    public function test_stores_cluster_vo_as_json_in_database(): void
    {
        $cluster = new ClusterVO(['status' => 'active', 'role' => 'admin']);

        $model = TestCluster::create([
            'name' => 'Charlie Doe',
            'email' => 'charlie@example.com',
            'clusters' => $cluster,
        ]);

        $raw = TestCluster::find($model->id)->getAttributes();
        $this->assertIsString($raw['clusters']);

        $decoded = json_decode($raw['clusters'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('active', $decoded['status']);
        $this->assertSame('admin', $decoded['role']);
    }

    public function test_stores_json_string_preserved(): void
    {
        $json = '{"status":"active","role":"admin"}';

        $model = TestCluster::create([
            'name' => 'David Smith',
            'email' => 'david@example.com',
            'clusters' => $json,
        ]);

        $raw = TestCluster::find($model->id)->getAttributes();
        $this->assertSame($json, $raw['clusters']);
    }

    public function test_handles_null_value(): void
    {
        $model = TestCluster::create([
            'name' => 'Eva Green',
            'email' => 'eva@example.com',
            'clusters' => null,
        ]);

        $fresh = TestCluster::find($model->id);
        $this->assertNull($fresh->clusters);
        $this->assertNull($fresh->getAttributes()['clusters']);
    }

    public function test_handles_empty_array_as_null(): void
    {
        $model = TestCluster::create([
            'name' => 'Frank White',
            'email' => 'frank@example.com',
            'clusters' => [],
        ]);

        $fresh = TestCluster::find($model->id);
        $this->assertNull($fresh->clusters);
        $this->assertNull($fresh->getAttributes()['clusters']);
    }

    public function test_handles_empty_json_string_as_null(): void
    {
        $model = TestCluster::create([
            'name' => 'Grace Kelly',
            'email' => 'grace@example.com',
            'clusters' => '{}',
        ]);

        $fresh = TestCluster::find($model->id);
        $this->assertNull($fresh->clusters);
        $this->assertNull($fresh->getAttributes()['clusters']);
    }

    public function test_handles_nested_structures(): void
    {
        $data = [
            'status' => 'active',
            'user' => [
                'name' => 'John Doe',
                'age' => 30,
                'address' => [
                    'city' => 'Kinshasa',
                    'country' => 'RDC',
                ],
            ],
            'tags' => ['php', 'js', 'docker'],
            'settings' => [
                'theme' => 'dark',
                'notifications' => 'yes',
            ],
        ];

        $model = TestCluster::create([
            'name' => 'Henry Ford',
            'email' => 'henry@example.com',
            'clusters' => $data,
        ]);

        $fresh = TestCluster::find($model->id);

        $this->assertInstanceOf(ClusterVO::class, $fresh->clusters);
        $this->assertSame('active', $fresh->clusters->get('status'));
        $this->assertSame('John Doe', $fresh->clusters->get('user.name'));
        $this->assertSame('Kinshasa', $fresh->clusters->get('user.address.city'));
        $this->assertSame('yes', $fresh->clusters->get('tags_php'));
        $this->assertSame('dark', $fresh->clusters->get('settings.theme'));
        $this->assertSame('yes', $fresh->clusters->get('settings.notifications'));
    }

    public function test_handles_string_boolean_values(): void
    {
        $data = [
            'is_active' => 'yes',
            'is_deleted' => 'no',
            'is_verified' => 'yes',
            'age' => 30,
        ];

        $model = TestCluster::create([
            'name' => 'Ivy Wilson',
            'email' => 'ivy@example.com',
            'clusters' => $data,
        ]);

        $fresh = TestCluster::find($model->id);

        $this->assertInstanceOf(ClusterVO::class, $fresh->clusters);
        $this->assertSame('yes', $fresh->clusters->get('is_active'));
        $this->assertSame('no', $fresh->clusters->get('is_deleted'));
        $this->assertSame('yes', $fresh->clusters->get('is_verified'));
        $this->assertSame(30, $fresh->clusters->get('age'));
    }

    public function test_handles_boolean_values_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean values are not allowed');

        $data = [
            'is_active' => true,
            'is_deleted' => false,
            'is_verified' => true,
            'age' => 30,
        ];

        TestCluster::create([
            'name' => 'Ivy Wilson',
            'email' => 'ivy@example.com',
            'clusters' => $data,
        ]);
    }

    public function test_where_cluster_query_with_casted_column(): void
    {
        TestCluster::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'clusters' => ['status' => 'active', 'role' => 'admin'],
        ]);

        TestCluster::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'clusters' => ['status' => 'inactive', 'role' => 'doctor'],
        ]);

        TestCluster::create([
            'name' => 'User 3',
            'email' => 'user3@example.com',
            'clusters' => ['status' => 'active', 'role' => 'doctor'],
        ]);

        $results = TestCluster::whereCluster('clusters', 'status=active')->get();

        $this->assertCount(2, $results);
        $this->assertContains('User 1', $results->pluck('name')->toArray());
        $this->assertContains('User 3', $results->pluck('name')->toArray());
        $this->assertNotContains('User 2', $results->pluck('name')->toArray());
    }

    public function test_where_cluster_query_with_complex_conditions(): void
    {
        TestCluster::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'clusters' => ['status' => 'active', 'role' => 'admin', 'age' => 30],
        ]);

        TestCluster::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'clusters' => ['status' => 'active', 'role' => 'admin', 'age' => 25],
        ]);

        TestCluster::create([
            'name' => 'User 3',
            'email' => 'user3@example.com',
            'clusters' => ['status' => 'active', 'role' => 'doctor', 'age' => 30],
        ]);

        $results = TestCluster::whereCluster('clusters', 'status=active & role=admin & age>=30')->get();

        $this->assertCount(1, $results);
        $this->assertSame('User 1', $results->first()->name);
    }

    public function test_where_cluster_query_with_or_condition(): void
    {
        TestCluster::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'clusters' => ['status' => 'active', 'role' => 'admin'],
        ]);

        TestCluster::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'clusters' => ['status' => 'pending', 'role' => 'guest'],
        ]);

        TestCluster::create([
            'name' => 'User 3',
            'email' => 'user3@example.com',
            'clusters' => ['status' => 'active', 'role' => 'doctor'],
        ]);

        $results = TestCluster::whereCluster('clusters', 'status=active | status=pending')->get();

        $this->assertCount(3, $results);
    }

    public function test_where_cluster_query_with_sub_condition(): void
    {
        TestCluster::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'clusters' => [
                'status' => 'active',
                'addresses' => [
                    ['city' => 'Kinshasa', 'country' => 'RDC'],
                    ['city' => 'Paris', 'country' => 'France'],
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'clusters' => [
                'status' => 'active',
                'addresses' => [
                    ['city' => 'Paris', 'country' => 'France'],
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'User 3',
            'email' => 'user3@example.com',
            'clusters' => [
                'status' => 'active',
                'addresses' => [
                    ['city' => 'London', 'country' => 'UK'],
                ],
            ],
        ]);

        $results = TestCluster::whereCluster('clusters', 'addresses[city=Kinshasa]')->get();

        $this->assertCount(1, $results);
        $this->assertSame('User 1', $results->first()->name);
    }

    public function test_where_cluster_query_with_aggregate_function(): void
    {
        TestCluster::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'clusters' => [
                'status' => 'active',
                'scores' => [80, 90, 85],
                'addresses' => ['a', 'b', 'c'],
            ],
        ]);

        TestCluster::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'clusters' => [
                'status' => 'active',
                'scores' => [70, 75, 80],
                'addresses' => ['a', 'b'],
            ],
        ]);

        TestCluster::create([
            'name' => 'User 3',
            'email' => 'user3@example.com',
            'clusters' => [
                'status' => 'active',
                'scores' => [95, 98, 92],
                'addresses' => ['a', 'b', 'c', 'd'],
            ],
        ]);

        $results = TestCluster::whereCluster('clusters', 'COUNT(addresses) > 2')->get();

        $this->assertCount(2, $results);
        $this->assertContains('User 1', $results->pluck('name')->toArray());
        $this->assertContains('User 3', $results->pluck('name')->toArray());
        $this->assertNotContains('User 2', $results->pluck('name')->toArray());

        $results = TestCluster::whereCluster('clusters', 'AVG(scores) >= 85')->get();

        $this->assertCount(2, $results);
        $this->assertContains('User 1', $results->pluck('name')->toArray());
        $this->assertContains('User 3', $results->pluck('name')->toArray());
        $this->assertNotContains('User 2', $results->pluck('name')->toArray());
    }

    public function test_update_cluster_via_array(): void
    {
        $model = TestCluster::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'clusters' => ['status' => 'active', 'role' => 'admin'],
        ]);

        $model->clusters = ['status' => 'inactive', 'role' => 'doctor'];
        $model->save();

        $fresh = TestCluster::find($model->id);
        $this->assertSame('inactive', $fresh->clusters->get('status'));
        $this->assertSame('doctor', $fresh->clusters->get('role'));
    }

    public function test_update_cluster_via_cluster_vo(): void
    {
        $model = TestCluster::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'clusters' => ['status' => 'active', 'role' => 'admin'],
        ]);

        $cluster = new ClusterVO(['status' => 'inactive', 'role' => 'doctor']);
        $model->clusters = $cluster;
        $model->save();

        $fresh = TestCluster::find($model->id);
        $this->assertSame('inactive', $fresh->clusters->get('status'));
        $this->assertSame('doctor', $fresh->clusters->get('role'));
    }

    public function test_update_specific_cluster_value(): void
    {
        $model = TestCluster::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'clusters' => ['status' => 'active', 'role' => 'admin', 'age' => 30],
        ]);

        $cluster = $model->clusters;
        $cluster = new ClusterVO(array_merge($cluster->toArray(), ['age' => 35]));
        $model->clusters = $cluster;
        $model->save();

        $fresh = TestCluster::find($model->id);
        $this->assertSame('active', $fresh->clusters->get('status'));
        $this->assertSame('admin', $fresh->clusters->get('role'));
        $this->assertSame(35, $fresh->clusters->get('age'));
    }

    public function test_handles_multiple_records_with_different_clusters(): void
    {
        TestCluster::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'clusters' => ['status' => 'active', 'role' => 'admin'],
        ]);

        TestCluster::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'clusters' => ['status' => 'inactive', 'role' => 'doctor'],
        ]);

        TestCluster::create([
            'name' => 'User 3',
            'email' => 'user3@example.com',
            'clusters' => ['status' => 'active', 'role' => 'doctor'],
        ]);

        $all = TestCluster::all();
        $this->assertCount(3, $all);

        foreach ($all as $record) {
            $this->assertInstanceOf(ClusterVO::class, $record->clusters);
            $this->assertTrue($record->clusters->has('status'));
            $this->assertTrue($record->clusters->has('role'));
        }

        $active = TestCluster::whereCluster('clusters', 'status=active')->get();
        $this->assertCount(2, $active);

        $activeAdmins = TestCluster::whereCluster('clusters', 'status=active & role=admin')->get();
        $this->assertCount(1, $activeAdmins);
        $this->assertSame('User 1', $activeAdmins->first()->name);
    }

    public function test_cast_performance_with_many_records(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            TestCluster::create([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'clusters' => [
                    'status' => $i % 2 === 0 ? 'active' : 'inactive',
                    'role' => $i % 3 === 0 ? 'admin' : 'doctor',
                    'age' => 20 + $i,
                ],
            ]);
        }

        $start = microtime(true);

        $all = TestCluster::all();

        foreach ($all as $record) {
            $this->assertInstanceOf(ClusterVO::class, $record->clusters);
            $this->assertTrue($record->clusters->has('status'));
            $this->assertTrue($record->clusters->has('role'));
            $this->assertTrue($record->clusters->has('age'));
        }

        $end = microtime(true);
        $time = ($end - $start) * 1000;

        $this->assertLessThan(100, $time);
    }
}
