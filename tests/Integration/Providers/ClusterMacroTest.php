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
                    'verified' => 'yes',
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
                    'verified' => 'no',
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
                    'verified' => 'yes',
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
                    'verified' => 'no',
                    'addresses' => [],
                    'tags' => ['go', 'rust'],
                ],
            ],
        ];

        foreach ($data as $item) {
            TestCluster::create($item);
        }
    }

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

    public function test_where_cluster_detects_driver_automatically(): void
    {
        $driverName = DB::connection()->getDriverName();

        $this->assertContains($driverName, ['sqlite', 'mysql', 'pgsql']);

        $result = TestCluster::whereCluster($this->column, 'status=active')->get();

        $this->assertCount(2, $result);
    }

    public function test_where_cluster_throws_exception_for_aggregations(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid character "{"');

        TestCluster::whereCluster($this->column, '{COUNT(addresses) > 2}')->get();
    }

    public function test_where_cluster_with_empty_result(): void
    {
        $result = TestCluster::whereCluster($this->column, 'status=super_admin')->get();

        $this->assertCount(0, $result);
    }

    public function test_where_cluster_with_non_existent_json_key(): void
    {
        $result = TestCluster::whereCluster($this->column, 'non_existent=active')->get();

        $this->assertCount(0, $result);
    }

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

    public function test_where_cluster_with_dot_notation_simple(): void
    {
        TestCluster::truncate();

        TestCluster::create([
            'name' => 'Dot Test User',
            'email' => 'dot@example.com',
            'clusters' => [
                'profile' => [
                    'is_verified' => 'yes',
                    'years_experience' => 5,
                ],
                'settings' => [
                    'theme' => 'dark',
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'Dot Test User 2',
            'email' => 'dot2@example.com',
            'clusters' => [
                'profile' => [
                    'is_verified' => 'no',
                    'years_experience' => 3,
                ],
                'settings' => [
                    'theme' => 'light',
                ],
            ],
        ]);

        $result = TestCluster::whereCluster($this->column, 'profile.is_verified=yes')->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Dot Test User', $result->first()->name);
    }

    public function test_where_cluster_with_dot_notation_and_condition(): void
    {
        TestCluster::truncate();

        TestCluster::create([
            'name' => 'Dot Test User 1',
            'email' => 'dot1@example.com',
            'clusters' => [
                'profile' => [
                    'is_verified' => 'yes',
                    'years_experience' => 5,
                ],
                'settings' => [
                    'theme' => 'dark',
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'Dot Test User 2',
            'email' => 'dot2@example.com',
            'clusters' => [
                'profile' => [
                    'is_verified' => 'yes',
                    'years_experience' => 3,
                ],
                'settings' => [
                    'theme' => 'light',
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'Dot Test User 3',
            'email' => 'dot3@example.com',
            'clusters' => [
                'profile' => [
                    'is_verified' => 'no',
                    'years_experience' => 5,
                ],
                'settings' => [
                    'theme' => 'dark',
                ],
            ],
        ]);

        $result = TestCluster::whereCluster($this->column, 'profile.is_verified=yes & profile.years_experience>3')->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Dot Test User 1', $result->first()->name);
    }

    public function test_where_cluster_with_dot_notation_deep(): void
    {
        TestCluster::truncate();

        TestCluster::create([
            'name' => 'Deep Dot User',
            'email' => 'deep@example.com',
            'clusters' => [
                'user' => [
                    'profile' => [
                        'address' => [
                            'city' => 'Kinshasa',
                            'country' => 'RDC',
                        ],
                    ],
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'Deep Dot User 2',
            'email' => 'deep2@example.com',
            'clusters' => [
                'user' => [
                    'profile' => [
                        'address' => [
                            'city' => 'Paris',
                            'country' => 'France',
                        ],
                    ],
                ],
            ],
        ]);

        $result = TestCluster::whereCluster($this->column, 'user.profile.address.city=Kinshasa')->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Deep Dot User', $result->first()->name);
    }

    public function test_where_cluster_with_dot_notation_and_numeric_operator(): void
    {
        TestCluster::truncate();

        TestCluster::create([
            'name' => 'Numeric Dot User',
            'email' => 'numeric@example.com',
            'clusters' => [
                'profile' => [
                    'years_experience' => 5,
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'Numeric Dot User 2',
            'email' => 'numeric2@example.com',
            'clusters' => [
                'profile' => [
                    'years_experience' => 3,
                ],
            ],
        ]);

        $result = TestCluster::whereCluster($this->column, 'profile.years_experience>3')->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Numeric Dot User', $result->first()->name);
    }

    public function test_where_cluster_with_dot_notation_and_like(): void
    {
        TestCluster::truncate();

        TestCluster::create([
            'name' => 'Like Dot User',
            'email' => 'like@example.com',
            'clusters' => [
                'profile' => [
                    'name' => 'John Doe',
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'Like Dot User 2',
            'email' => 'like2@example.com',
            'clusters' => [
                'profile' => [
                    'name' => 'Jane Smith',
                ],
            ],
        ]);

        $result = TestCluster::whereCluster($this->column, 'profile.name=~John%')->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Like Dot User', $result->first()->name);
    }

    public function test_where_cluster_with_dot_notation_exists(): void
    {
        TestCluster::truncate();

        TestCluster::create([
            'name' => 'Exists Dot User',
            'email' => 'exists@example.com',
            'clusters' => [
                'profile' => [
                    'verified' => 'yes',
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'Exists Dot User 2',
            'email' => 'exists2@example.com',
            'clusters' => [
                'status' => 'active',
            ],
        ]);

        $result = TestCluster::whereCluster($this->column, '*profile.verified')->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Exists Dot User', $result->first()->name);
    }

    public function test_where_cluster_with_dot_notation_not_exists(): void
    {
        TestCluster::truncate();

        TestCluster::create([
            'name' => 'Not Exists Dot User',
            'email' => 'notexists@example.com',
            'clusters' => [
                'profile' => [
                    'name' => 'John',
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'Not Exists Dot User 2',
            'email' => 'notexists2@example.com',
            'clusters' => [
                'profile' => [
                    'verified' => 'yes',
                ],
            ],
        ]);

        $result = TestCluster::whereCluster($this->column, '#profile.verified')->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Not Exists Dot User', $result->first()->name);
    }

    public function test_where_cluster_with_dot_notation_combined_conditions(): void
    {
        TestCluster::truncate();

        TestCluster::create([
            'name' => 'Combined Dot User 1',
            'email' => 'combined1@example.com',
            'clusters' => [
                'profile' => [
                    'is_verified' => 'yes',
                    'years_experience' => 5,
                ],
                'settings' => [
                    'theme' => 'dark',
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'Combined Dot User 2',
            'email' => 'combined2@example.com',
            'clusters' => [
                'profile' => [
                    'is_verified' => 'yes',
                    'years_experience' => 3,
                ],
                'settings' => [
                    'theme' => 'dark',
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'Combined Dot User 3',
            'email' => 'combined3@example.com',
            'clusters' => [
                'profile' => [
                    'is_verified' => 'no',
                    'years_experience' => 5,
                ],
                'settings' => [
                    'theme' => 'dark',
                ],
            ],
        ]);

        $result = TestCluster::whereCluster(
            $this->column,
            'profile.is_verified=yes & profile.years_experience>3 & settings.theme=dark'
        )->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Combined Dot User 1', $result->first()->name);
    }
}
