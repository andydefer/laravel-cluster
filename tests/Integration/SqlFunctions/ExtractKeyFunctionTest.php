<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\SqlFunctions;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\SqlFunctions\ExtractKeyFunction;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\SqliteTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class ExtractKeyFunctionTest extends SqliteTestCase
{
    use RefreshDatabase;

    private const COLUMN = 'clusters';

    private ClusterQuery $clusterQuery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clusterQuery = new ClusterQuery;
    }

    // ==================== EXECUTE TESTS ====================

    public function test_extract_key_function_execute_with_valid_object(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'name' => 'Pharmacie A',
                'slug' => 'pharma-a',
            ],
        ];

        $result = $function->execute($data, ['slug', 'pharmacy']);

        $this->assertSame('pharma-a', $result);
    }

    public function test_extract_key_function_execute_with_nested_object(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'drug' => [
                'name' => 'Paracétamol',
                'slug' => 'paracetamol',
            ],
        ];

        $result = $function->execute($data, ['slug', 'drug']);

        $this->assertSame('paracetamol', $result);
    }

    public function test_extract_key_function_execute_with_missing_key(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'name' => 'Pharmacie A',
            ],
        ];

        $result = $function->execute($data, ['slug', 'pharmacy']);

        $this->assertNull($result);
    }

    public function test_extract_key_function_execute_with_missing_object(): void
    {
        $function = new ExtractKeyFunction;

        $data = [];

        $result = $function->execute($data, ['slug', 'pharmacy']);

        $this->assertNull($result);
    }

    public function test_extract_key_function_execute_with_null_value(): void
    {
        $function = new ExtractKeyFunction;

        $result = $function->execute(null, ['slug', 'pharmacy']);

        $this->assertNull($result);
    }

    public function test_extract_key_function_execute_with_invalid_args(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'slug' => 'pharma-a',
            ],
        ];

        $result = $function->execute($data, []);

        $this->assertNull($result);
    }

    public function test_extract_key_function_execute_with_only_one_arg(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'slug' => 'pharma-a',
            ],
        ];

        $result = $function->execute($data, ['slug']);

        $this->assertNull($result);
    }

    // ==================== TO SQL TESTS ====================

    public function test_extract_key_function_to_sql_sqlite(): void
    {
        $function = new ExtractKeyFunction;

        $sql = $function->toSql(self::COLUMN, 'pharmacy', DatabaseDriver::SQLITE, ['slug', 'pharmacy']);

        $this->assertSame(
            "json_extract(clusters, '$.pharmacy.slug')",
            $sql
        );
    }

    public function test_extract_key_function_to_sql_mysql(): void
    {
        $function = new ExtractKeyFunction;

        $sql = $function->toSql(self::COLUMN, 'pharmacy', DatabaseDriver::MYSQL, ['slug', 'pharmacy']);

        $this->assertSame(
            "JSON_EXTRACT(clusters, '$.pharmacy.slug')",
            $sql
        );
    }

    public function test_extract_key_function_to_sql_pgsql(): void
    {
        $function = new ExtractKeyFunction;

        $sql = $function->toSql(self::COLUMN, 'pharmacy', DatabaseDriver::PGSQL, ['slug', 'pharmacy']);

        $this->assertSame(
            "clusters->>'pharmacy.slug'",
            $sql
        );
    }

    public function test_extract_key_function_to_sql_with_different_key(): void
    {
        $function = new ExtractKeyFunction;

        $sql = $function->toSql(self::COLUMN, 'drug', DatabaseDriver::SQLITE, ['name', 'drug']);

        $this->assertSame(
            "json_extract(clusters, '$.drug.name')",
            $sql
        );
    }

    // ==================== INTEGRATION TESTS ====================
    public function test_extract_key_function_integration_with_cluster_query(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Offer 1',
            'pharmacy' => [
                'name' => 'Pharmacie A',
                'slug' => 'pharma-a',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Offer 2',
            'pharmacy' => [
                'name' => 'Pharmacie B',
                'slug' => 'pharma-b',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Offer 3',
            'pharmacy' => [
                'name' => 'Pharmacie A',
                'slug' => 'pharma-a',
            ],
        ]));

        $result = $this->clusterQuery->filter(
            $collection,
            'EXTRACT_KEY(slug, pharmacy) = pharma-a'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('Offer 1', $result->first()->get('name'));
        $this->assertEquals('Offer 3', $result->last()->get('name'));
    }

    public function test_extract_key_function_integration_with_different_key(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Offer 1',
            'drug' => [
                'name' => 'Paracétamol',
                'slug' => 'paracetamol',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Offer 2',
            'drug' => [
                'name' => 'Ibuprofène',
                'slug' => 'ibuprofene',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Offer 3',
            'drug' => [
                'name' => 'Paracétamol',
                'slug' => 'paracetamol',
            ],
        ]));

        $result = $this->clusterQuery->filter(
            $collection,
            'EXTRACT_KEY(slug, drug) = paracetamol'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('Offer 1', $result->first()->get('name'));
        $this->assertEquals('Offer 3', $result->last()->get('name'));
    }

    public function test_extract_key_function_with_other_conditions(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Offer 1',
            'pharmacy' => [
                'slug' => 'pharma-a',
            ],
            'status' => 'active',
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Offer 2',
            'pharmacy' => [
                'slug' => 'pharma-a',
            ],
            'status' => 'inactive',
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Offer 3',
            'pharmacy' => [
                'slug' => 'pharma-b',
            ],
            'status' => 'active',
        ]));

        $result = $this->clusterQuery->filter(
            $collection,
            'EXTRACT_KEY(slug, pharmacy) = pharma-a & status=active'
        );

        $this->assertCount(1, $result);
        $this->assertEquals('Offer 1', $result->first()->get('name'));
    }

    public function test_extract_key_function_with_or_condition(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Offer 1',
            'pharmacy' => [
                'slug' => 'pharma-a',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Offer 2',
            'pharmacy' => [
                'slug' => 'pharma-b',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Offer 3',
            'pharmacy' => [
                'slug' => 'pharma-c',
            ],
        ]));

        $result = $this->clusterQuery->filter(
            $collection,
            'EXTRACT_KEY(slug, pharmacy) = pharma-a | EXTRACT_KEY(slug, pharmacy) = pharma-b'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('Offer 1', $result->first()->get('name'));
        $this->assertEquals('Offer 2', $result->last()->get('name'));
    }

    public function test_extract_key_function_with_not_equal(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Offer 1',
            'pharmacy' => [
                'slug' => 'pharma-a',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Offer 2',
            'pharmacy' => [
                'slug' => 'pharma-b',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Offer 3',
            'pharmacy' => [
                'slug' => 'pharma-a',
            ],
        ]));

        $result = $this->clusterQuery->filter(
            $collection,
            'EXTRACT_KEY(slug, pharmacy) != pharma-a'
        );

        $this->assertCount(1, $result);
        $this->assertEquals('Offer 2', $result->first()->get('name'));
    }

    // ==================== ELOQUENT WHERECLUSTER TESTS ====================

    public function test_extract_key_function_with_eloquent_where_cluster(): void
    {
        TestCluster::create([
            'name' => 'Offer 1',
            'email' => 'offer1@example.com',
            'clusters' => [
                'pharmacy' => [
                    'name' => 'Pharmacie A',
                    'slug' => 'pharma-a',
                ],
                'status' => 'active',
            ],
        ]);

        TestCluster::create([
            'name' => 'Offer 2',
            'email' => 'offer2@example.com',
            'clusters' => [
                'pharmacy' => [
                    'name' => 'Pharmacie B',
                    'slug' => 'pharma-b',
                ],
                'status' => 'active',
            ],
        ]);

        TestCluster::create([
            'name' => 'Offer 3',
            'email' => 'offer3@example.com',
            'clusters' => [
                'pharmacy' => [
                    'name' => 'Pharmacie A',
                    'slug' => 'pharma-a',
                ],
                'status' => 'inactive',
            ],
        ]);

        $results = TestCluster::whereCluster('clusters', 'EXTRACT_KEY(slug, pharmacy) = pharma-a')->get();

        $this->assertCount(2, $results);
        $this->assertEquals('Offer 1', $results[0]->name);
        $this->assertEquals('Offer 3', $results[1]->name);
    }

    public function test_extract_key_function_with_drug_slug_eloquent(): void
    {
        TestCluster::create([
            'name' => 'Offer 1',
            'email' => 'offer1@example.com',
            'clusters' => [
                'drug' => [
                    'name' => 'Paracétamol',
                    'slug' => 'paracetamol',
                ],
                'status' => 'active',
            ],
        ]);

        TestCluster::create([
            'name' => 'Offer 2',
            'email' => 'offer2@example.com',
            'clusters' => [
                'drug' => [
                    'name' => 'Ibuprofène',
                    'slug' => 'ibuprofene',
                ],
                'status' => 'active',
            ],
        ]);

        TestCluster::create([
            'name' => 'Offer 3',
            'email' => 'offer3@example.com',
            'clusters' => [
                'drug' => [
                    'name' => 'Paracétamol',
                    'slug' => 'paracetamol',
                ],
                'status' => 'inactive',
            ],
        ]);

        $results = TestCluster::whereCluster('clusters', 'EXTRACT_KEY(slug, drug) = paracetamol')->get();

        $this->assertCount(2, $results);
        $this->assertEquals('Offer 1', $results[0]->name);
        $this->assertEquals('Offer 3', $results[1]->name);
    }

    // ==================== REGISTRY TESTS ====================

    public function test_extract_key_function_registered_in_registry(): void
    {
        $registry = new SqlFunctionRegistry;

        $this->assertTrue($registry->has('EXTRACT_KEY'));
        $this->assertInstanceOf(ExtractKeyFunction::class, $registry->get('EXTRACT_KEY'));
    }

    public function test_extract_key_function_to_sql_via_registry(): void
    {
        $registry = new SqlFunctionRegistry;

        $sql = $registry->toSql('EXTRACT_KEY', 'clusters', 'pharmacy', DatabaseDriver::SQLITE, ['slug', 'pharmacy']);

        $this->assertSame(
            "json_extract(clusters, '$.pharmacy.slug')",
            $sql
        );
    }

    public function test_extract_key_function_execute_via_registry(): void
    {
        $registry = new SqlFunctionRegistry;

        $data = [
            'pharmacy' => [
                'name' => 'Pharmacie A',
                'slug' => 'pharma-a',
            ],
        ];

        $result = $registry->execute('EXTRACT_KEY', $data, ['slug', 'pharmacy']);

        $this->assertSame('pharma-a', $result);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_extract_key_function_validate_args(): void
    {
        $function = new ExtractKeyFunction;

        $this->assertTrue($function->validateArgs(['slug', 'pharmacy']));
        $this->assertTrue($function->validateArgs(['name', 'drug']));

        $this->assertFalse($function->validateArgs(['slug']));
        $this->assertFalse($function->validateArgs(['slug', 'pharmacy', 'extra']));
        $this->assertFalse($function->validateArgs([]));
        $this->assertFalse($function->validateArgs(['', 'pharmacy']));
        $this->assertFalse($function->validateArgs(['slug', '']));
    }

    public function test_extract_key_function_get_min_max_args(): void
    {
        $function = new ExtractKeyFunction;

        $this->assertSame(2, $function->getMinArgs());
        $this->assertSame(2, $function->getMaxArgs());
    }

    public function test_extract_key_function_return_type(): void
    {
        $function = new ExtractKeyFunction;

        $this->assertSame('string', $function->getReturnType());
    }

    public function test_extract_key_function_default_value(): void
    {
        $function = new ExtractKeyFunction;

        $this->assertNull($function->getDefaultValue());
    }

    public function test_extract_key_function_get_name(): void
    {
        $function = new ExtractKeyFunction;

        $this->assertSame('EXTRACT_KEY', $function->getName());
    }

    // ==================== EDGE CASES ====================

    public function test_extract_key_function_with_empty_collection(): void
    {
        $collection = new ClusterVOCollection;

        $result = $this->clusterQuery->filter(
            $collection,
            'EXTRACT_KEY(slug, pharmacy) = pharma-a'
        );

        $this->assertCount(0, $result);
    }

    public function test_extract_key_function_with_nested_path(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Offer 1',
            'profile' => [
                'pharmacy' => [
                    'slug' => 'pharma-a',
                ],
            ],
        ]));

        $result = $this->clusterQuery->filter(
            $collection,
            'profile.pharmacy.slug = pharma-a'
        );

        $this->assertCount(1, $result);
    }

    // ==================== NESTED KEY TESTS ====================

    public function test_extract_key_function_execute_with_nested_key(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'profile' => [
                    'name' => 'Jean Dupont',
                    'email' => 'jean@example.com',
                ],
                'slug' => 'pharma-a',
            ],
        ];

        $result = $function->execute($data, ['profile.name', 'pharmacy']);

        $this->assertSame('Jean Dupont', $result);
    }

    public function test_extract_key_function_execute_with_deep_nested_key(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'profile' => [
                    'contact' => [
                        'phone' => '0612345678',
                    ],
                ],
            ],
        ];

        $result = $function->execute($data, ['profile.contact.phone', 'pharmacy']);

        $this->assertSame('0612345678', $result);
    }

    public function test_extract_key_function_integration_with_nested_key(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Offer 1',
            'pharmacy' => [
                'profile' => [
                    'name' => 'Jean Dupont',
                ],
                'slug' => 'pharma-a',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Offer 2',
            'pharmacy' => [
                'profile' => [
                    'name' => 'Marie Martin',
                ],
                'slug' => 'pharma-b',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Offer 3',
            'pharmacy' => [
                'profile' => [
                    'name' => 'Jean Dupont',
                ],
                'slug' => 'pharma-c',
            ],
        ]));

        $result = $this->clusterQuery->filter(
            $collection,
            'EXTRACT_KEY(profile.name, pharmacy) = "Jean Dupont"'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('Offer 1', $result->first()->get('name'));
        $this->assertEquals('Offer 3', $result->last()->get('name'));
    }

    public function test_extract_key_function_nested_with_eloquent(): void
    {
        TestCluster::create([
            'name' => 'Offer 1',
            'email' => 'offer1@example.com',
            'clusters' => [
                'pharmacy' => [
                    'profile' => [
                        'name' => 'Jean Dupont',
                    ],
                    'status' => 'active',
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'Offer 2',
            'email' => 'offer2@example.com',
            'clusters' => [
                'pharmacy' => [
                    'profile' => [
                        'name' => 'Marie Martin',
                    ],
                    'status' => 'active',
                ],
            ],
        ]);

        TestCluster::create([
            'name' => 'Offer 3',
            'email' => 'offer3@example.com',
            'clusters' => [
                'pharmacy' => [
                    'profile' => [
                        'name' => 'Jean Dupont',
                    ],
                    'status' => 'inactive',
                ],
            ],
        ]);

        $results = TestCluster::whereCluster('clusters', 'EXTRACT_KEY(profile.name, pharmacy) = "Jean Dupont"')->get();

        $this->assertCount(2, $results);
        $this->assertEquals('Offer 1', $results[0]->name);
        $this->assertEquals('Offer 3', $results[1]->name);

        // ✅ Correction : status est dans pharmacy.status
        $results = TestCluster::whereCluster('clusters', 'EXTRACT_KEY(profile.name, pharmacy) = "Jean Dupont" & pharmacy.status=active')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Offer 1', $results[0]->name);
    }
}
