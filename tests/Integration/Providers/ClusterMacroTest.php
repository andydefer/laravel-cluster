<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\Providers;

use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\DB;

final class ClusterMacroTest extends IntegrationTestCase
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
                    'verified' => 'true',
                    'addresses' => [
                        ['city' => 'Kinshasa', 'country' => 'RDC'],
                        ['city' => 'Paris', 'country' => 'France'],
                    ],
                    'tags' => ['php', 'js', 'docker'],
                ],
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'clusters' => [
                    'status' => 'inactive',
                    'role' => 'doctor',
                    'age' => 25,
                    'verified' => 'false',
                    'addresses' => [
                        ['city' => 'Paris', 'country' => 'France'],
                    ],
                    'tags' => ['python', 'react'],
                ],
            ],
            [
                'name' => 'Bob Johnson',
                'email' => 'bob@example.com',
                'clusters' => [
                    'status' => 'active',
                    'role' => 'doctor',
                    'age' => 35,
                    'verified' => 'true',
                    'addresses' => [
                        ['city' => 'Kinshasa', 'country' => 'RDC'],
                        ['city' => 'London', 'country' => 'UK'],
                        ['city' => 'Paris', 'country' => 'France'],
                    ],
                    'tags' => ['php', 'laravel', 'vuejs'],
                ],
            ],
            [
                'name' => 'Alice Wonder',
                'email' => 'alice@example.com',
                'clusters' => [
                    'status' => 'pending',
                    'role' => 'guest',
                    'age' => 28,
                    'verified' => 'false',
                    'addresses' => [],
                    'tags' => ['go', 'rust'],
                ],
            ],
        ];

        foreach ($data as $item) {
            TestCluster::create($item);
        }
    }

    // ==================== TESTS SUR BUILDER ====================

    public function test_where_cluster_on_builder_simple_condition(): void
    {
        $result = TestCluster::query()
            ->whereCluster($this->column, 'status=active')
            ->get();

        $this->assertCount(2, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    public function test_where_cluster_on_builder_with_and(): void
    {
        $result = TestCluster::query()
            ->whereCluster($this->column, 'status=active')
            ->whereCluster($this->column, 'role=admin')
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    public function test_where_cluster_on_builder_with_multiple_conditions(): void
    {
        // ✅ Chaîner les conditions (AND par défaut)
        $result = TestCluster::query()
            ->whereCluster($this->column, 'status=active')
            ->whereCluster($this->column, 'role=admin')
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    public function test_where_cluster_on_builder_with_comparison(): void
    {
        $result = TestCluster::query()
            ->whereCluster($this->column, 'age>30')
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Bob Johnson', $result->first()->name);
    }

    public function test_where_cluster_on_builder_with_like(): void
    {
        $result = TestCluster::query()
            ->whereCluster($this->column, 'name=~John%')
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    public function test_where_cluster_on_builder_with_subcondition(): void
    {
        $result = TestCluster::query()
            ->whereCluster($this->column, 'addresses[city=Kinshasa]')
            ->get();

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_where_cluster_on_builder_with_parentheses(): void
    {
        $result = TestCluster::query()
            ->whereCluster($this->column, '(status=active | status=pending) & role=admin')
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    public function test_where_cluster_on_builder_with_complex_expression(): void
    {
        $result = TestCluster::query()
            ->whereCluster(
                $this->column,
                '(status=active | status=pending) & (role=admin | role=doctor) & age>=25'
            )
            ->get();

        // ✅ John Doe et Bob Johnson → 2
        $this->assertCount(2, $result);
    }

    public function test_where_cluster_on_builder_with_not(): void
    {
        $result = TestCluster::query()
            ->whereCluster($this->column, 'status=active')
            ->whereCluster($this->column, 'role!=admin')
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Bob Johnson', $result->first()->name);
    }

    public function test_where_cluster_on_builder_with_array_exists(): void
    {
        // ✅ Utiliser addresses[] pour vérifier que le tableau n'est pas vide
        $result = TestCluster::query()
            ->whereCluster($this->column, 'addresses[]')
            ->get();

        $this->assertCount(3, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_cluster_on_builder_chained_with_eloquent_conditions(): void
    {
        $result = TestCluster::query()
            ->where('name', 'like', '%Doe%')
            ->whereCluster($this->column, 'status=active')
            ->whereCluster($this->column, 'role=admin')
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    // ==================== TESTS SUR MODEL ====================

    public function test_where_cluster_on_model_simple_condition(): void
    {
        $result = TestCluster::whereCluster($this->column, 'status=active')->get();

        $this->assertCount(2, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    public function test_where_cluster_on_model_with_and(): void
    {
        $result = TestCluster::whereCluster($this->column, 'status=active')
            ->whereCluster($this->column, 'role=admin')
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    public function test_where_cluster_on_model_with_subcondition(): void
    {
        $result = TestCluster::whereCluster($this->column, 'addresses[city=Kinshasa]')
            ->get();

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_where_cluster_on_model_chained_with_eloquent_conditions(): void
    {
        $result = TestCluster::where('name', 'like', '%Doe%')
            ->whereCluster($this->column, 'status=active')
            ->whereCluster($this->column, 'role=admin')
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->name);
    }

    // ==================== TESTS DE DRIVER ====================

    public function test_where_cluster_detects_driver_automatically(): void
    {

        $driverName = DB::connection()->getDriverName();

        $this->assertContains($driverName, ['sqlite', 'mysql', 'pgsql']);

        $result = TestCluster::whereCluster($this->column, 'status=active')->get();

        $this->assertCount(2, $result);
    }

    // ==================== TESTS D'AGRÉGATIONS ====================

    public function test_where_cluster_throws_exception_for_aggregations(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid character "{"');

        TestCluster::whereCluster($this->column, '{COUNT(addresses) > 2}')->get();
    }

    // ==================== EDGE CASES ====================

    public function test_where_cluster_with_empty_result(): void
    {
        $result = TestCluster::whereCluster($this->column, 'status=super_admin')->get();

        $this->assertCount(0, $result);
    }

    public function test_where_cluster_with_non_existent_json_key(): void
    {
        // ✅ La colonne 'clusters' existe, mais la clé 'non_existent' n'existe pas dans le JSON
        $result = TestCluster::whereCluster($this->column, 'non_existent=active')->get();

        $this->assertCount(0, $result);
    }
    // ==================== TEST DE PERFORMANCE ====================

    public function test_where_cluster_generates_valid_sql(): void
    {
        $query = TestCluster::whereCluster($this->column, 'status=active');
        $sql = $query->toSql();

        $driverName = DB::connection()->getDriverName();
        if ($driverName === 'sqlite') {
            $this->assertStringContainsString('json_extract', $sql);
        }

        $result = $query->get();
        $this->assertCount(2, $result);
    }
}
