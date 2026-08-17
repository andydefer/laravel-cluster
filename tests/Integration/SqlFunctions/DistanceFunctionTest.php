<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\SqlFunctions;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\SqlFunctions\DistanceFunction;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\SqliteTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\PhpVo\ValueObjects\CoordinatesVO;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class DistanceFunctionTest extends SqliteTestCase
{
    use RefreshDatabase;

    private const COLUMN = 'clusters';

    private const PATH = 'coordinates';

    private ClusterQuery $clusterQuery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clusterQuery = new ClusterQuery;
    }

    // ==================== EXECUTE TESTS ====================

    public function test_distance_function_execute_with_coordinates(): void
    {
        $function = new DistanceFunction;

        $coords = new CoordinatesVO(
            FloatVO::from(48.8566),
            FloatVO::from(2.3522)
        );

        $result = $function->execute($coords, ['coordinates', 45.7640, 4.8357]);

        $this->assertTrue(is_float($result) || is_string($result));
        $distance = (float) $result;
        $this->assertGreaterThan(0, $distance);
        $this->assertGreaterThan(391000, $distance);
        $this->assertLessThan(392000, $distance);
    }

    public function test_distance_function_execute_with_coordinates_in_kilometers(): void
    {
        $function = new DistanceFunction;

        $coords = new CoordinatesVO(
            FloatVO::from(48.8566),
            FloatVO::from(2.3522)
        );

        $result = $function->execute($coords, ['coordinates', 45.7640, 4.8357, 'km']);

        $this->assertTrue(is_float($result) || is_string($result));
        $distance = (float) $result;
        $this->assertGreaterThan(0, $distance);
        $this->assertGreaterThan(391, $distance);
        $this->assertLessThan(392, $distance);
    }

    public function test_distance_function_execute_with_same_coordinates(): void
    {
        $function = new DistanceFunction;

        $coords = new CoordinatesVO(
            FloatVO::from(48.8566),
            FloatVO::from(2.3522)
        );

        $result = $function->execute($coords, ['coordinates', 48.8566, 2.3522]);

        $this->assertTrue(is_float($result) || is_string($result));
        $this->assertEquals(0.0, (float) $result);
    }

    public function test_distance_function_execute_with_invalid_value(): void
    {
        $function = new DistanceFunction;

        $result = $function->execute('not a coordinate', ['coordinates', 48.8566, 2.3522]);

        $this->assertTrue(is_float($result) || is_string($result));
        $this->assertEquals(0.0, (float) $result);
    }

    public function test_distance_function_execute_with_null(): void
    {
        $function = new DistanceFunction;

        $result = $function->execute(null, ['coordinates', 48.8566, 2.3522]);

        $this->assertTrue(is_float($result) || is_string($result));
        $this->assertEquals(0.0, (float) $result);
    }

    // ==================== TO SQL TESTS ====================

    public function test_distance_function_to_sql_sqlite(): void
    {
        $function = new DistanceFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::SQLITE, ['coordinates', 48.8566, 2.3522]);

        $this->assertStringContainsString('json_extract(clusters, \'$."coordinates".latitude\')', $sql);
        $this->assertStringContainsString('json_extract(clusters, \'$."coordinates".longitude\')', $sql);
        $this->assertStringContainsString('6371000', $sql);
    }

    public function test_distance_function_to_sql_mysql(): void
    {
        $function = new DistanceFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::MYSQL, ['coordinates', 48.8566, 2.3522]);

        $this->assertStringContainsString('JSON_EXTRACT(clusters, \'$."coordinates".latitude\')', $sql);
        $this->assertStringContainsString('JSON_EXTRACT(clusters, \'$."coordinates".longitude\')', $sql);
        $this->assertStringContainsString('6371000', $sql);
    }

    public function test_distance_function_to_sql_pgsql(): void
    {
        $function = new DistanceFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::PGSQL, ['coordinates', 48.8566, 2.3522]);

        $this->assertStringContainsString('clusters->\'coordinates\'->>\'latitude\'', $sql);
        $this->assertStringContainsString('clusters->\'coordinates\'->>\'longitude\'', $sql);
        $this->assertStringContainsString('6371000', $sql);
    }

    public function test_distance_function_to_sql_sqlite_with_km_unit(): void
    {
        $function = new DistanceFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::SQLITE, ['coordinates', 48.8566, 2.3522, 'km']);

        $this->assertStringContainsString('6371', $sql);
        $this->assertStringNotContainsString('6371000', $sql);
    }

    public function test_distance_function_to_sql_sqlite_with_m_unit(): void
    {
        $function = new DistanceFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::SQLITE, ['coordinates', 48.8566, 2.3522, 'm']);

        $this->assertStringContainsString('6371000', $sql);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_distance_function_validate_args(): void
    {
        $function = new DistanceFunction;

        $this->assertTrue($function->validateArgs(['coordinates', 48.8566, 2.3522]));
        $this->assertTrue($function->validateArgs(['coordinates', 48.8566, 2.3522, 'km']));
        $this->assertTrue($function->validateArgs(['coordinates', 48.8566, 2.3522, 'm']));

        $this->assertFalse($function->validateArgs(['coordinates', 48.8566]));
        $this->assertFalse($function->validateArgs(['coordinates']));
        $this->assertFalse($function->validateArgs([]));
        $this->assertFalse($function->validateArgs(['coordinates', 48.8566, 2.3522, 'invalid']));
    }

    // ==================== GETTER TESTS ====================

    public function test_distance_function_get_name(): void
    {
        $function = new DistanceFunction;
        $this->assertSame('DISTANCE', $function->getName());
    }

    public function test_distance_function_get_return_type(): void
    {
        $function = new DistanceFunction;
        $this->assertSame('float', $function->getReturnType());
    }

    public function test_distance_function_get_default_value(): void
    {
        $function = new DistanceFunction;
        $this->assertSame('0', $function->getDefaultValue());
    }

    public function test_distance_function_get_min_args(): void
    {
        $function = new DistanceFunction;
        $this->assertSame(3, $function->getMinArgs());
    }

    public function test_distance_function_get_max_args(): void
    {
        $function = new DistanceFunction;
        $this->assertSame(4, $function->getMaxArgs());
    }

    // ==================== INTEGRATION WITH CLUSTER QUERY TESTS ====================

    public function test_distance_function_integration_with_cluster_query(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Paris Pharmacy',
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Lyon Pharmacy',
            'coordinates' => [
                'latitude' => 45.7640,
                'longitude' => 4.8357,
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Marseille Pharmacy',
            'coordinates' => [
                'latitude' => 43.2965,
                'longitude' => 5.3698,
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, 'DISTANCE(coordinates, 48.8566, 2.3522, km) < 500');

        $resultNames = [];
        foreach ($result as $item) {
            $resultNames[] = $item->get('name');
        }

        $this->assertCount(2, $result, 'Résultats trouvés: '.implode(', ', $resultNames));
        $this->assertEquals('Paris Pharmacy', $result->first()->get('name'));
        $this->assertEquals('Lyon Pharmacy', $result->last()->get('name'));
    }

    public function test_distance_function_integration_with_other_conditions(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Paris Pharmacy',
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
            'status' => 'active',
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Lyon Pharmacy',
            'coordinates' => [
                'latitude' => 45.7640,
                'longitude' => 4.8357,
            ],
            'status' => 'inactive',
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Marseille Pharmacy',
            'coordinates' => [
                'latitude' => 43.2965,
                'longitude' => 5.3698,
            ],
            'status' => 'active',
        ]));

        $result = $this->clusterQuery->filter(
            $collection,
            'DISTANCE(coordinates, 48.8566, 2.3522, km) < 500 & status=active'
        );

        $resultNames = [];
        foreach ($result as $item) {
            $resultNames[] = $item->get('name').' (status: '.$item->get('status').')';
        }

        $this->assertCount(1, $result, 'Résultats trouvés: '.implode(', ', $resultNames));
        $this->assertEquals('Paris Pharmacy', $result->first()->get('name'));
    }

    public function test_distance_function_with_no_matches(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Paris Pharmacy',
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Lyon Pharmacy',
            'coordinates' => [
                'latitude' => 45.7640,
                'longitude' => 4.8357,
            ],
        ]));

        $result = $this->clusterQuery->filter(
            $collection,
            'DISTANCE(coordinates, 48.8566, 2.3522, km) < 1 & name != "Paris Pharmacy"'
        );

        $resultNames = [];
        foreach ($result as $item) {
            $resultNames[] = $item->get('name');
        }

        $this->assertCount(0, $result, 'Résultats trouvés: '.implode(', ', $resultNames));
    }

    public function test_distance_function_with_empty_collection(): void
    {
        $collection = new ClusterVOCollection;

        $result = $this->clusterQuery->filter($collection, 'DISTANCE(coordinates, 48.8566, 2.3522, km) < 500');

        $this->assertCount(0, $result);
    }

    // ==================== ELOQUENT WHERECLUSTER TESTS ====================

    public function test_distance_function_with_eloquent_where_cluster(): void
    {
        TestCluster::create([
            'name' => 'Paris Pharmacy',
            'email' => 'paris@example.com',
            'clusters' => [
                'coordinates' => [
                    'latitude' => 48.8566,
                    'longitude' => 2.3522,
                ],
                'status' => 'active',
            ],
        ]);

        TestCluster::create([
            'name' => 'Lyon Pharmacy',
            'email' => 'lyon@example.com',
            'clusters' => [
                'coordinates' => [
                    'latitude' => 45.7640,
                    'longitude' => 4.8357,
                ],
                'status' => 'active',
            ],
        ]);

        TestCluster::create([
            'name' => 'Marseille Pharmacy',
            'email' => 'marseille@example.com',
            'clusters' => [
                'coordinates' => [
                    'latitude' => 43.2965,
                    'longitude' => 5.3698,
                ],
                'status' => 'active',
            ],
        ]);

        $results = TestCluster::whereCluster('clusters', 'DISTANCE(coordinates, 48.8566, 2.3522) < 500000')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('Paris Pharmacy', $results[0]->name);
        $this->assertEquals('Lyon Pharmacy', $results[1]->name);

        $results = TestCluster::whereCluster('clusters', 'DISTANCE(coordinates, 48.8566, 2.3522, km) < 500')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('Paris Pharmacy', $results[0]->name);
        $this->assertEquals('Lyon Pharmacy', $results[1]->name);

        $results = TestCluster::whereCluster('clusters', 'DISTANCE(coordinates, 48.8566, 2.3522, km) < 100')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Paris Pharmacy', $results[0]->name);

        $results = TestCluster::whereCluster(
            'clusters',
            'DISTANCE(coordinates, 48.8566, 2.3522, km) > 300 & DISTANCE(coordinates, 48.8566, 2.3522, km) < 500'
        )->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Lyon Pharmacy', $results[0]->name);
    }

    public function test_distance_function_with_eloquent_where_cluster_and_other_conditions(): void
    {
        TestCluster::create([
            'name' => 'Paris Pharmacy',
            'email' => 'paris@example.com',
            'clusters' => [
                'coordinates' => [
                    'latitude' => 48.8566,
                    'longitude' => 2.3522,
                ],
                'status' => 'active',
                'is_verified' => 'yes',
            ],
        ]);

        TestCluster::create([
            'name' => 'Lyon Pharmacy',
            'email' => 'lyon@example.com',
            'clusters' => [
                'coordinates' => [
                    'latitude' => 45.7640,
                    'longitude' => 4.8357,
                ],
                'status' => 'active',
                'is_verified' => 'no',
            ],
        ]);

        TestCluster::create([
            'name' => 'Marseille Pharmacy',
            'email' => 'marseille@example.com',
            'clusters' => [
                'coordinates' => [
                    'latitude' => 43.2965,
                    'longitude' => 5.3698,
                ],
                'status' => 'inactive',
                'is_verified' => 'yes',
            ],
        ]);

        $results = TestCluster::whereCluster(
            'clusters',
            'DISTANCE(coordinates, 48.8566, 2.3522, km) < 500 & status=active & is_verified=yes'
        )->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Paris Pharmacy', $results[0]->name);
    }

    // ==================== REGISTRY TESTS ====================

    public function test_distance_function_registered_in_registry(): void
    {
        $registry = new SqlFunctionRegistry;

        $this->assertTrue($registry->has('DISTANCE'));
        $this->assertInstanceOf(DistanceFunction::class, $registry->get('DISTANCE'));
    }

    public function test_distance_function_to_sql_via_registry(): void
    {
        $registry = new SqlFunctionRegistry;

        $sql = $registry->toSql(
            'DISTANCE',
            self::COLUMN,
            self::PATH,
            DatabaseDriver::SQLITE,
            ['coordinates', 48.8566, 2.3522]
        );

        $this->assertStringContainsString('json_extract(clusters, \'$."coordinates".latitude\')', $sql);
        $this->assertStringContainsString('6371000', $sql);
    }

    public function test_distance_function_execute_via_registry(): void
    {
        $registry = new SqlFunctionRegistry;

        $coords = new CoordinatesVO(
            FloatVO::from(48.8566),
            FloatVO::from(2.3522)
        );

        $result = $registry->execute('DISTANCE', $coords, ['coordinates', 45.7640, 4.8357]);

        $this->assertTrue(is_float($result) || is_string($result));
        $distance = (float) $result;
        $this->assertGreaterThan(391000, $distance);
        $this->assertLessThan(392000, $distance);
    }
}
