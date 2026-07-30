<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Benchmark;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

/**
 * Benchmark tests for ClusterVOCollection performance.
 *
 * This test suite measures execution time for various operations on
 * different dataset sizes to identify performance bottlenecks.
 *
 * @group benchmark
 */
final class ClusterVOCollectionBenchmarkTest extends IntegrationTestCase
{
    private const BENCHMARK_ITERATIONS = 100;

    private const SMALL_DATASET_SIZE = 10;

    private const MEDIUM_DATASET_SIZE = 100;

    private const LARGE_DATASET_SIZE = 1000;

    private const HUGE_DATASET_SIZE = 10000;

    private static array $datasets = [];

    private static bool $dataInitialized = false;

    private static bool $databaseInitialized = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$dataInitialized) {
            $this->initializeDatasets();
            self::$dataInitialized = true;
        }

        if (! self::$databaseInitialized && $this->isDatabaseTest()) {
            $this->initializeDatabase();
            self::$databaseInitialized = true;
        }
    }

    /**
     * Determines if the current test requires database access.
     */
    private function isDatabaseTest(): bool
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $testName = $trace[1]['function'] ?? '';

        return str_contains($testName, 'eloquent') ||
            str_contains($testName, 'apply_to_eloquent');
    }

    /**
     * Initializes all datasets for benchmarking.
     */
    private function initializeDatasets(): void
    {
        echo "\n🔧 Initialisation des datasets...\n";
        $start = microtime(true);

        self::$datasets = [
            'small' => $this->generateCollection(self::SMALL_DATASET_SIZE),
            'medium' => $this->generateCollection(self::MEDIUM_DATASET_SIZE),
            'large' => $this->generateCollection(self::LARGE_DATASET_SIZE),
            'huge' => $this->generateCollection(self::HUGE_DATASET_SIZE),
        ];

        $time = (microtime(true) - $start) * 1000;
        echo sprintf("✅ Datasets générés en %.2f ms\n", $time);
    }

    /**
     * Initializes the database with test data.
     */
    private function initializeDatabase(): void
    {
        echo "🔧 Initialisation de la base de données...\n";
        $start = microtime(true);

        TestCluster::truncate();

        $count = 0;
        foreach (self::$datasets['medium'] as $cluster) {
            TestCluster::create([
                'clusters' => $cluster->toArray(),
            ]);
            $count++;
        }

        $time = (microtime(true) - $start) * 1000;
        echo sprintf("✅ %d clusters insérés en %.2f ms\n", $count, $time);
    }

    /**
     * Generates a collection of clusters.
     *
     * @param  int  $size  The number of clusters to generate
     * @return ClusterVOCollection The generated collection
     */
    private function generateCollection(int $size): ClusterVOCollection
    {
        $collection = new ClusterVOCollection;

        for ($i = 0; $i < $size; $i++) {
            $collection->add($this->generateCluster($i));
        }

        return $collection;
    }

    /**
     * Generates a single cluster with realistic test data.
     *
     * @param  int  $index  The index to seed the data
     * @return ClusterVO The generated cluster
     */
    private function generateCluster(int $index): ClusterVO
    {
        $statuses = ['active', 'inactive', 'pending'];
        $roles = ['admin', 'doctor', 'user', 'guest'];
        $cities = ['Kinshasa', 'Paris', 'London', 'New York', 'Tokyo', 'Lubumbashi'];
        $countries = ['RDC', 'France', 'UK', 'USA', 'Japan'];

        $addressCount = ($index % 3) + 1;
        $addresses = [];

        for ($i = 0; $i < $addressCount; $i++) {
            $addresses[] = [
                'city' => $cities[array_rand($cities)],
                'country' => $countries[array_rand($countries)],
                'street' => 'Street '.($i + 1),
            ];
        }

        $scores = [];
        $scoreCount = ($index % 5) + 3;
        for ($i = 0; $i < $scoreCount; $i++) {
            $scores[] = rand(60, 100);
        }

        return new ClusterVO([
            'id' => $index + 1,
            'name' => 'User_'.$index,
            'status' => $statuses[$index % 3],
            'role' => $roles[$index % 4],
            'age' => rand(18, 65),
            'verified' => $index % 2 === 0 ? 'true' : 'false',
            'lang_fr' => $index % 3 === 0 ? 'true' : 'false',
            'lang_en' => $index % 3 === 1 ? 'true' : 'false',
            'addresses' => $addresses,
            'scores' => $scores,
            'tags' => ['tag_'.($index % 5), 'tag_'.(($index + 1) % 5)],
        ]);
    }

    /**
     * Measures the execution time of a callback over multiple iterations.
     *
     * @param  callable  $callback  The callback to measure
     * @return float The average execution time in seconds
     */
    private function measureExecutionTime(callable $callback): float
    {
        $start = microtime(true);

        for ($i = 0; $i < self::BENCHMARK_ITERATIONS; $i++) {
            $callback();
        }

        $end = microtime(true);

        return ($end - $start) / self::BENCHMARK_ITERATIONS;
    }

    /**
     * Prints benchmark results in a formatted table.
     *
     * @param  string  $testName  The name of the test
     * @param  array<string, float>  $results  The results [size => time]
     * @param  string|null  $customSize  Custom size label for non-dataset tests
     */
    private function printResults(string $testName, array $results, ?string $customSize = null): void
    {
        echo "\n";
        echo "============================================\n";
        echo "  BENCHMARK: {$testName}\n";
        echo "============================================\n";

        foreach ($results as $size => $time) {
            $sizeLabel = ucfirst($size);

            if (isset(self::$datasets[$size])) {
                $items = self::$datasets[$size]->count();
            } else {
                $items = $customSize ?? 1;
            }

            echo sprintf(
                "  %-10s : %-6d items | %8.4f ms | %8.2f μs/item\n",
                $sizeLabel,
                $items,
                $time * 1000,
                ($time * 1000000) / max(1, $items)
            );
        }

        echo "============================================\n\n";
    }

    // ==================== BENCHMARK TESTS ====================

    /**
     * Benchmarks simple where filter operations.
     *
     * @test
     *
     * @group benchmark
     */
    public function test_benchmark_simple_where_filter(): void
    {
        $results = [];

        foreach (self::$datasets as $size => $collection) {
            $time = $this->measureExecutionTime(
                fn () => $collection->where('status', 'active')
            );

            $results[$size] = $time;
        }

        $this->printResults('Simple WHERE Filter', $results);
        $this->assertTrue(true);
    }

    /**
     * Benchmarks chained where filter operations.
     *
     * @test
     *
     * @group benchmark
     */
    public function test_benchmark_chained_where_filters(): void
    {
        $results = [];

        foreach (self::$datasets as $size => $collection) {
            $time = $this->measureExecutionTime(
                function () use ($collection) {
                    return $collection
                        ->where('status', 'active')
                        ->where('role', 'admin')
                        ->whereGreaterThan('age', 25)
                        ->whereTrue('verified');
                }
            );

            $results[$size] = $time;
        }

        $this->printResults('Chained WHERE Filters', $results);
        $this->assertTrue(true);
    }

    /**
     * Benchmarks query parser operations.
     *
     * @test
     *
     * @group benchmark
     */
    public function test_benchmark_where_query_parser(): void
    {
        $query = 'status=active & role=admin & age>25 & lang_fr=true';
        $results = [];

        foreach (self::$datasets as $size => $collection) {
            $clusterQuery = new ClusterQuery;

            $time = $this->measureExecutionTime(
                function () use ($collection, $clusterQuery, $query) {
                    return $clusterQuery->filter($collection, $query);
                }
            );

            $results[$size] = $time;
        }

        $this->printResults('WHERE Query Parser', $results);
        $this->assertTrue(true);
    }

    /**
     * Benchmarks aggregate COUNT filter operations.
     *
     * @test
     *
     * @group benchmark
     */
    public function test_benchmark_aggregate_count_filter(): void
    {
        $results = [];

        foreach (self::$datasets as $size => $collection) {
            $time = $this->measureExecutionTime(
                fn () => $collection->whereAggregate('{COUNT(addresses) > 2}')
            );

            $results[$size] = $time;
        }

        $this->printResults('Aggregate COUNT Filter', $results);
        $this->assertTrue(true);
    }

    /**
     * Benchmarks complex aggregate expressions.
     *
     * @test
     *
     * @group benchmark
     */
    public function test_benchmark_aggregate_complex_expression(): void
    {
        $expression = '{COUNT(addresses) > 1} & {AVG(scores) >= 85}';
        $results = [];

        foreach (self::$datasets as $size => $collection) {
            $time = $this->measureExecutionTime(
                fn () => $collection->whereAggregate($expression)
            );

            $results[$size] = $time;
        }

        $this->printResults('Aggregate Complex Expression', $results);
        $this->assertTrue(true);
    }

    /**
     * Benchmarks Eloquent query application.
     *
     * @test
     *
     * @group benchmark
     */
    public function test_benchmark_apply_to_eloquent(): void
    {
        $query = 'status=active & age>25 & lang_fr=true';
        $results = [];

        foreach (['small', 'medium'] as $size) {
            $clusterQuery = new ClusterQuery;

            $time = $this->measureExecutionTime(
                function () use ($clusterQuery, $query) {
                    $queryBuilder = TestCluster::query();
                    $clusterQuery->applyToEloquent(
                        $queryBuilder,
                        'clusters',
                        $query,
                        DatabaseDriver::SQLITE
                    );

                    return $queryBuilder->get();
                }
            );

            $results[$size] = $time;
        }

        $this->printResults('Apply to Eloquent (SQL)', $results);
        $this->assertTrue(true);
    }

    /**
     * Benchmarks Eloquent query application with subconditions.
     *
     * @test
     *
     * @group benchmark
     */
    public function test_benchmark_apply_to_eloquent_with_subconditions(): void
    {
        $query = 'status=active & addresses[city=kinshasa]';
        $results = [];

        foreach (['small', 'medium'] as $size) {
            $clusterQuery = new ClusterQuery;

            $time = $this->measureExecutionTime(
                function () use ($clusterQuery, $query) {
                    $queryBuilder = TestCluster::query();
                    $clusterQuery->applyToEloquent(
                        $queryBuilder,
                        'clusters',
                        $query,
                        DatabaseDriver::SQLITE
                    );

                    return $queryBuilder->get();
                }
            );

            $results[$size] = $time;
        }

        $this->printResults('Apply to Eloquent with Subconditions', $results);
        $this->assertTrue(true);
    }

    /**
     * Benchmarks matches operation on a single cluster.
     *
     * @test
     *
     * @group benchmark
     */
    public function test_benchmark_matches_on_single_cluster(): void
    {
        $clusterQuery = new ClusterQuery;
        $query = 'status=active & role=admin & age>25';
        $cluster = $this->generateCluster(1);

        $time = $this->measureExecutionTime(
            fn () => $clusterQuery->matches($cluster, $query)
        );

        $results = ['small' => $time];
        $this->printResults('Matches on Single Cluster', $results, '1');
        $this->assertTrue(true);
    }

    /**
     * Benchmarks complex chaining of all features.
     *
     * @test
     *
     * @group benchmark
     */
    public function test_benchmark_complex_chaining(): void
    {
        $results = [];

        foreach (self::$datasets as $size => $collection) {
            $time = $this->measureExecutionTime(
                function () use ($collection) {
                    return $collection
                        ->whereQuery('status=active')
                        ->whereQuery('(role=admin | role=doctor)')
                        ->whereQuery('{COUNT(addresses) > 1}')
                        ->whereQuery('{AVG(scores) >= 80}')
                        ->whereQuery('name=~User_%')
                        ->whereQuery('{HAS(tags, "tag_1")}');
                }
            );

            $results[$size] = $time;
        }

        $this->printResults('Complex Chaining (All Features)', $results);
        $this->assertTrue(true);
    }
}
