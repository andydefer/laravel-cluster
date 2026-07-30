<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Integration\Nodes;

use AndyDefer\LaravelCluster\Enums\ComparisonOperator;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Nodes\ConditionNode;
use AndyDefer\LaravelCluster\Tests\Fixtures\Models\TestCluster;
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;

final class ConditionNodeTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'admin',
                'age' => 25,
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => 85.5,
                'name' => 'john_doe',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'inactive',
                'role' => 'doctor',
                'age' => 30,
                'lang_fr' => 'false',
                'lang_en' => 'true',
                'verified' => 'false',
                'score' => 92.0,
                'name' => 'jane_smith',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'doctor',
                'age' => 35,
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => 78.0,
                'name' => 'bob_johnson',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'pending',
                'role' => 'guest',
                'age' => 18,
                'lang_fr' => 'false',
                'lang_en' => 'true',
                'verified' => 'false',
                'score' => 30.5,
                'name' => 'alice_johanson',
            ],
        ]);

        TestCluster::create([
            'clusters' => [
                'status' => 'active',
                'role' => 'admin',
                'age' => 40,
                'lang_fr' => 'true',
                'lang_en' => 'false',
                'verified' => 'true',
                'score' => 95.0,
                'name' => 'charlie_doe',
            ],
        ]);
    }

    // ==================== EVALUATE TESTS ====================

    public function test_evaluate_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $cluster = new ClusterVO(['status' => 'active']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_equals_false(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $cluster = new ClusterVO(['status' => 'inactive']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_equals_loose(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::EQUAL_LOOSE, '25');
        $cluster = new ClusterVO(['age' => '25']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_equals_strict(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::EQUAL_STRICT, '25');
        $cluster = new ClusterVO(['age' => '25']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_equals_strict_false(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::EQUAL_STRICT, '25');
        $cluster = new ClusterVO(['age' => 25]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_not_equal(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::NOT_EQUAL, 'inactive');
        $cluster = new ClusterVO(['status' => 'active']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_not_equal_false(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::NOT_EQUAL, 'inactive');
        $cluster = new ClusterVO(['status' => 'inactive']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_less_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::LESS_THAN, '30');
        $cluster = new ClusterVO(['age' => '25']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_less_than_false(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::LESS_THAN, '30');
        $cluster = new ClusterVO(['age' => '35']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_less_than_or_equal(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::LESS_THAN_OR_EQUAL, '30');
        $cluster = new ClusterVO(['age' => '30']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '30');
        $cluster = new ClusterVO(['age' => '35']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_greater_than_or_equal(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN_OR_EQUAL, '30');
        $cluster = new ClusterVO(['age' => '30']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_spaceship(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::SPACESHIP, '30');
        $cluster = new ClusterVO(['age' => '25']);

        $result = $node->evaluate($cluster);
        $this->assertEquals(-1, $result);
    }

    public function test_evaluate_presence_true(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EQUAL, 'true');
        $cluster = new ClusterVO(['lang_fr' => 'true']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_presence_false(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EQUAL, 'true');
        $cluster = new ClusterVO(['lang_fr' => 'false']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_absence(): void
    {
        $node = new ConditionNode('lang_en', ComparisonOperator::EQUAL, 'false');

        $cluster1 = new ClusterVO(['lang_fr' => 'true']);
        $this->assertTrue($node->evaluate($cluster1));

        $cluster2 = new ClusterVO(['lang_fr' => 'true', 'lang_en' => 'false']);
        $this->assertTrue($node->evaluate($cluster2));

        $cluster3 = new ClusterVO(['lang_fr' => 'true', 'lang_en' => 'true']);
        $this->assertFalse($node->evaluate($cluster3));
    }

    public function test_evaluate_key_not_exists(): void
    {
        $node = new ConditionNode('non_existent', ComparisonOperator::EQUAL, 'value');
        $cluster = new ClusterVO(['status' => 'active']);

        $this->assertFalse($node->evaluate($cluster));
    }

    // ==================== EXISTS / NOT_EXISTS EVALUATE TESTS ====================

    public function test_evaluate_exists_true(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EXISTS);
        $cluster = new ClusterVO(['lang_fr' => 'true']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_exists_false(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EXISTS);
        $cluster = new ClusterVO(['status' => 'active']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_not_exists_true(): void
    {
        $node = new ConditionNode('lang_es', ComparisonOperator::NOT_EXISTS);
        $cluster = new ClusterVO(['lang_fr' => 'true']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_not_exists_false(): void
    {
        $node = new ConditionNode('lang_es', ComparisonOperator::NOT_EXISTS);
        $cluster = new ClusterVO(['lang_es' => 'true']);

        $this->assertFalse($node->evaluate($cluster));
    }

    // ==================== LIKE EVALUATE TESTS ====================

    public function test_evaluate_like_simple_contains(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john');
        $cluster = new ClusterVO(['name' => 'john_doe']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_simple_contains_case_insensitive(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'JOHN');
        $cluster = new ClusterVO(['name' => 'John_Doe']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_pattern_starts_with(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john%');
        $cluster = new ClusterVO(['name' => 'john_doe']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_pattern_starts_with_false(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john%');
        $cluster = new ClusterVO(['name' => 'jane_doe']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_like_pattern_ends_with(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%doe');
        $cluster = new ClusterVO(['name' => 'john_doe']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_pattern_ends_with_false(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%doe');
        $cluster = new ClusterVO(['name' => 'john_doe_smith']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_like_pattern_contains(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%hn%');
        $cluster = new ClusterVO(['name' => 'john_doe']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_pattern_contains_false(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%xyz%');
        $cluster = new ClusterVO(['name' => 'john_doe']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_like_multiple_patterns_in_order(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%j%h%n');
        $cluster = new ClusterVO(['name' => 'johanson']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_multiple_patterns_in_order_false(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%j%n%h');
        $cluster = new ClusterVO(['name' => 'johanson']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_like_multiple_patterns_with_wildcards(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%j%a%n%');
        $cluster = new ClusterVO(['name' => 'johanson']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_multiple_patterns_case_insensitive(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%J%H%N');
        $cluster = new ClusterVO(['name' => 'Johanson']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_multiple_patterns_contains_all_in_order(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%a%o%');
        $cluster = new ClusterVO(['name' => 'johanson']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_multiple_patterns_all_ends(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%a%n');
        $cluster = new ClusterVO(['name' => 'johanson']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_multiple_patterns_all_starts(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'j%o%');
        $cluster = new ClusterVO(['name' => 'johanson']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_with_actual_not_string(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::LIKE, '25%');
        $cluster = new ClusterVO(['age' => 25]);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_like_with_value_not_string(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, null);
        $cluster = new ClusterVO(['name' => 'john_doe']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_like_with_empty_string(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '');
        $cluster = new ClusterVO(['name' => '']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_with_empty_string_and_pattern(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%');
        $cluster = new ClusterVO(['name' => '']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_with_key_not_exists(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john');
        $cluster = new ClusterVO(['status' => 'active']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_like_with_special_characters(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%john_doe%');
        $cluster = new ClusterVO(['name' => 'john_doe']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_with_underscore_pattern(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john_doe');
        $cluster = new ClusterVO(['name' => 'john_doe']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_multiple_patterns_with_empty(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%j%%h%%n%');
        $cluster = new ClusterVO(['name' => 'johanson']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_like_multiple_patterns_consecutive(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%j%h%n%');
        $cluster = new ClusterVO(['name' => 'johanson']);

        $this->assertTrue($node->evaluate($cluster));
    }

    // ==================== NOT_LIKE EVALUATE TESTS ====================

    public function test_evaluate_not_like_simple_contains(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::NOT_LIKE, 'john');
        $cluster = new ClusterVO(['name' => 'jane_doe']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_not_like_simple_contains_false(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::NOT_LIKE, 'john');
        $cluster = new ClusterVO(['name' => 'john_doe']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_not_like_pattern_starts_with(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::NOT_LIKE, 'john%');
        $cluster = new ClusterVO(['name' => 'jane_doe']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_not_like_pattern_ends_with(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::NOT_LIKE, '%doe');
        $cluster = new ClusterVO(['name' => 'john_doe_smith']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_not_like_multiple_patterns(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::NOT_LIKE, '%j%h%n');
        $cluster = new ClusterVO(['name' => 'johnson']);

        $this->assertFalse($node->evaluate($cluster));
    }

    public function test_evaluate_not_like_multiple_patterns_true(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::NOT_LIKE, '%j%n%h');
        $cluster = new ClusterVO(['name' => 'johnson']);

        $this->assertTrue($node->evaluate($cluster));
    }

    public function test_evaluate_not_like_with_actual_not_string(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::NOT_LIKE, '25%');
        $cluster = new ClusterVO(['age' => 25]);

        $this->assertTrue($node->evaluate($cluster));
    }

    // ==================== TO SQL TESTS ====================

    public function test_to_sql_mysql_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_EXTRACT(clusters, '$.\"status\"')) = LOWER('active')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_not_equal(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::NOT_EQUAL, 'inactive');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_EXTRACT(clusters, '$.\"status\"')) != LOWER('inactive')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '25');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "CAST(JSON_EXTRACT(clusters, '$.\"age\"') AS DECIMAL(10,2)) > 25";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_boolean_true(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EQUAL, 'true');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_EXTRACT(clusters, '$.\"lang_fr\"')) = LOWER('true')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_boolean_false(): void
    {
        $node = new ConditionNode('lang_en', ComparisonOperator::EQUAL, 'false');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_EXTRACT(clusters, '$.\"lang_en\"')) = LOWER('false')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_exists(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EXISTS);
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "JSON_EXTRACT(clusters, '$.\"lang_fr\"') IS NOT NULL";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_not_exists(): void
    {
        $node = new ConditionNode('lang_es', ComparisonOperator::NOT_EXISTS);
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "JSON_EXTRACT(clusters, '$.\"lang_es\"') IS NULL";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_like_contains(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_EXTRACT(clusters, '$.\"name\"')) LIKE LOWER('%john%')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_like_pattern_starts(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john%');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_EXTRACT(clusters, '$.\"name\"')) LIKE LOWER('john%')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_like_pattern_ends(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%doe');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_EXTRACT(clusters, '$.\"name\"')) LIKE LOWER('%doe')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_mysql_not_like(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::NOT_LIKE, 'john');
        $sql = $node->toSql('clusters', DatabaseDriver::MYSQL);

        $expected = "LOWER(JSON_EXTRACT(clusters, '$.\"name\"')) NOT LIKE LOWER('%john%')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_postgres_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $sql = $node->toSql('clusters', DatabaseDriver::PGSQL);

        $expected = "LOWER(clusters->>'status') = LOWER('active')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_postgres_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '25');
        $sql = $node->toSql('clusters', DatabaseDriver::PGSQL);

        $this->assertStringContainsString("(clusters->>'age')::numeric", $sql);
        $this->assertStringContainsString('> 25', $sql);
    }

    public function test_to_sql_postgres_exists(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EXISTS);
        $sql = $node->toSql('clusters', DatabaseDriver::PGSQL);

        $expected = "clusters->'lang_fr' IS NOT NULL";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_postgres_not_exists(): void
    {
        $node = new ConditionNode('lang_es', ComparisonOperator::NOT_EXISTS);
        $sql = $node->toSql('clusters', DatabaseDriver::PGSQL);

        $expected = "clusters->'lang_es' IS NULL";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_postgres_like(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john');
        $sql = $node->toSql('clusters', DatabaseDriver::PGSQL);

        $expected = "LOWER(clusters->>'name') LIKE LOWER('%john%')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_postgres_like_pattern(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john%');
        $sql = $node->toSql('clusters', DatabaseDriver::PGSQL);

        $expected = "LOWER(clusters->>'name') LIKE LOWER('john%')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_postgres_not_like(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::NOT_LIKE, 'john');
        $sql = $node->toSql('clusters', DatabaseDriver::PGSQL);

        $expected = "LOWER(clusters->>'name') NOT LIKE LOWER('%john%')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "LOWER(json_extract(clusters, '$.status')) = LOWER('active')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '25');
        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "CAST(json_extract(clusters, '$.age') AS NUMERIC) > 25";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_exists(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EXISTS);
        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "json_extract(clusters, '$.lang_fr') IS NOT NULL";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_not_exists(): void
    {
        $node = new ConditionNode('lang_es', ComparisonOperator::NOT_EXISTS);
        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "json_extract(clusters, '$.lang_es') IS NULL";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_like(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john');
        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "LOWER(json_extract(clusters, '$.name')) LIKE LOWER('%john%')";
        $this->assertEquals($expected, $sql);
    }

    public function test_to_sql_sqlite_not_like(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::NOT_LIKE, 'john');
        $sql = $node->toSql('clusters', DatabaseDriver::SQLITE);

        $expected = "LOWER(json_extract(clusters, '$.name')) NOT LIKE LOWER('%john%')";
        $this->assertEquals($expected, $sql);
    }

    // ==================== TO ELOQUENT TESTS ====================

    public function test_to_eloquent_mysql_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $sql = $query->toSql();
        // Vérifier que le SQL contient LOWER et = LOWER(?) pour l'insensibilité à la casse
        $this->assertStringContainsString('LOWER(JSON_EXTRACT(clusters', $sql);
        $this->assertStringContainsString('= LOWER(?)', $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_mysql_not_equal(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::NOT_EQUAL, 'inactive');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(4, $results);
    }

    public function test_to_eloquent_mysql_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '25');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_mysql_boolean_true(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EQUAL, 'true');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_mysql_boolean_false(): void
    {
        $node = new ConditionNode('lang_en', ComparisonOperator::EQUAL, 'false');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_postgres_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::PGSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString("LOWER(clusters->>'status')", $sql);
        $this->assertStringContainsString('= LOWER(?)', $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_sqlite_equals(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $sql = $query->toSql();
        $this->assertStringContainsString("LOWER(json_extract(clusters, '$.status'))", $sql);
        $this->assertStringContainsString('= LOWER(?)', $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_sqlite_greater_than(): void
    {
        $node = new ConditionNode('age', ComparisonOperator::GREATER_THAN, '25');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $sql = $query->toSql();
        $this->assertStringContainsString("json_extract(clusters, '$.age')", $sql);
        $this->assertStringContainsString('> ?', $sql);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    public function test_to_eloquent_mysql_exists(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EXISTS);
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString('IS NOT NULL', $sql);
        $this->assertStringContainsString('lang_fr', $sql);

        $results = $query->get();
        $this->assertCount(5, $results);
    }

    public function test_to_eloquent_mysql_not_exists(): void
    {
        $node = new ConditionNode('lang_es', ComparisonOperator::NOT_EXISTS);
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString('IS NULL', $sql);
        $this->assertStringContainsString('lang_es', $sql);

        $results = $query->get();
        $this->assertCount(5, $results);
    }

    public function test_to_eloquent_mysql_exists_with_condition(): void
    {
        $node = new ConditionNode('verified', ComparisonOperator::EXISTS);
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(5, $results);
    }

    public function test_to_eloquent_mysql_not_exists_with_condition(): void
    {
        $node = new ConditionNode('lang_es', ComparisonOperator::NOT_EXISTS);
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(5, $results);
    }

    public function test_to_eloquent_postgres_exists(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EXISTS);
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::PGSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString("->'lang_fr'", $sql);
        $this->assertStringContainsString('IS NOT NULL', $sql);
    }

    public function test_to_eloquent_sqlite_exists(): void
    {
        $node = new ConditionNode('lang_fr', ComparisonOperator::EXISTS);
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $sql = $query->toSql();
        $this->assertStringContainsString("json_extract(clusters, '$.lang_fr')", $sql);
        $this->assertStringContainsString('IS NOT NULL', $sql);
    }

    public function test_to_eloquent_mysql_like(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john%');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(1, $results);
    }

    public function test_to_eloquent_mysql_like_contains_multiple_patterns(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%j%h%n');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_to_eloquent_mysql_like_multiple_patterns_strict(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, '%j%n%h');
        $query = TestCluster::query();
        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertEquals('jane_smith', $results->first()->clusters['name']);
    }

    public function test_to_eloquent_mysql_not_like(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::NOT_LIKE, 'john%');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(4, $results);
    }

    public function test_to_eloquent_postgres_like(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john%');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::PGSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString('LOWER(clusters->>\'name\')', $sql);
        $this->assertStringContainsString('LIKE', $sql);
    }

    public function test_to_eloquent_sqlite_like(): void
    {
        $node = new ConditionNode('name', ComparisonOperator::LIKE, 'john%');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::SQLITE);

        $sql = $query->toSql();
        $this->assertStringContainsString('LOWER(json_extract(clusters', $sql);
        $this->assertStringContainsString('LIKE', $sql);
    }

    // ==================== COMPLEX CONDITIONS TESTS ====================

    public function test_to_eloquent_multiple_conditions(): void
    {
        $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'admin');

        $query = TestCluster::query();
        $node1->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);
        $node2->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_to_eloquent_with_or_condition(): void
    {
        $query = TestCluster::query();

        $query->where(function (Builder $q) {
            $node1 = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
            $node2 = new ConditionNode('role', ComparisonOperator::EQUAL, 'doctor');

            $node1->toEloquent($q, 'clusters', DatabaseDriver::MYSQL);
            $q->orWhere(function (Builder $sub) use ($node2) {
                $node2->toEloquent($sub, 'clusters', DatabaseDriver::MYSQL);
            });
        });

        $results = $query->get();
        $this->assertCount(4, $results);
    }

    public function test_to_eloquent_with_numeric_comparison(): void
    {
        $node = new ConditionNode('score', ComparisonOperator::GREATER_THAN, '80');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(3, $results);
    }

    // ==================== EDGE CASES TESTS ====================

    public function test_to_eloquent_with_null_value(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, null);
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $results = $query->get();
        $this->assertCount(0, $results);
    }

    public function test_to_eloquent_default_driver(): void
    {
        $node = new ConditionNode('status', ComparisonOperator::EQUAL, 'active');
        $query = TestCluster::query();

        $node->toEloquent($query, 'clusters', DatabaseDriver::MYSQL);

        $sql = $query->toSql();
        $this->assertStringContainsString('LOWER(JSON_EXTRACT', $sql);
    }

    public function test_get_json_path(): void
    {
        $node = new ConditionNode('test_key', ComparisonOperator::EQUAL, 'value');
        $reflection = new \ReflectionClass($node);
        $method = $reflection->getMethod('getJsonPath');
        $method->setAccessible(true);

        $result = $method->invoke($node);
        $this->assertEquals('$."test_key"', $result);
    }
}
