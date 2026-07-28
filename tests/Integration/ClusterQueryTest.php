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
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

final class ClusterQueryTest extends IntegrationTestCase
{
    private ClusterQuery $clusterQuery;

    private ClusterVOCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clusterQuery = new ClusterQuery;

        // Données avec des strings pour éviter les problèmes de types booléens
        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'admin',
                'age' => '25',
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => '85.5',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'inactive',
                'role' => 'doctor',
                'age' => '30',
                'lang_fr' => 'false',
                'lang_en' => 'true',
                'verified' => 'false',
                'score' => '92.0',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'doctor',
                'age' => '35',
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => '78.0',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'pending',
                'role' => 'guest',
                'age' => '18',
                'lang_fr' => 'false',
                'lang_en' => 'true',
                'verified' => 'false',
                'score' => '30.5',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'admin',
                'age' => '40',
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => '95.0',
            ],
        ]);

        // Collection en mémoire avec des strings
        $this->collection = new ClusterVOCollection;
        $this->collection->add(new ClusterVO([
            'status' => 'active',
            'role' => 'admin',
            'age' => '25',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'verified' => 'true',
            'score' => '85.5',
        ]));
        $this->collection->add(new ClusterVO([
            'status' => 'inactive',
            'role' => 'doctor',
            'age' => '30',
            'lang_fr' => 'false',
            'lang_en' => 'true',
            'verified' => 'false',
            'score' => '92.0',
        ]));
        $this->collection->add(new ClusterVO([
            'status' => 'active',
            'role' => 'doctor',
            'age' => '35',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'verified' => 'true',
            'score' => '78.0',
        ]));
        $this->collection->add(new ClusterVO([
            'status' => 'pending',
            'role' => 'guest',
            'age' => '18',
            'lang_fr' => 'false',
            'lang_en' => 'true',
            'verified' => 'false',
            'score' => '30.5',
        ]));
        $this->collection->add(new ClusterVO([
            'status' => 'active',
            'role' => 'admin',
            'age' => '40',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'verified' => 'true',
            'score' => '95.0',
        ]));
    }

    // ==================== PARSE TESTS ====================

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

    // ==================== FILTER TESTS (MEMORY) ====================

    public function test_filter_returns_filtered_collection(): void
    {
        $result = $this->clusterQuery->filter($this->collection, 'status=active');

        $this->assertInstanceOf(ClusterVOCollection::class, $result);
        $this->assertCount(3, $result);
    }

    public function test_filter_with_complex_query(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'status=active & role=admin'
        );

        $this->assertCount(2, $result);
    }

    public function test_filter_with_or(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'status=active | status=pending'
        );

        $this->assertCount(4, $result);
    }

    public function test_filter_with_parentheses(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            '(status=active | status=pending) & role=admin'
        );

        $this->assertCount(2, $result);
    }

    public function test_filter_with_presence(): void
    {
        // lang_fr est un raccourci pour lang_fr=true
        $result = $this->clusterQuery->filter(
            $this->collection,
            'lang_fr'
        );

        // lang_fr=true : ID 1, 3, 5 → 3 clusters
        $this->assertCount(3, $result);
    }

    public function test_filter_with_absence(): void
    {
        // !lang_fr est un raccourci pour lang_fr=false
        $result = $this->clusterQuery->filter(
            $this->collection,
            '!lang_fr'
        );

        // lang_fr=false : ID 2, 4 → 2 clusters
        $this->assertCount(2, $result);
    }

    public function test_filter_with_true_value(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'lang_fr=true'
        );

        // lang_fr=true : ID 1, 3, 5 → 3 clusters
        $this->assertCount(3, $result);
    }

    public function test_filter_with_false_value(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'lang_fr=false'
        );

        // lang_fr=false : ID 2, 4 → 2 clusters
        $this->assertCount(2, $result);
    }

    public function test_filter_with_gt_operator(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'age>30'
        );

        // age > 30 : 35, 40 → 2 clusters
        $this->assertCount(2, $result);
    }

    public function test_filter_with_gte_operator(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'age>=35'
        );

        // age >= 35 : 35, 40 → 2 clusters
        $this->assertCount(2, $result);
    }

    public function test_filter_with_lt_operator(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'age<30'
        );

        // age < 30 : 25, 18 → 2 clusters
        $this->assertCount(2, $result);
    }

    public function test_filter_with_lte_operator(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'age<=25'
        );

        // age <= 25 : 25, 18 → 2 clusters
        $this->assertCount(2, $result);
    }

    public function test_filter_with_not_equal(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'status!=active'
        );

        // status != active : inactive, pending → 2 clusters
        $this->assertCount(2, $result);
    }

    public function test_filter_with_empty_result(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'status=active & role=guest'
        );

        $this->assertCount(0, $result);
    }

    // ==================== FILTER TESTS (ELOQUENT) ====================

    public function test_apply_to_eloquent_simple(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent($query, 'clusters', 'status=active', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_complex_and(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status=active & role=admin',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_or(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status=active | status=pending',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(4, $results);
    }

    public function test_apply_to_eloquent_with_parentheses(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            '(status=active | status=pending) & role=admin',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_true_value(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'lang_fr=true',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_false_value(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'lang_fr=false',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_presence(): void
    {
        $query = TestCluster::query();

        // lang_fr est un raccourci pour lang_fr=true
        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'lang_fr',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        // lang_fr=true : ID 1, 3, 5 → 3 clusters
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_absence(): void
    {
        $query = TestCluster::query();

        // !lang_fr est un raccourci pour lang_fr=false
        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            '!lang_fr',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        // lang_fr=false : ID 2, 4 → 2 clusters
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_gt(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'age>30',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_gte(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'age>=35',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_not_equal(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status!=active',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_apply_to_eloquent_with_complex_nested(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            '(status=active | status=pending) & lang_fr & age>=25',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        // (active ou pending) ET lang_fr=true ET age>=25
        // ID 1 (active, true, 25) ✅
        // ID 3 (active, true, 35) ✅
        // ID 5 (active, true, 40) ✅
        // ID 4 (pending, false, 18) ❌
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_with_empty_result(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status=active & role=guest',
            DatabaseDriver::MYSQL
        );

        $results = $query->get();
        $this->assertCount(0, $results);
    }

    public function test_apply_to_eloquent_postgres(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status=active',
            DatabaseDriver::PGSQL
        );

        $sql = $query->toSql();
        $this->assertStringContainsString("clusters->>'status'", $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_apply_to_eloquent_sqlite(): void
    {
        $query = TestCluster::query();

        $this->clusterQuery->applyToEloquent(
            $query,
            'clusters',
            'status=active',
            DatabaseDriver::SQLITE
        );

        $sql = $query->toSql();
        $this->assertStringContainsString('json_extract', $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    // ==================== MATCHES TESTS ====================

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
        $cluster = new ClusterVO(['lang_fr' => 'true']);

        $result = $this->clusterQuery->matches($cluster, 'lang_fr');

        $this->assertTrue($result);
    }

    public function test_matches_with_absence(): void
    {
        $cluster = new ClusterVO(['lang_fr' => 'false']);

        $result = $this->clusterQuery->matches($cluster, '!lang_fr');

        $this->assertTrue($result);
    }

    public function test_matches_with_true_value(): void
    {
        $cluster = new ClusterVO(['lang_fr' => 'true']);

        $result = $this->clusterQuery->matches($cluster, 'lang_fr=true');

        $this->assertTrue($result);
    }

    public function test_matches_with_false_value(): void
    {
        $cluster = new ClusterVO(['lang_fr' => 'false']);

        $result = $this->clusterQuery->matches($cluster, 'lang_fr=false');

        $this->assertTrue($result);
    }

    // ==================== TO SQL TESTS ====================

    public function test_to_sql_mysql(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'status=active', DatabaseDriver::MYSQL);

        $this->assertStringContainsString('JSON_EXTRACT', $sql);
        $this->assertStringContainsString('status', $sql);
    }

    public function test_to_sql_postgres(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'status=active', DatabaseDriver::PGSQL);

        $this->assertStringContainsString("clusters->>'status'", $sql);
    }

    public function test_to_sql_sqlite(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'status=active', DatabaseDriver::SQLITE);

        $this->assertStringContainsString('json_extract', $sql);
    }

    public function test_to_sql_default_driver_mysql(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'status=active');

        $this->assertStringContainsString('JSON_EXTRACT', $sql);
    }

    public function test_to_sql_with_presence(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'lang_fr', DatabaseDriver::MYSQL);

        $this->assertStringContainsString("= 'true'", $sql);
    }

    public function test_to_sql_with_absence(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', '!lang_fr', DatabaseDriver::MYSQL);

        $this->assertStringContainsString("= 'false'", $sql);
    }

    public function test_to_sql_with_true_value(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'lang_fr=true', DatabaseDriver::MYSQL);

        $this->assertStringContainsString("= 'true'", $sql);
    }

    public function test_to_sql_with_false_value(): void
    {
        $sql = $this->clusterQuery->toSql('clusters', 'lang_fr=false', DatabaseDriver::MYSQL);

        $this->assertStringContainsString("= 'false'", $sql);
    }

    // ==================== EDGE CASES TESTS ====================

    public function test_filter_with_empty_collection(): void
    {
        $emptyCollection = new ClusterVOCollection;

        $result = $this->clusterQuery->filter($emptyCollection, 'status=active');

        $this->assertCount(0, $result);
    }

    public function test_filter_with_non_existent_key(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            'non_existent=value'
        );

        $this->assertCount(0, $result);
    }

    public function test_matches_with_non_existent_key(): void
    {
        $cluster = new ClusterVO(['status' => 'active']);

        $result = $this->clusterQuery->matches($cluster, 'non_existent=value');

        $this->assertFalse($result);
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

    // ==================== PERFORMANCE TESTS ====================

    public function test_filter_large_collection_performance(): void
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
        $result = $this->clusterQuery->filter($collection, 'status=active & role=admin');
        $end = microtime(true);

        $this->assertCount(17, $result);
        $this->assertLessThan(0.1, $end - $start);
    }

    // ==================== EXISTS / NOT_EXISTS FILTER TESTS ====================

    public function test_filter_with_exists_operator(): void
    {
        // *lang_fr vérifie l'existence de la clé lang_fr
        $result = $this->clusterQuery->filter(
            $this->collection,
            '*lang_fr'
        );

        // Tous les clusters ont lang_fr → 5 clusters
        $this->assertCount(5, $result);
    }

    public function test_filter_with_not_exists_operator(): void
    {
        // #lang_es vérifie l'absence de la clé lang_es
        $result = $this->clusterQuery->filter(
            $this->collection,
            '#lang_es'
        );

        // Aucun cluster n'a lang_es → 5 clusters
        $this->assertCount(5, $result);
    }

    public function test_filter_with_exists_and_condition(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            '*verified & status=active'
        );

        // clusters avec verified ET status=active : ID 1, 3, 5 → 3 clusters
        $this->assertCount(3, $result);
    }

    public function test_filter_with_not_exists_or_condition(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            '#lang_es | status=active'
        );

        // clusters sans lang_es OU status=active : tous → 5 clusters
        $this->assertCount(5, $result);
    }

    public function test_filter_with_exists_complex(): void
    {
        $result = $this->clusterQuery->filter(
            $this->collection,
            '(*lang_fr | #lang_en) & age>=25'
        );

        // (lang_fr existe OU lang_en n'existe pas) ET age>=25
        // ID 1 (lang_fr existe, age=25) ✅
        // ID 2 (lang_fr existe, age=30) ✅  ← AJOUTÉ
        // ID 3 (lang_fr existe, age=35) ✅
        // ID 5 (lang_fr existe, age=40) ✅
        // ID 4 (lang_en existe, age=18) ❌
        $this->assertCount(4, $result);
    }

    // ==================== LIKE / NOT_LIKE FILTER TESTS ====================

    public function test_filter_with_like_operator(): void
    {
        // Ajouter un cluster avec un nom
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_doe']));
        $collection->add(new ClusterVO(['name' => 'bob']));

        $result = $this->clusterQuery->filter($collection, 'name=~john');

        $this->assertCount(1, $result);
    }

    public function test_filter_with_not_like_operator(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_doe']));
        $collection->add(new ClusterVO(['name' => 'bob']));

        $result = $this->clusterQuery->filter($collection, 'name!~john');

        $this->assertCount(2, $result);
    }

    public function test_filter_with_like_and_condition(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe', 'status' => 'active']));
        $collection->add(new ClusterVO(['name' => 'john_doe', 'status' => 'inactive']));
        $collection->add(new ClusterVO(['name' => 'jane_doe', 'status' => 'active']));

        $result = $this->clusterQuery->filter($collection, 'name=~john & status=active');

        $this->assertCount(1, $result);
    }
}
