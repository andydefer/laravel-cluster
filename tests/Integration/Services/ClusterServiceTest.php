<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\Services;

use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Contracts\NodeInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Nodes\GroupNode;
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

final class ClusterServiceTest extends IntegrationTestCase
{
    private ClusterService $service;

    private ClusterVOCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ClusterService(new ClusterQuery);

        $testData = [
            [
                'status' => 'active',
                'role' => 'admin',
                'age' => '25',
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => '85.5',
            ],
            [
                'status' => 'inactive',
                'role' => 'doctor',
                'age' => '30',
                'lang_fr' => 'false',
                'lang_en' => 'true',
                'verified' => 'false',
                'score' => '92.0',
            ],
            [
                'status' => 'active',
                'role' => 'doctor',
                'age' => '35',
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => '78.0',
            ],
            [
                'status' => 'pending',
                'role' => 'guest',
                'age' => '18',
                'lang_fr' => 'false',
                'lang_en' => 'true',
                'verified' => 'false',
                'score' => '30.5',
            ],
            [
                'status' => 'active',
                'role' => 'admin',
                'age' => '40',
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => '95.0',
            ],
        ];

        foreach ($testData as $data) {
            TestCluster::create(['clusters' => $data]);
        }

        $this->collection = new ClusterVOCollection;
        foreach ($testData as $data) {
            $this->collection->add(new ClusterVO($data));
        }
    }

    // ==================== PARSE TESTS ====================

    public function test_parse_returns_node(): void
    {
        $result = $this->service->parse('status=active');

        $this->assertInstanceOf(NodeInterface::class, $result);
        $this->assertInstanceOf(ConditionNode::class, $result);
    }

    public function test_parse_complex_expression(): void
    {
        $result = $this->service->parse('status=active & role=admin');

        $this->assertInstanceOf(GroupNode::class, $result);
    }

    // ==================== FILTER TESTS ====================

    public function test_filter_returns_filtered_collection(): void
    {
        $result = $this->service->filter($this->collection, 'status=active');

        $this->assertInstanceOf(ClusterVOCollection::class, $result);
        $this->assertCount(3, $result);
    }

    public function test_filter_with_complex_query(): void
    {
        $result = $this->service->filter(
            $this->collection,
            'status=active & (role=admin | role=doctor)'
        );

        $this->assertCount(3, $result);
    }

    public function test_filter_with_presence(): void
    {
        $result = $this->service->filter(
            $this->collection,
            'lang_fr & verified'
        );

        // lang_fr=true ET verified=true : ID 1, 3, 5 → 3 clusters
        $this->assertCount(3, $result);
    }

    public function test_filter_with_absence(): void
    {
        $result = $this->service->filter(
            $this->collection,
            'lang_fr=false'
        );

        // lang_fr=false : ID 2, 4 → 2 clusters
        $this->assertCount(2, $result);
    }

    public function test_filter_with_numeric_comparison(): void
    {
        $result = $this->service->filter(
            $this->collection,
            'age>=30 & status=active'
        );

        $this->assertCount(2, $result);
    }

    public function test_filter_with_empty_result(): void
    {
        $result = $this->service->filter(
            $this->collection,
            'status=active & role=guest'
        );

        $this->assertCount(0, $result);
    }

    // ==================== MATCHES TESTS ====================

    public function test_matches_returns_true(): void
    {
        $cluster = new ClusterVO(['status' => 'active', 'role' => 'admin']);

        $result = $this->service->matches($cluster, 'status=active & role=admin');

        $this->assertTrue($result);
    }

    public function test_matches_returns_false(): void
    {
        $cluster = new ClusterVO(['status' => 'inactive', 'role' => 'admin']);

        $result = $this->service->matches($cluster, 'status=active & role=admin');

        $this->assertFalse($result);
    }

    public function test_matches_with_presence(): void
    {
        $cluster = new ClusterVO(['lang_fr' => 'true']);

        $result = $this->service->matches($cluster, 'lang_fr');

        $this->assertTrue($result);
    }

    public function test_matches_with_absence(): void
    {
        $cluster = new ClusterVO(['lang_fr' => 'false']);

        $result = $this->service->matches($cluster, 'lang_fr=false');

        $this->assertTrue($result);
    }

    // ==================== TO SQL TESTS ====================

    public function test_to_sql_mysql(): void
    {
        $sql = $this->service->toSql('clusters', 'status=active & role=admin', DatabaseDriver::MYSQL);

        $this->assertStringContainsString('JSON_EXTRACT', $sql);
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('admin', $sql);
    }

    public function test_to_sql_postgres(): void
    {
        $sql = $this->service->toSql('clusters', 'status=active & role=admin', DatabaseDriver::PGSQL);

        $this->assertStringContainsString("clusters->>'status'", $sql);
        $this->assertStringContainsString('active', $sql);
    }

    public function test_to_sql_sqlite(): void
    {
        $sql = $this->service->toSql('clusters', 'status=active & role=admin', DatabaseDriver::SQLITE);

        $this->assertStringContainsString('json_extract', $sql);
        $this->assertStringContainsString('active', $sql);
    }

    public function test_to_sql_default_driver(): void
    {
        $sql = $this->service->toSql('clusters', 'status=active');

        $this->assertStringContainsString('JSON_EXTRACT', $sql);
    }

    public function test_to_sql_with_complex_query(): void
    {
        $sql = $this->service->toSql(
            'clusters',
            '(status=active | status=pending) & lang_fr=true & lang_en=false & age>=25',
            DatabaseDriver::MYSQL
        );

        $this->assertStringContainsString('JSON_EXTRACT', $sql);
        $this->assertStringContainsString('AND', $sql);
        $this->assertStringContainsString('OR', $sql);
        $this->assertStringContainsString('>=', $sql);
    }

    // ==================== APPLY TO ELOQUENT TESTS ====================

    public function test_apply_to_eloquent_simple(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent($query, 'clusters', 'status=active', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_complex(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent(
            $query,
            'clusters',
            'status=active & (role=admin | role=doctor)',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_presence(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent($query, 'clusters', 'lang_fr & verified', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_absence(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent($query, 'clusters', 'lang_fr=false', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_numeric_comparison(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent($query, 'clusters', 'age>=30 & status=active', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_postgres(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent($query, 'clusters', 'status=active', DatabaseDriver::PGSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString("clusters->>'status'", $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_sqlite(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent($query, 'clusters', 'status=active', DatabaseDriver::SQLITE);

        $sql = $query->toSql();
        $this->assertStringContainsString('json_extract', $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_default_driver(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent($query, 'clusters', 'status=active');

        $sql = $query->toSql();
        $this->assertStringContainsString('JSON_EXTRACT', $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_complex_conditions(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent(
            $query,
            'clusters',
            '(status=active | status=pending) & lang_fr=true & lang_en=false & age>=25',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        // (active ou pending) ET lang_fr=true ET lang_en=false ET age>=25
        // ID 1 (active, true, false, 25) ✅
        // ID 3 (active, true, false, 35) ✅
        // ID 5 (active, true, false, 40) ✅
        // ID 2 (inactive) ❌
        // ID 4 (pending, false) ❌
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_or_conditions(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent(
            $query,
            'clusters',
            'status=active | role=admin',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        // status=active OU role=admin
        // ID 1 (active, admin) ✅
        // ID 2 (inactive, doctor) ❌
        // ID 3 (active, doctor) ✅
        // ID 4 (pending, guest) ❌
        // ID 5 (active, admin) ✅
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_not(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent(
            $query,
            'clusters',
            'lang_fr=false & status=active',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        // lang_fr=false ET status=active : aucun → 0
        $this->assertCount(0, $results);
    }

    // ==================== EDGE CASES TESTS ====================

    public function test_apply_to_eloquent_with_empty_result(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent(
            $query,
            'clusters',
            'status=active & role=guest',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(0, $results);
    }

    public function test_apply_to_eloquent_with_non_existent_key(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent(
            $query,
            'clusters',
            'non_existent=value',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(0, $results);
    }

    public function test_to_sql_with_non_existent_key(): void
    {
        $sql = $this->service->toSql('clusters', 'non_existent=value', DatabaseDriver::MYSQL);

        $this->assertStringContainsString('non_existent', $sql);
        $this->assertStringContainsString('value', $sql);
    }

    // ==================== PERFORMANCE TESTS ====================

    public function test_filter_large_collection(): void
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
        $result = $this->service->filter($collection, 'status=active & role=admin');
        $end = microtime(true);

        $this->assertCount(17, $result);
        $this->assertLessThan(0.1, $end - $start);
    }

    // ==================== CACHE TESTS ====================

    public function test_parse_cache(): void
    {
        $ast1 = $this->service->parse('status=active & role=admin');
        $ast2 = $this->service->parse('status=active & role=admin');

        $this->assertSame($ast1, $ast2);
    }

    public function test_parse_different_queries_not_cached(): void
    {
        $ast1 = $this->service->parse('status=active');
        $ast2 = $this->service->parse('role=admin');

        $this->assertNotSame($ast1, $ast2);
    }

    // ==================== EXISTS / NOT_EXISTS FILTER TESTS ====================

    public function test_filter_with_exists_operator(): void
    {
        $result = $this->service->filter(
            $this->collection,
            '*lang_fr'
        );

        $this->assertCount(5, $result);
    }

    public function test_filter_with_not_exists_operator(): void
    {
        $result = $this->service->filter(
            $this->collection,
            '#lang_es'
        );

        $this->assertCount(5, $result);
    }

    public function test_filter_with_exists_and_condition(): void
    {
        $result = $this->service->filter(
            $this->collection,
            '*verified & status=active'
        );

        $this->assertCount(3, $result);
    }

    public function test_filter_with_not_exists_or_condition(): void
    {
        $result = $this->service->filter(
            $this->collection,
            '#lang_es | status=active'
        );

        $this->assertCount(5, $result);
    }

    public function test_apply_to_eloquent_with_exists(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent(
            $query,
            'clusters',
            '*lang_fr',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(5, $results);
    }

    public function test_apply_to_eloquent_with_not_exists(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent(
            $query,
            'clusters',
            '#lang_es',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(5, $results);
    }

    public function test_apply_to_eloquent_with_exists_and_condition(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent(
            $query,
            'clusters',
            '*verified & status=active',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_not_exists_or_condition(): void
    {
        $query = TestCluster::query();

        $this->service->applyToEloquent(
            $query,
            'clusters',
            '#lang_es | status=active',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(5, $results);
    }

    // ==================== LIKE / NOT_LIKE FILTER TESTS ====================

    public function test_filter_with_like_operator(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_doe']));
        $collection->add(new ClusterVO(['name' => 'bob']));

        $result = $this->service->filter($collection, 'name=~john');

        $this->assertCount(1, $result);
    }

    public function test_apply_to_eloquent_with_like(): void
    {
        // Créer des données en base
        TestCluster::create(['clusters' => ['name' => 'john_doe']]);
        TestCluster::create(['clusters' => ['name' => 'jane_doe']]);
        TestCluster::create(['clusters' => ['name' => 'bob']]);

        $query = TestCluster::query();

        $this->service->applyToEloquent(
            $query,
            'clusters',
            'name=~john',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(1, $results);
    }

    public function test_apply_to_eloquent_with_not_like(): void
    {
        TestCluster::create(['clusters' => ['name' => 'john_doe']]);
        TestCluster::create(['clusters' => ['name' => 'jane_doe']]);
        TestCluster::create(['clusters' => ['name' => 'bob']]);

        $query = TestCluster::query();

        $this->service->applyToEloquent(
            $query,
            'clusters',
            'name!~john',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }
}
