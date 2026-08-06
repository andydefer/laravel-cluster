<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\SqlFunctions;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\SqlFunctions\AvgFunction;
use AndyDefer\LaravelCluster\SqlFunctions\ContainsFunction;
use AndyDefer\LaravelCluster\SqlFunctions\CountFunction;
use AndyDefer\LaravelCluster\SqlFunctions\JsonLengthFunction;
use AndyDefer\LaravelCluster\SqlFunctions\LengthFunction;
use AndyDefer\LaravelCluster\SqlFunctions\MaxFunction;
use AndyDefer\LaravelCluster\SqlFunctions\MinFunction;
use AndyDefer\LaravelCluster\SqlFunctions\RegexpFunction;
use AndyDefer\LaravelCluster\SqlFunctions\SumFunction;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class SqlFunctionsTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private const COLUMN = 'clusters';

    private const PATH = 'addresses';

    private ClusterQuery $clusterQuery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clusterQuery = new ClusterQuery;
    }

    // ==================== COUNT FUNCTION TESTS ====================

    public function test_count_function_execute_with_array(): void
    {
        $function = new CountFunction;

        $result = $function->execute(['a', 'b', 'c']);

        $this->assertSame(3, $result);
    }

    public function test_count_function_execute_with_string(): void
    {
        $function = new CountFunction;

        $result = $function->execute('hello');

        $this->assertSame(5, $result);
    }

    public function test_count_function_execute_with_empty_array(): void
    {
        $function = new CountFunction;

        $result = $function->execute([]);

        $this->assertSame(0, $result);
    }

    public function test_count_function_execute_with_null(): void
    {
        $function = new CountFunction;

        $result = $function->execute(null);

        $this->assertSame(0, $result);
    }

    public function test_count_function_execute_with_nested_array(): void
    {
        $function = new CountFunction;

        $result = $function->execute([['a', 'b'], ['c', 'd']]);

        $this->assertSame(2, $result);
    }

    public function test_count_function_to_sql_sqlite(): void
    {
        $function = new CountFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::SQLITE);

        $this->assertSame(
            'json_array_length(clusters, \'$.addresses\')',
            $sql
        );
    }

    public function test_count_function_to_sql_mysql(): void
    {
        $function = new CountFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::MYSQL);

        $this->assertSame(
            'JSON_LENGTH(clusters, \'$.addresses\')',
            $sql
        );
    }

    public function test_count_function_to_sql_pgsql(): void
    {
        $function = new CountFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::PGSQL);

        $this->assertSame(
            'jsonb_array_length(clusters->\'addresses\')',
            $sql
        );
    }

    public function test_count_function_integration_with_cluster_query(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John Doe',
            'addresses' => ['a', 'b', 'c'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane Smith',
            'addresses' => ['a'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob Johnson',
            'addresses' => ['a', 'b'],
        ]));

        $result = $this->clusterQuery->filter($collection, 'COUNT(addresses) > 2');

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->get('name'));
    }

    // ==================== SUM FUNCTION TESTS ====================

    public function test_sum_function_execute_with_integers(): void
    {
        $function = new SumFunction;

        $result = $function->execute([10, 20, 30]);

        $this->assertSame(60.0, $result);
    }

    public function test_sum_function_execute_with_floats(): void
    {
        $function = new SumFunction;

        $result = $function->execute([1.5, 2.5, 3.0]);

        $this->assertSame(7.0, $result);
    }

    public function test_sum_function_execute_with_mixed_values(): void
    {
        $function = new SumFunction;

        $result = $function->execute([10, '20', 30, 'not a number', 40]);

        $this->assertSame(100.0, $result);
    }

    public function test_sum_function_execute_with_empty_array(): void
    {
        $function = new SumFunction;

        $result = $function->execute([]);

        $this->assertSame(0.0, $result);
    }

    public function test_sum_function_execute_with_nested_array(): void
    {
        $function = new SumFunction;

        $result = $function->execute([[10, 20], [30, 40]]);

        $this->assertSame(100.0, $result);
    }

    public function test_sum_function_integration_with_cluster_query(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John Doe',
            'prices' => [100, 200, 300],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane Smith',
            'prices' => [50, 100],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob Johnson',
            'prices' => [500],
        ]));

        $result = $this->clusterQuery->filter($collection, 'SUM(prices) > 400');

        $this->assertCount(2, $result);
        $this->assertEquals('John Doe', $result->first()->get('name'));
        $this->assertEquals('Bob Johnson', $result->last()->get('name'));
    }

    // ==================== AVG FUNCTION TESTS ====================

    public function test_avg_function_execute_with_integers(): void
    {
        $function = new AvgFunction;

        $result = $function->execute([10, 20, 30]);

        $this->assertSame(20.0, $result);
    }

    public function test_avg_function_execute_with_floats(): void
    {
        $function = new AvgFunction;

        $result = $function->execute([1.5, 2.5, 3.0]);

        $this->assertSame(2.3333333333333335, $result);
    }

    public function test_avg_function_execute_with_mixed_values(): void
    {
        $function = new AvgFunction;

        $result = $function->execute([10, '20', 30, 'not a number', 40]);

        $this->assertSame(25.0, $result);
    }

    public function test_avg_function_execute_with_empty_array(): void
    {
        $function = new AvgFunction;

        $result = $function->execute([]);

        $this->assertSame(0.0, $result);
    }

    public function test_avg_function_execute_with_single_value(): void
    {
        $function = new AvgFunction;

        $result = $function->execute([85]);

        $this->assertSame(85.0, $result);
    }

    public function test_avg_function_integration_with_cluster_query(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John Doe',
            'scores' => [80, 90, 85],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane Smith',
            'scores' => [50, 60],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob Johnson',
            'scores' => [95],
        ]));

        $result = $this->clusterQuery->filter($collection, 'AVG(scores) >= 85');

        $this->assertCount(2, $result);
        $this->assertEquals('John Doe', $result->first()->get('name'));
        $this->assertEquals('Bob Johnson', $result->last()->get('name'));
    }

    // ==================== MIN FUNCTION TESTS ====================

    public function test_min_function_execute_with_integers(): void
    {
        $function = new MinFunction;

        $result = $function->execute([10, 30, 20, 5]);

        $this->assertEquals(5, $result);
    }

    public function test_min_function_execute_with_floats(): void
    {
        $function = new MinFunction;

        $result = $function->execute([1.5, 2.5, 0.5, 3.0]);

        $this->assertSame(0.5, $result);
    }

    public function test_min_function_execute_with_mixed_values(): void
    {
        $function = new MinFunction;

        $result = $function->execute([10, '20', 30, 'not a number', 5]);

        $this->assertEquals(5.0, $result);
    }

    public function test_min_function_execute_with_empty_array(): void
    {
        $function = new MinFunction;

        $result = $function->execute([]);

        $this->assertSame(0, $result);
    }

    public function test_min_function_integration_with_cluster_query(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John Doe',
            'scores' => [80, 90, 85],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane Smith',
            'scores' => [50, 60],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob Johnson',
            'scores' => [95],
        ]));

        $result = $this->clusterQuery->filter($collection, 'MIN(scores) > 60');

        $this->assertCount(2, $result);
        $this->assertEquals('John Doe', $result->first()->get('name'));
        $this->assertEquals('Bob Johnson', $result->last()->get('name'));
    }

    // ==================== MAX FUNCTION TESTS ====================

    public function test_max_function_execute_with_integers(): void
    {
        $function = new MaxFunction;

        $result = $function->execute([10, 30, 20, 5]);

        $this->assertEquals(30, $result);
    }

    public function test_max_function_execute_with_floats(): void
    {
        $function = new MaxFunction;

        $result = $function->execute([1.5, 2.5, 0.5, 3.0]);

        $this->assertSame(3.0, $result);
    }

    public function test_max_function_execute_with_mixed_values(): void
    {
        $function = new MaxFunction;

        $result = $function->execute([10, '20', 30, 'not a number', 5]);

        $this->assertEquals(30.0, $result);
    }

    public function test_max_function_execute_with_empty_array(): void
    {
        $function = new MaxFunction;

        $result = $function->execute([]);

        $this->assertSame(0, $result);
    }

    public function test_max_function_integration_with_cluster_query(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John Doe',
            'scores' => [80, 90, 85],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane Smith',
            'scores' => [50, 60],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob Johnson',
            'scores' => [95],
        ]));

        // MAX(scores) <= 90 → John (90) et Jane (60) → 2 résultats
        $result = $this->clusterQuery->filter($collection, 'MAX(scores) <= 90');

        $this->assertCount(2, $result);
        $this->assertEquals('John Doe', $result->first()->get('name'));
        $this->assertEquals('Jane Smith', $result->last()->get('name'));
    }

    // ==================== LENGTH FUNCTION TESTS ====================

    public function test_length_function_execute_with_string(): void
    {
        $function = new LengthFunction;

        $result = $function->execute('hello');

        $this->assertSame(5, $result);
    }

    public function test_length_function_execute_with_array(): void
    {
        $function = new LengthFunction;

        $result = $function->execute(['a', 'b', 'c']);

        $this->assertSame(3, $result);
    }

    public function test_length_function_execute_with_empty_string(): void
    {
        $function = new LengthFunction;

        $result = $function->execute('');

        $this->assertSame(0, $result);
    }

    public function test_length_function_execute_with_empty_array(): void
    {
        $function = new LengthFunction;

        $result = $function->execute([]);

        $this->assertSame(0, $result);
    }

    // HAHHAHAHA

    public function test_sum_function_to_sql_sqlite(): void
    {
        $function = new SumFunction;

        $sql = $function->toSql(self::COLUMN, 'prices', DatabaseDriver::SQLITE);

        $this->assertSame(
            "(SELECT SUM(json_extract(value, '$')) FROM json_each(clusters, '$.prices'))",
            $sql
        );
    }

    public function test_sum_function_to_sql_mysql(): void
    {
        $function = new SumFunction;

        $sql = $function->toSql(self::COLUMN, 'prices', DatabaseDriver::MYSQL);

        $this->assertSame(
            "(SELECT SUM(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(clusters, '$.\"prices\"[*]' COLUMNS(value JSON PATH '$')) AS jt)",
            $sql
        );
    }

    public function test_sum_function_to_sql_pgsql(): void
    {
        $function = new SumFunction;

        $sql = $function->toSql(self::COLUMN, 'prices', DatabaseDriver::PGSQL);

        $this->assertSame(
            "(SELECT SUM((value->>'$')::numeric) FROM json_array_elements(clusters->'prices') AS value)",
            $sql
        );
    }

    public function test_avg_function_to_sql_sqlite(): void
    {
        $function = new AvgFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::SQLITE);

        $this->assertSame(
            "(SELECT AVG(json_extract(value, '$')) FROM json_each(clusters, '$.scores'))",
            $sql
        );
    }

    public function test_avg_function_to_sql_mysql(): void
    {
        $function = new AvgFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::MYSQL);

        $this->assertSame(
            "(SELECT AVG(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(clusters, '$.\"scores\"[*]' COLUMNS(value JSON PATH '$')) AS jt)",
            $sql
        );
    }

    public function test_avg_function_to_sql_pgsql(): void
    {
        $function = new AvgFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::PGSQL);

        $this->assertSame(
            "(SELECT AVG((value->>'$')::numeric) FROM json_array_elements(clusters->'scores') AS value)",
            $sql
        );
    }

    public function test_min_function_to_sql_sqlite(): void
    {
        $function = new MinFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::SQLITE);

        $this->assertSame(
            "(SELECT MIN(json_extract(value, '$')) FROM json_each(clusters, '$.scores'))",
            $sql
        );
    }

    public function test_min_function_to_sql_mysql(): void
    {
        $function = new MinFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::MYSQL);

        $this->assertSame(
            "(SELECT MIN(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(clusters, '$.\"scores\"[*]' COLUMNS(value JSON PATH '$')) AS jt)",
            $sql
        );
    }

    public function test_min_function_to_sql_pgsql(): void
    {
        $function = new MinFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::PGSQL);

        $this->assertSame(
            "(SELECT MIN((value->>'$')::numeric) FROM json_array_elements(clusters->'scores') AS value)",
            $sql
        );
    }

    public function test_max_function_to_sql_sqlite(): void
    {
        $function = new MaxFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::SQLITE);

        $this->assertSame(
            "(SELECT MAX(json_extract(value, '$')) FROM json_each(clusters, '$.scores'))",
            $sql
        );
    }

    public function test_max_function_to_sql_mysql(): void
    {
        $function = new MaxFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::MYSQL);

        $this->assertSame(
            "(SELECT MAX(JSON_EXTRACT(value, '$')) FROM JSON_TABLE(clusters, '$.\"scores\"[*]' COLUMNS(value JSON PATH '$')) AS jt)",
            $sql
        );
    }

    public function test_max_function_to_sql_pgsql(): void
    {
        $function = new MaxFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::PGSQL);

        $this->assertSame(
            "(SELECT MAX((value->>'$')::numeric) FROM json_array_elements(clusters->'scores') AS value)",
            $sql
        );
    }

    public function test_length_function_execute_with_null(): void
    {
        $function = new LengthFunction;

        $result = $function->execute(null);

        $this->assertSame(0, $result);
    }

    public function test_length_function_to_sql_sqlite(): void
    {
        $function = new LengthFunction;

        $sql = $function->toSql(self::COLUMN, 'name', DatabaseDriver::SQLITE);

        $this->assertSame(
            'LENGTH(json_extract(clusters, \'$.name\'))',
            $sql
        );
    }

    public function test_length_function_to_sql_mysql(): void
    {
        $function = new LengthFunction;

        $sql = $function->toSql(self::COLUMN, 'name', DatabaseDriver::MYSQL);

        $this->assertSame(
            'LENGTH(JSON_UNQUOTE(JSON_EXTRACT(clusters, \'$.name\')))',
            $sql
        );
    }

    public function test_length_function_to_sql_pgsql(): void
    {
        $function = new LengthFunction;

        $sql = $function->toSql(self::COLUMN, 'name', DatabaseDriver::PGSQL);

        $this->assertSame(
            'LENGTH(clusters->>\'name\')',
            $sql
        );
    }

    public function test_length_function_integration_with_cluster_query(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John Doe',
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob Johnson',
        ]));

        $result = $this->clusterQuery->filter($collection, 'LENGTH(name) > 5');

        $this->assertCount(2, $result);
        $this->assertEquals('John Doe', $result->first()->get('name'));
        $this->assertEquals('Bob Johnson', $result->last()->get('name'));
    }

    // ==================== JSON_LENGTH FUNCTION TESTS ====================

    public function test_json_length_function_execute_with_array(): void
    {
        $function = new JsonLengthFunction;

        $result = $function->execute(['a', 'b', 'c']);

        $this->assertSame(3, $result);
    }

    public function test_json_length_function_execute_with_empty_array(): void
    {
        $function = new JsonLengthFunction;

        $result = $function->execute([]);

        $this->assertSame(0, $result);
    }

    public function test_json_length_function_execute_with_string(): void
    {
        $function = new JsonLengthFunction;

        $result = $function->execute('hello');

        $this->assertSame(0, $result);
    }

    public function test_json_length_function_execute_with_null(): void
    {
        $function = new JsonLengthFunction;

        $result = $function->execute(null);

        $this->assertSame(0, $result);
    }

    public function test_json_length_function_to_sql_sqlite(): void
    {
        $function = new JsonLengthFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::SQLITE);

        $this->assertSame(
            'json_array_length(clusters, \'$.addresses\')',
            $sql
        );
    }

    public function test_json_length_function_to_sql_mysql(): void
    {
        $function = new JsonLengthFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::MYSQL);

        $this->assertSame(
            'JSON_LENGTH(clusters, \'$.addresses\')',
            $sql
        );
    }

    public function test_json_length_function_to_sql_pgsql(): void
    {
        $function = new JsonLengthFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::PGSQL);

        $this->assertSame(
            'jsonb_array_length(clusters->\'addresses\')',
            $sql
        );
    }

    public function test_json_length_function_integration_with_cluster_query(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John Doe',
            'addresses' => ['a', 'b', 'c'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane Smith',
            'addresses' => ['a'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob Johnson',
            'addresses' => ['a', 'b'],
        ]));

        $result = $this->clusterQuery->filter($collection, 'JSON_LENGTH(addresses) > 2');

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->get('name'));
    }

    // ==================== REGEXP FUNCTION TESTS ====================

    public function test_regexp_function_execute_with_string(): void
    {
        $function = new RegexpFunction;

        $result = $function->execute('hello', ['name', '^hel.*']);

        $this->assertTrue($result);
    }

    public function test_regexp_function_execute_with_non_string(): void
    {
        $function = new RegexpFunction;

        $result = $function->execute(123, ['name', '^.*']);

        $this->assertFalse($result);
    }

    public function test_regexp_function_to_sql_sqlite(): void
    {
        $function = new RegexpFunction;

        $sql = $function->toSql(self::COLUMN, 'name', DatabaseDriver::SQLITE, ['name', '^John.*']);

        $this->assertSame(
            "json_extract(clusters, '$.name') REGEXP '^John.*'",
            $sql
        );
    }

    public function test_regexp_function_to_sql_mysql(): void
    {
        $function = new RegexpFunction;

        $sql = $function->toSql(self::COLUMN, 'name', DatabaseDriver::MYSQL, ['name', '^John.*']);

        $this->assertSame(
            "JSON_UNQUOTE(JSON_EXTRACT(clusters, '$.name')) REGEXP '^John.*'",
            $sql
        );
    }

    public function test_regexp_function_to_sql_pgsql(): void
    {
        $function = new RegexpFunction;

        $sql = $function->toSql(self::COLUMN, 'name', DatabaseDriver::PGSQL, ['name', '^John.*']);

        $this->assertSame(
            "clusters->>'name' ~ '^John.*'",
            $sql
        );
    }

    public function test_regexp_function_validate_args(): void
    {
        $function = new RegexpFunction;

        $this->assertTrue($function->validateArgs(['path', 'pattern']));
        $this->assertFalse($function->validateArgs(['path']));
        $this->assertFalse($function->validateArgs(['path', 'pattern', 'extra']));
        $this->assertFalse($function->validateArgs([]));
    }

    // ==================== CONTAINS FUNCTION TESTS ====================
    public function test_contains_function_execute_with_array_contains_value(): void
    {
        $function = new ContainsFunction;

        $result = $function->execute(['fr', 'en', 'es'], ['languages', 'fr']);

        $this->assertTrue($result);
    }

    public function test_contains_function_execute_with_array_not_contains_value(): void
    {
        $function = new ContainsFunction;

        $result = $function->execute(['fr', 'en', 'es'], ['de']);

        $this->assertFalse($result);
    }

    public function test_contains_function_execute_with_empty_array(): void
    {
        $function = new ContainsFunction;

        $result = $function->execute([], ['fr']);

        $this->assertFalse($result);
    }

    public function test_contains_function_execute_with_string_instead_of_array(): void
    {
        $function = new ContainsFunction;

        $result = $function->execute('fr,en,es', ['fr']);

        $this->assertFalse($result);
    }

    public function test_contains_function_execute_with_null(): void
    {
        $function = new ContainsFunction;

        $result = $function->execute(null, ['fr']);

        $this->assertFalse($result);
    }

    public function test_contains_function_execute_with_no_args(): void
    {
        $function = new ContainsFunction;

        $result = $function->execute(['fr', 'en'], []);

        $this->assertFalse($result);
    }

    public function test_contains_function_to_sql_sqlite(): void
    {
        $function = new ContainsFunction;

        $sql = $function->toSql(self::COLUMN, 'languages', DatabaseDriver::SQLITE, ['languages', 'fr']);

        $this->assertSame(
            "EXISTS (SELECT 1 FROM json_each(clusters, '$.languages') WHERE value = 'fr')",
            $sql
        );
    }

    public function test_contains_function_to_sql_mysql(): void
    {
        $function = new ContainsFunction;

        // ✅ Correction : passer 2 arguments (path + value)
        $sql = $function->toSql(self::COLUMN, 'languages', DatabaseDriver::MYSQL, ['languages', 'fr']);

        $this->assertSame(
            "JSON_SEARCH(clusters, 'one', 'fr', NULL, '$.\"languages\"') IS NOT NULL",
            $sql
        );
    }

    public function test_contains_function_to_sql_pgsql(): void
    {
        $function = new ContainsFunction;

        // ✅ Correction : passer 2 arguments (path + value)
        $sql = $function->toSql(self::COLUMN, 'languages', DatabaseDriver::PGSQL, ['languages', 'fr']);

        $this->assertSame(
            "EXISTS (SELECT 1 FROM json_array_elements_text(clusters->'languages') AS elem WHERE elem = 'fr')",
            $sql
        );
    }

    public function test_contains_function_to_sql_with_special_characters(): void
    {
        $function = new ContainsFunction;

        $sql = $function->toSql(self::COLUMN, 'languages', DatabaseDriver::SQLITE, ['languages', "fr'"]);

        $this->assertSame(
            "EXISTS (SELECT 1 FROM json_each(clusters, '$.languages') WHERE value = 'fr\\'')",
            $sql
        );
    }

    public function test_contains_function_validate_args(): void
    {
        $function = new ContainsFunction;

        // ✅ CONTAINS requires exactly 2 arguments: path and value
        $this->assertFalse($function->validateArgs(['fr']));           // ❌ 1 argument
        $this->assertTrue($function->validateArgs(['languages', 'fr'])); // ✅ 2 arguments
        $this->assertTrue($function->validateArgs(['fr', 'en']));      // ✅ 2 arguments (path=fr, value=en)
        $this->assertFalse($function->validateArgs(['']));             // ❌ vide
        $this->assertFalse($function->validateArgs([]));               // ❌ 0 argument
        $this->assertFalse($function->validateArgs(['languages', ''])); // ❌ value vide
    }

    public function test_contains_function_integration_with_cluster_query(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John Doe',
            'languages' => ['fr', 'en', 'es'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane Smith',
            'languages' => ['en', 'de'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob Johnson',
            'languages' => ['fr', 'it'],
        ]));

        // Syntaxe correcte : CONTAINS(languages, fr)
        $result = $this->clusterQuery->filter($collection, 'CONTAINS(languages, fr)');

        $this->assertCount(2, $result);
        $this->assertEquals('John Doe', $result->first()->get('name'));
        $this->assertEquals('Bob Johnson', $result->last()->get('name'));
    }

    public function test_contains_function_integration_with_other_conditions(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John Doe',
            'languages' => ['fr', 'en'],
            'status' => 'active',
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane Smith',
            'languages' => ['en', 'de'],
            'status' => 'active',
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob Johnson',
            'languages' => ['fr', 'it'],
            'status' => 'inactive',
        ]));

        $result = $this->clusterQuery->filter(
            $collection,
            'CONTAINS(languages, fr) & status=active'
        );

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->get('name'));
    }

    public function test_contains_function_with_multiple_values_or(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John Doe',
            'languages' => ['fr'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane Smith',
            'languages' => ['de'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob Johnson',
            'languages' => ['es'],
        ]));

        $result = $this->clusterQuery->filter(
            $collection,
            'CONTAINS(languages, fr) | CONTAINS(languages, es)'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('John Doe', $result->first()->get('name'));
        $this->assertEquals('Bob Johnson', $result->last()->get('name'));
    }

    public function test_contains_function_with_nested_path(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John Doe',
            'profile' => [
                'languages' => ['fr', 'en'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane Smith',
            'profile' => [
                'languages' => ['en', 'de'],
            ],
        ]));

        $result = $this->clusterQuery->filter($collection, 'CONTAINS(profile.languages, fr)');

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()->get('name'));
    }

    public function test_contains_function_with_eloquent_where_cluster(): void
    {
        TestCluster::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'clusters' => [
                'languages' => ['fr', 'en'],
                'status' => 'active',
            ],
        ]);

        TestCluster::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'clusters' => [
                'languages' => ['en', 'de'],
                'status' => 'active',
            ],
        ]);

        TestCluster::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'clusters' => [
                'languages' => ['fr', 'it'],
                'status' => 'inactive',
            ],
        ]);

        $results = TestCluster::whereCluster('clusters', 'CONTAINS(languages, fr)')->get();

        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Bob Johnson', $results[1]->name);
    }

    public function test_contains_function_with_eloquent_where_cluster_and_other_conditions(): void
    {
        TestCluster::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'clusters' => [
                'languages' => ['fr', 'en'],
                'status' => 'active',
            ],
        ]);

        TestCluster::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'clusters' => [
                'languages' => ['en', 'de'],
                'status' => 'active',
            ],
        ]);

        TestCluster::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'clusters' => [
                'languages' => ['fr', 'it'],
                'status' => 'inactive',
            ],
        ]);

        $results = TestCluster::whereCluster('clusters', 'CONTAINS(languages, fr) & status=active')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);
    }

    public function test_contains_function_with_no_matches(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John Doe',
            'languages' => ['en', 'de'],
        ]));

        $result = $this->clusterQuery->filter($collection, 'CONTAINS(languages, fr)');

        $this->assertCount(0, $result);
    }

    public function test_contains_function_with_empty_collection(): void
    {
        $collection = new ClusterVOCollection;

        $result = $this->clusterQuery->filter($collection, 'CONTAINS(languages, fr)');

        $this->assertCount(0, $result);
    }

    // ==================== REGISTRY TESTS ====================

    public function test_contains_function_registered_in_registry(): void
    {
        $registry = new SqlFunctionRegistry;

        $this->assertTrue($registry->has('CONTAINS'));
        $this->assertInstanceOf(ContainsFunction::class, $registry->get('CONTAINS'));
    }

    public function test_contains_function_to_sql_via_registry(): void
    {
        $registry = new SqlFunctionRegistry;

        $sql = $registry->toSql('CONTAINS', 'clusters', 'languages', DatabaseDriver::SQLITE, ['languages', 'fr']);

        $this->assertSame(
            "EXISTS (SELECT 1 FROM json_each(clusters, '$.languages') WHERE value = 'fr')",
            $sql
        );
    }

    public function test_contains_function_execute_via_registry(): void
    {
        $registry = new SqlFunctionRegistry;

        // ✅ Correction : passer 2 arguments (path + value)
        $result = $registry->execute('CONTAINS', ['fr', 'en'], ['languages', 'fr']);

        $this->assertTrue($result);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_validate_args_with_single_argument(): void
    {
        $function = new CountFunction;

        $this->assertTrue($function->validateArgs(['path']));
    }

    public function test_validate_args_with_multiple_arguments(): void
    {
        $function = new CountFunction;

        $this->assertFalse($function->validateArgs(['path', 'extra']));
    }

    public function test_validate_args_with_empty_arguments(): void
    {
        $function = new CountFunction;

        $this->assertFalse($function->validateArgs([]));
    }

    // ==================== GETTER TESTS ====================

    public function test_function_names(): void
    {
        $functions = [
            'COUNT' => new CountFunction,
            'SUM' => new SumFunction,
            'AVG' => new AvgFunction,
            'MIN' => new MinFunction,
            'MAX' => new MaxFunction,
            'LENGTH' => new LengthFunction,
            'JSON_LENGTH' => new JsonLengthFunction,
            'REGEXP' => new RegexpFunction,
            'CONTAINS' => new ContainsFunction,
        ];

        foreach ($functions as $name => $function) {
            $this->assertSame($name, $function->getName());
        }
    }

    public function test_return_types(): void
    {
        $intFunctions = [
            new CountFunction,
            new LengthFunction,
            new JsonLengthFunction,
        ];

        foreach ($intFunctions as $function) {
            $this->assertSame('int', $function->getReturnType());
        }

        $floatFunctions = [
            new SumFunction,
            new AvgFunction,
            new MinFunction,
            new MaxFunction,
        ];

        foreach ($floatFunctions as $function) {
            $this->assertSame('float', $function->getReturnType());
        }

        $boolFunctions = [
            new ContainsFunction,
            new RegexpFunction,  // ✅ AJOUT : REGEXP retourne bool
        ];

        foreach ($boolFunctions as $function) {
            $this->assertSame('bool', $function->getReturnType());
        }
    }

    public function test_get_default_value(): void
    {
        $function = new CountFunction;

        $this->assertSame(0, $function->getDefaultValue());
    }

    public function test_contains_function_get_default_value(): void
    {
        $function = new ContainsFunction;

        $this->assertFalse($function->getDefaultValue());
    }

    // ==================== EDGE CASES ====================

    public function test_sum_function_with_nested_array_and_non_numeric(): void
    {
        $function = new SumFunction;

        $result = $function->execute([[10, '20'], ['not a number', 30], 40]);

        $this->assertSame(100.0, $result);
    }

    public function test_avg_function_with_nested_array_and_non_numeric(): void
    {
        $function = new AvgFunction;

        $result = $function->execute([[10, '20'], ['not a number', 30], 40]);

        $this->assertSame(25.0, $result);
    }

    public function test_min_function_with_nested_array_and_non_numeric(): void
    {
        $function = new MinFunction;

        $result = $function->execute([[10, '20'], ['not a number', 5], 30]);

        $this->assertEquals(5.0, $result);
    }

    public function test_max_function_with_nested_array_and_non_numeric(): void
    {
        $function = new MaxFunction;

        $result = $function->execute([[10, '20'], ['not a number', 5], 30]);

        $this->assertEquals(30.0, $result);
    }

    // ==================== ELOQUENT WHERECLUSTER TESTS ====================

    public function test_count_function_with_eloquent_where_cluster(): void
    {
        TestCluster::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'clusters' => ['addresses' => ['a', 'b', 'c'], 'status' => 'active'],
        ]);

        TestCluster::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'clusters' => ['addresses' => ['a', 'b'], 'status' => 'active'],
        ]);

        TestCluster::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'clusters' => ['addresses' => ['a'], 'status' => 'inactive'],
        ]);

        // Test 1: COUNT > 2 → seulement John Doe
        $results = TestCluster::whereCluster('clusters', 'COUNT(addresses) > 2')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);

        // Test 2: COUNT >= 2 → John Doe et Jane Smith
        $results = TestCluster::whereCluster('clusters', 'COUNT(addresses) >= 2')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Smith', $results[1]->name);

        // Test 3: COUNT = 1 → Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'COUNT(addresses) = 1')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results[0]->name);

        // Test 4: COUNT != 2 → John Doe et Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'COUNT(addresses) != 2')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Bob Johnson', $results[1]->name);

        // Test 5: COUNT < 2 → Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'COUNT(addresses) < 2')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results[0]->name);

        // Test 6: COUNT <= 2 → Jane Smith et Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'COUNT(addresses) <= 2')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('Jane Smith', $results[0]->name);
        $this->assertEquals('Bob Johnson', $results[1]->name);

        // Test 7: Combinaison avec status → John Doe
        $results = TestCluster::whereCluster('clusters', 'COUNT(addresses) > 2 & status=active')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);
    }

    public function test_sum_function_with_eloquent_where_cluster(): void
    {
        TestCluster::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'clusters' => ['prices' => [100, 200, 300], 'status' => 'active'],
        ]);

        TestCluster::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'clusters' => ['prices' => [150, 250], 'status' => 'active'],
        ]);

        TestCluster::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'clusters' => ['prices' => [50, 75], 'status' => 'inactive'],
        ]);

        // Test 1: SUM > 400 → John Doe
        $results = TestCluster::whereCluster('clusters', 'SUM(prices) > 400')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);

        // Test 2: SUM >= 400 → John Doe et Jane Smith
        $results = TestCluster::whereCluster('clusters', 'SUM(prices) >= 400')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Smith', $results[1]->name);

        // Test 3: SUM = 600 → John Doe
        $results = TestCluster::whereCluster('clusters', 'SUM(prices) = 600')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);

        // Test 4: SUM < 200 → Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'SUM(prices) < 200')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results[0]->name);

        // Test 5: Combinaison avec autre condition
        TestCluster::create([
            'name' => 'Carol White',
            'email' => 'carol@example.com',
            'clusters' => ['prices' => [200, 300], 'status' => 'inactive'],
        ]);
        $results = TestCluster::whereCluster('clusters', 'SUM(prices) > 400 & status=active')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);
    }

    public function test_avg_function_with_eloquent_where_cluster(): void
    {
        TestCluster::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'clusters' => ['scores' => [80, 90, 85], 'status' => 'active'],
        ]);

        TestCluster::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'clusters' => ['scores' => [70, 75, 80], 'status' => 'inactive'],
        ]);

        TestCluster::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'clusters' => ['scores' => [95, 98, 92], 'status' => 'active'],
        ]);

        // Test 1: AVG >= 85 → John Doe et Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'AVG(scores) >= 85')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Bob Johnson', $results[1]->name);

        // Test 2: AVG > 90 → Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'AVG(scores) > 90')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results[0]->name);

        // Test 3: AVG = 85 → John Doe
        $results = TestCluster::whereCluster('clusters', 'AVG(scores) = 85')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);

        // Test 4: AVG <= 75 → Jane Smith (moyenne = 75)
        $results = TestCluster::whereCluster('clusters', 'AVG(scores) <= 75')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results[0]->name);

        // Test 5: AVG <= 80 → Jane Smith
        $results = TestCluster::whereCluster('clusters', 'AVG(scores) <= 80')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results[0]->name);

        // Test 6: Combinaison avec autre condition
        $results = TestCluster::whereCluster('clusters', 'AVG(scores) >= 85 & status=active')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Bob Johnson', $results[1]->name);
    }

    public function test_min_function_with_eloquent_where_cluster(): void
    {
        TestCluster::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'clusters' => ['scores' => [80, 90, 85], 'status' => 'active'],
        ]);

        TestCluster::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'clusters' => ['scores' => [50, 60, 55], 'status' => 'inactive'],
        ]);

        TestCluster::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'clusters' => ['scores' => [95, 98, 92], 'status' => 'active'],
        ]);

        // Test 1: MIN > 60 → John Doe et Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'MIN(scores) > 60')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Bob Johnson', $results[1]->name);

        // Test 2: MIN >= 90 → Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'MIN(scores) >= 90')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results[0]->name);

        // Test 3: MIN = 50 → Jane Smith
        $results = TestCluster::whereCluster('clusters', 'MIN(scores) = 50')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results[0]->name);

        // Test 4: MIN < 80 → Jane Smith
        $results = TestCluster::whereCluster('clusters', 'MIN(scores) < 80')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results[0]->name);

        // Test 5: Combinaison avec autre condition
        $results = TestCluster::whereCluster('clusters', 'MIN(scores) > 60 & status=active')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Bob Johnson', $results[1]->name);
    }

    public function test_max_function_with_eloquent_where_cluster(): void
    {
        TestCluster::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'clusters' => ['scores' => [80, 90, 85], 'status' => 'active'],
        ]);

        TestCluster::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'clusters' => ['scores' => [50, 60, 55], 'status' => 'active'],
        ]);

        TestCluster::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'clusters' => ['scores' => [95, 98, 92], 'status' => 'inactive'],
        ]);

        // Test 1: MAX <= 90 → John Doe et Jane Smith
        $results = TestCluster::whereCluster('clusters', 'MAX(scores) <= 90')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Smith', $results[1]->name);

        // Test 2: MAX < 90 → Jane Smith
        $results = TestCluster::whereCluster('clusters', 'MAX(scores) < 90')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results[0]->name);

        // Test 3: MAX > 90 → Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'MAX(scores) > 90')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results[0]->name);

        // Test 4: MAX >= 95 → Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'MAX(scores) >= 95')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results[0]->name);

        // Test 5: Combinaison avec autre condition
        $results = TestCluster::whereCluster('clusters', 'MAX(scores) <= 90 & status=active')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Smith', $results[1]->name);
    }

    public function test_length_function_with_eloquent_where_cluster(): void
    {
        TestCluster::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'clusters' => ['name' => 'John Doe'],
        ]);
        TestCluster::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'clusters' => ['name' => 'Jane'],
        ]);
        TestCluster::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'clusters' => ['name' => 'Bob'],
        ]);

        // Test 1: LENGTH > 5 → John Doe
        $results = TestCluster::whereCluster('clusters', 'LENGTH(name) > 5')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);

        // Test 2: LENGTH >= 4 → John Doe et Jane Smith
        $results = TestCluster::whereCluster('clusters', 'LENGTH(name) >= 4')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Smith', $results[1]->name);

        // Test 3: LENGTH = 3 → Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'LENGTH(name) = 3')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results[0]->name);

        // Test 4: LENGTH < 4 → Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'LENGTH(name) < 4')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results[0]->name);

        // Test 5: LENGTH <= 4 → Jane Smith et Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'LENGTH(name) <= 4')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('Jane Smith', $results[0]->name);
        $this->assertEquals('Bob Johnson', $results[1]->name);

        // Test 6: LENGTH != 3 → John Doe et Jane Smith
        $results = TestCluster::whereCluster('clusters', 'LENGTH(name) != 3')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Smith', $results[1]->name);

        // Test 7: Combinaison avec autre condition
        $john = TestCluster::where('name', 'John Doe')->first();
        $john->clusters = ['name' => 'John Doe', 'status' => 'active'];
        $john->save();

        $results = TestCluster::whereCluster('clusters', 'LENGTH(name) > 5 & status=active')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);
    }

    public function test_json_length_function_with_eloquent_where_cluster(): void
    {
        TestCluster::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'clusters' => ['addresses' => ['a', 'b', 'c']],
        ]);
        TestCluster::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'clusters' => ['addresses' => ['a', 'b']],
        ]);
        TestCluster::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'clusters' => ['addresses' => ['a']],
        ]);

        // Test 1: JSON_LENGTH > 2 → John Doe
        $results = TestCluster::whereCluster('clusters', 'JSON_LENGTH(addresses) > 2')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);

        // Test 2: JSON_LENGTH >= 2 → John Doe et Jane Smith
        $results = TestCluster::whereCluster('clusters', 'JSON_LENGTH(addresses) >= 2')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Smith', $results[1]->name);

        // Test 3: JSON_LENGTH = 1 → Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'JSON_LENGTH(addresses) = 1')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results[0]->name);

        // Test 4: JSON_LENGTH != 2 → John Doe et Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'JSON_LENGTH(addresses) != 2')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Bob Johnson', $results[1]->name);

        // Test 5: JSON_LENGTH < 2 → Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'JSON_LENGTH(addresses) < 2')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results[0]->name);

        // Test 6: JSON_LENGTH <= 2 → Jane Smith et Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'JSON_LENGTH(addresses) <= 2')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('Jane Smith', $results[0]->name);
        $this->assertEquals('Bob Johnson', $results[1]->name);

        // Test 7: Combinaison avec autre condition
        $john = TestCluster::where('name', 'John Doe')->first();
        $john->clusters = ['addresses' => ['a', 'b', 'c'], 'status' => 'active'];
        $john->save();

        $results = TestCluster::whereCluster('clusters', 'JSON_LENGTH(addresses) > 2 & status=active')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);
    }

    public function test_regexp_function_with_eloquent_where_cluster(): void
    {
        TestCluster::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'clusters' => ['name' => 'John Doe', 'status' => 'active'],
        ]);

        TestCluster::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'clusters' => ['name' => 'Jane Smith', 'status' => 'inactive'],
        ]);

        TestCluster::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'clusters' => ['name' => 'Bob Johnson', 'status' => 'inactive'],
        ]);

        // Test 1: Commence par "John" → John Doe
        $results = TestCluster::whereCluster('clusters', 'REGEXP(name, "^John.*")')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);

        // Test 2: Contient "Smith" → Jane Smith
        $results = TestCluster::whereCluster('clusters', 'REGEXP(name, "Smith")')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results[0]->name);

        // Test 3: Termine par "son" → Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'REGEXP(name, "son$")')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results[0]->name);

        // Test 4: Commence par "J" et contient "e" → John Doe, Jane Smith
        $results = TestCluster::whereCluster('clusters', 'REGEXP(name, "^J.*e.*")')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Smith', $results[1]->name);

        // Test 5: Combinaison avec autre condition
        $results = TestCluster::whereCluster('clusters', 'REGEXP(name, "^John.*") & status=active')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);
    }

    public function test_contains_function_with_eloquent_where_cluster_multiple_values(): void
    {
        TestCluster::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'clusters' => [
                'languages' => ['fr', 'en', 'es'],
                'status' => 'active',
            ],
        ]);

        TestCluster::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'clusters' => [
                'languages' => ['en', 'de'],
                'status' => 'active',
            ],
        ]);

        TestCluster::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'clusters' => [
                'languages' => ['fr', 'it'],
                'status' => 'inactive',
            ],
        ]);

        TestCluster::create([
            'name' => 'Carol White',
            'email' => 'carol@example.com',
            'clusters' => [
                'languages' => ['es', 'pt'],
                'status' => 'active',
            ],
        ]);

        // Test 1: Contient 'fr' → John Doe, Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'CONTAINS(languages, fr)')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Bob Johnson', $results[1]->name);

        // Test 2: Contient 'en' → John Doe, Jane Smith
        $results = TestCluster::whereCluster('clusters', 'CONTAINS(languages, en)')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Smith', $results[1]->name);

        // Test 3: Contient 'fr' ET 'en' → John Doe
        $results = TestCluster::whereCluster('clusters', 'CONTAINS(languages, fr) & CONTAINS(languages, en)')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);

        // Test 4: Contient 'fr' OU 'en' → John Doe, Jane Smith, Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'CONTAINS(languages, fr) | CONTAINS(languages, en)')->get();
        $this->assertCount(3, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Smith', $results[1]->name);
        $this->assertEquals('Bob Johnson', $results[2]->name);

        // Test 5: Contient 'fr' ET status=active → John Doe
        $results = TestCluster::whereCluster('clusters', 'CONTAINS(languages, fr) & status=active')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);

        // Test 6: Contient 'en' ET status=active → John Doe, Jane Smith
        $results = TestCluster::whereCluster('clusters', 'CONTAINS(languages, en) & status=active')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Smith', $results[1]->name);

        // Test 7: Contient 'fr' ET status=inactive → Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'CONTAINS(languages, fr) & status=inactive')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results[0]->name);

        // Test 8: Contient 'es' → John Doe, Carol White
        $results = TestCluster::whereCluster('clusters', 'CONTAINS(languages, es)')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Carol White', $results[1]->name);

        // Test 9: Contient 'fr' = false → Jane Smith, Carol White
        $results = TestCluster::whereCluster('clusters', 'CONTAINS(languages, fr) = false')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('Jane Smith', $results[0]->name);
        $this->assertEquals('Carol White', $results[1]->name);

        // Test 10: Contient 'fr' = true → John Doe, Bob Johnson
        $results = TestCluster::whereCluster('clusters', 'CONTAINS(languages, fr) = true')->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Bob Johnson', $results[1]->name);
    }

    public function test_multiple_functions_with_eloquent_where_cluster(): void
    {
        TestCluster::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'clusters' => [
                'addresses' => ['a', 'b', 'c'],
                'scores' => [80, 90, 85],
                'prices' => [100, 200, 300],
                'languages' => ['fr', 'en'],
                'name' => 'John Doe',
                'status' => 'active',
            ],
        ]);
        TestCluster::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'clusters' => [
                'addresses' => ['a', 'b'],
                'scores' => [50, 60, 55],
                'prices' => [150, 250],
                'languages' => ['en', 'de'],
                'name' => 'Jane Smith',
                'status' => 'active',
            ],
        ]);
        TestCluster::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'clusters' => [
                'addresses' => ['a'],
                'scores' => [95, 98, 92],
                'prices' => [50, 75],
                'languages' => ['fr', 'it'],
                'name' => 'Bob Johnson',
                'status' => 'inactive',
            ],
        ]);
        TestCluster::create([
            'name' => 'Carol White',
            'email' => 'carol@example.com',
            'clusters' => [
                'addresses' => ['a', 'b'],
                'scores' => [70, 75, 80],
                'prices' => [100, 150],
                'languages' => ['es', 'pt'],
                'name' => 'Carol White',
                'status' => 'active',
            ],
        ]);

        // Test 1: COUNT + AVG + LENGTH → John Doe
        $results = TestCluster::whereCluster('clusters', 'COUNT(addresses) > 2 & AVG(scores) >= 85 & LENGTH(name) > 5')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);

        // Test 2: SUM + MAX + MIN → John Doe (CORRIGÉ: <= 90 au lieu de < 90)
        $results = TestCluster::whereCluster('clusters', 'SUM(prices) > 400 & MAX(scores) <= 90')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);

        // Test 3: JSON_LENGTH + AVG + status → John Doe, Jane Smith
        $results = TestCluster::whereCluster('clusters', 'JSON_LENGTH(addresses) >= 2 & AVG(scores) >= 80 & status=active')->get();
        $this->assertCount(1, $results); // Seulement John Doe (Jane Smith a AVG=55)
        $this->assertEquals('John Doe', $results[0]->name);

        // Test 4: CONTAINS + COUNT + AVG → John Doe
        $results = TestCluster::whereCluster('clusters', 'CONTAINS(languages, fr) & COUNT(addresses) > 1 & AVG(scores) >= 85')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);

        // Test 5: Toutes les fonctions ensemble → John Doe
        $results = TestCluster::whereCluster('clusters',
            'COUNT(addresses) > 1 & '.
            'SUM(prices) > 400 & '.
            'AVG(scores) >= 85 & '.
            'MAX(scores) < 95 & '.
            'MIN(scores) > 75 & '.
            'LENGTH(name) > 5 & '.
            'JSON_LENGTH(addresses) >= 2 & '.
            'CONTAINS(languages, fr) & '.
            'status=active'
        )->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);
    }
}
