<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Collections;

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Tests\IntegrationTestCase;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

final class AggregateWhereTest extends IntegrationTestCase
{
    public function test_where_aggregate_with_count(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b', 'c'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => ['a', 'b'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => ['a'],
        ]));

        $result = $collection->whereAggregate('{COUNT(addresses) > 2}');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->first()->get('name'));
    }

    public function test_where_aggregate_with_avg(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => [80, 90, 85],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'scores' => [50, 60],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'scores' => [95],
        ]));

        $result = $collection->whereAggregate('{AVG(scores) >= 85}');

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Bob', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_sum(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'prices' => [100, 200, 300],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'prices' => [50, 100],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'prices' => [500],
        ]));

        $result = $collection->whereAggregate('{SUM(prices) > 400}');

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Bob', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_min(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => [80, 90, 85],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'scores' => [50, 60],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'scores' => [95, 98, 92],
        ]));

        $result = $collection->whereAggregate('{MIN(scores) > 60}');

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Bob', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_max(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => [80, 90, 85],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'scores' => [50, 60],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'scores' => [95, 98, 92],
        ]));

        $result = $collection->whereAggregate('{MAX(scores) <= 90}');

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Jane', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_length(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John Doe',
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob Johnson',
        ]));

        $result = $collection->whereAggregate('{LENGTH(name) > 5}');

        $this->assertCount(2, $result);
        $this->assertEquals('John Doe', $result->first()->get('name'));
        $this->assertEquals('Bob Johnson', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_exists(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'profile' => ['age' => 30],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'profile' => ['age' => 35],
        ]));

        $result = $collection->whereAggregate('{EXISTS(profile)}');

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Bob', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_is_empty(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'cart' => ['item1', 'item2'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'cart' => [],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'cart' => ['item3'],
        ]));

        $result = $collection->whereAggregate('{IS_EMPTY(cart)}');

        $this->assertCount(1, $result);
        $this->assertEquals('Jane', $result->first()->get('name'));
    }

    public function test_where_aggregate_with_has(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'tags' => ['php', 'javascript', 'docker'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'tags' => ['python'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'tags' => ['php', 'laravel'],
        ]));

        $result = $collection->whereAggregate('{HAS(tags, "php")}');

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Bob', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_has_on_object_array(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => [
                ['city' => 'Kinshasa', 'country' => 'RDC'],
                ['city' => 'Paris', 'country' => 'France'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => [
                ['city' => 'Paris', 'country' => 'France'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => [
                ['city' => 'Kinshasa', 'country' => 'RDC'],
                ['city' => 'London', 'country' => 'UK'],
            ],
        ]));

        $result = $collection->whereAggregate('{HAS(addresses, city, "Kinshasa")}');

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Bob', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_all(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => [
                ['city' => 'Kinshasa', 'country' => 'RDC'],
                ['city' => 'Lubumbashi', 'country' => 'RDC'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => [
                ['city' => 'Paris', 'country' => 'France'],
                ['city' => 'Kinshasa', 'country' => 'RDC'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => [
                ['city' => 'Kinshasa', 'country' => 'RDC'],
                ['city' => 'London', 'country' => 'UK'],
            ],
        ]));

        $result = $collection->whereAggregate('{ALL(addresses, country, "RDC")}');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->first()->get('name'));
    }

    public function test_where_aggregate_with_matches(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'tags' => ['php', 'javascript', 'docker'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'tags' => ['python', 'django'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'tags' => ['php', 'laravel', 'vuejs'],
        ]));

        $result = $collection->whereAggregate('{MATCHES(tags, "/^ja.*/")}');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->first()->get('name'));
    }

    public function test_where_aggregate_with_matches_on_object_array(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => [
                ['city' => 'Kinshasa', 'country' => 'RDC'],
                ['city' => 'Paris', 'country' => 'France'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => [
                ['city' => 'Paris', 'country' => 'France'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => [
                ['city' => 'Kinshasa', 'country' => 'RDC'],
                ['city' => 'London', 'country' => 'UK'],
            ],
        ]));

        $result = $collection->whereAggregate('{MATCHES(addresses, city, "/^Kin.*/")}');

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Bob', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_matches_case_insensitive(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'tags' => ['PHP', 'JavaScript', 'Docker'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'tags' => ['python', 'django'],
        ]));

        $result = $collection->whereAggregate('{MATCHES(tags, "/^ja.*/i")}');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->first()->get('name'));
    }

    public function test_where_aggregate_with_complex_combination(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b', 'c'],
            'scores' => [80, 90, 85],
            'tags' => ['php', 'javascript'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => ['a', 'b'],
            'scores' => [70, 75, 80],
            'tags' => ['python'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => ['a'],
            'scores' => [95, 98, 92],
            'tags' => ['php', 'laravel'],
        ]));

        $result = $collection->whereAggregate(
            '{COUNT(addresses) > 1} & {AVG(scores) >= 85} & {HAS(tags, "php")}'
        );

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->first()->get('name'));
    }

    public function test_where_aggregate_with_or_combination(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'tags' => ['php'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'tags' => ['python'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'tags' => ['javascript'],
        ]));

        $result = $collection->whereAggregate(
            '{HAS(tags, "php")} | {HAS(tags, "javascript")}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Bob', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_nested_complex(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b', 'c'],
            'scores' => [80, 90, 85],
            'tags' => ['php'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => ['a', 'b'],
            'scores' => [70, 75, 80],
            'tags' => ['python'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => ['a', 'b'],
            'scores' => [95, 98, 92],
            'tags' => ['javascript'],
        ]));

        // ✅ Utiliser GROUP au lieu des parenthèses
        $result = $collection->whereAggregate(
            '{GROUP({COUNT(addresses) > 1} & {AVG(scores) >= 85})} | {HAS(tags, "php")}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Bob', $result->last()->get('name'));
    }

    public function test_where_aggregate_direct(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b', 'c'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => ['a', 'b'],
        ]));

        $result = $collection->whereAggregateDirect('COUNT', ['addresses']);

        $this->assertCount(2, $result);
    }

    public function test_matches_aggregate(): void
    {
        $collection = new ClusterVOCollection;

        $cluster = new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b', 'c'],
        ]);

        $matches = $collection->matchesAggregate($cluster, '{COUNT(addresses) > 2}');

        $this->assertTrue($matches);
    }

    public function test_get_aggregate_value(): void
    {
        $collection = new ClusterVOCollection;

        $cluster = new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b', 'c'],
            'scores' => [80, 90, 85],
            'tags' => ['php', 'javascript'],
        ]);

        $count = $collection->getAggregateValue($cluster, 'COUNT', ['addresses']);
        $avg = $collection->getAggregateValue($cluster, 'AVG', ['scores']);
        $hasPhp = $collection->getAggregateValue($cluster, 'HAS', ['tags', 'php']);

        $this->assertEquals(3, $count);
        $this->assertEquals(85.0, $avg);
        $this->assertTrue($hasPhp);
    }

    // ==================== TESTS AVEC GROUP FUNCTION ====================

    public function test_where_aggregate_with_group_function(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b', 'c'],
            'scores' => [80, 90, 85],
            'tags' => ['php'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => ['a', 'b'],
            'scores' => [70, 75, 80],
            'tags' => ['python'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => ['a', 'b'],
            'scores' => [95, 98, 92],
            'tags' => ['javascript'],
        ]));

        $result = $collection->whereAggregate(
            '{GROUP({COUNT(addresses) > 1} & {AVG(scores) >= 85})} | {HAS(tags, "php")}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Bob', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_group_and_operator(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'tags' => ['php'],
            'scores' => [80],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'tags' => ['python'],
            'scores' => [90],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'tags' => ['javascript'],
            'scores' => [70],
        ]));

        $result = $collection->whereAggregate(
            '{COUNT(scores) > 0} & {GROUP({HAS(tags, "php")} | {HAS(tags, "javascript")})}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Bob', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_nested_group_functions(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b', 'c'],
            'scores' => [80, 90, 85],
            'tags' => ['php'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => ['a', 'b'],
            'scores' => [70, 75, 80],
            'tags' => ['python'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => ['a', 'b'],
            'scores' => [95, 98, 92],
            'tags' => ['javascript', 'php'],
        ]));

        $result = $collection->whereAggregate(
            '{GROUP({GROUP({COUNT(addresses) > 1} & {AVG(scores) >= 85})} | {HAS(tags, "php")})} & {COUNT(addresses) > 0}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Bob', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_multiple_group_functions(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => ['a', 'b', 'c'],
            'scores' => [80, 90, 85],
            'tags' => ['php'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => ['a', 'b'],
            'scores' => [70, 75, 80],
            'tags' => ['python'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => ['a', 'b'],
            'scores' => [95, 98, 92],
            'tags' => ['javascript'],
        ]));

        $result = $collection->whereAggregate(
            '{GROUP({COUNT(addresses) > 1} & {AVG(scores) >= 85})} | {GROUP({COUNT(addresses) > 0} & {HAS(tags, "php")})}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result->first()->get('name'));
        $this->assertEquals('Bob', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_group_and_multiple_args_functions(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'John',
            'addresses' => [
                ['city' => 'Kinshasa', 'country' => 'RDC'],
                ['city' => 'Paris', 'country' => 'France'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'addresses' => [
                ['city' => 'Paris', 'country' => 'France'],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'addresses' => [
                ['city' => 'Kinshasa', 'country' => 'RDC'],
            ],
        ]));

        $result = $collection->whereAggregate(
            '{GROUP({HAS(addresses, city, "Kinshasa")})} & {COUNT(addresses) > 1}'
        );

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->first()->get('name'));
    }

    // ==================== TESTS AVEC DISTANCE FUNCTION ====================

    public function test_where_aggregate_with_distance(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'Paris Pharmacy',
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Lyon Pharmacy',
            'coordinates' => [
                'latitude' => 45.7640,
                'longitude' => 4.8357,
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Marseille Pharmacy',
            'coordinates' => [
                'latitude' => 43.2965,
                'longitude' => 5.3698,
            ],
        ]));

        $result = $collection->whereAggregate(
            '{DISTANCE(coordinates, 48.8566, 2.3522, km) < 500}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('Paris Pharmacy', $result->first()->get('name'));
        $this->assertEquals('Lyon Pharmacy', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_distance_and_condition(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'Paris Pharmacy',
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
            'status' => 'active',
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Lyon Pharmacy',
            'coordinates' => [
                'latitude' => 45.7640,
                'longitude' => 4.8357,
            ],
            'status' => 'inactive',
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Marseille Pharmacy',
            'coordinates' => [
                'latitude' => 43.2965,
                'longitude' => 5.3698,
            ],
            'status' => 'active',
        ]));

        $result = $collection
            ->whereAggregate('{DISTANCE(coordinates, 48.8566, 2.3522, km) < 500}')
            ->where('status', 'active');

        $this->assertCount(1, $result);
        $this->assertEquals('Paris Pharmacy', $result->first()->get('name'));
    }

    public function test_where_aggregate_with_distance_in_meters(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'Paris Pharmacy',
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Lyon Pharmacy',
            'coordinates' => [
                'latitude' => 45.7640,
                'longitude' => 4.8357,
            ],
        ]));

        // Paris-Lyon ≈ 391 000 mètres
        $result = $collection->whereAggregate(
            '{DISTANCE(coordinates, 48.8566, 2.3522, m) < 400000}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('Paris Pharmacy', $result->first()->get('name'));
        $this->assertEquals('Lyon Pharmacy', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_distance_and_group(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'Paris Pharmacy',
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
            'status' => 'active',
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Lyon Pharmacy',
            'coordinates' => [
                'latitude' => 45.7640,
                'longitude' => 4.8357,
            ],
            'status' => 'active',
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Marseille Pharmacy',
            'coordinates' => [
                'latitude' => 43.2965,
                'longitude' => 5.3698,
            ],
            'status' => 'inactive',
        ]));

        $result = $collection->whereAggregate(
            '{GROUP({DISTANCE(coordinates, 48.8566, 2.3522, km) < 500} & status=active)}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('Paris Pharmacy', $result->first()->get('name'));
        $this->assertEquals('Lyon Pharmacy', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_distance_and_has(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'Paris Pharmacy',
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
            'services' => ['delivery', 'consultation'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Lyon Pharmacy',
            'coordinates' => [
                'latitude' => 45.7640,
                'longitude' => 4.8357,
            ],
            'services' => ['consultation'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Marseille Pharmacy',
            'coordinates' => [
                'latitude' => 43.2965,
                'longitude' => 5.3698,
            ],
            'services' => ['delivery'],
        ]));

        $result = $collection->whereAggregate(
            '{DISTANCE(coordinates, 48.8566, 2.3522, km) < 500} & {HAS(services, "delivery")}'
        );

        $this->assertCount(1, $result);
        $this->assertEquals('Paris Pharmacy', $result->first()->get('name'));
    }

    public function test_where_aggregate_with_distance_no_results(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'Paris Pharmacy',
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Lyon Pharmacy',
            'coordinates' => [
                'latitude' => 45.7640,
                'longitude' => 4.8357,
            ],
        ]));

        // Distance < 1km de Paris -> seulement Paris si elle était incluse
        $result = $collection->whereAggregate(
            '{DISTANCE(coordinates, 48.8566, 2.3522, km) < 1}'
        );

        $this->assertCount(1, $result);
        $this->assertEquals('Paris Pharmacy', $result->first()->get('name'));
    }

    public function test_where_aggregate_with_distance_and_count(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'Paris Pharmacy',
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
            'addresses' => ['a', 'b', 'c'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Lyon Pharmacy',
            'coordinates' => [
                'latitude' => 45.7640,
                'longitude' => 4.8357,
            ],
            'addresses' => ['a', 'b'],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Marseille Pharmacy',
            'coordinates' => [
                'latitude' => 43.2965,
                'longitude' => 5.3698,
            ],
            'addresses' => ['a'],
        ]));

        $result = $collection->whereAggregate(
            '{DISTANCE(coordinates, 48.8566, 2.3522, km) < 500} & {COUNT(addresses) > 1}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('Paris Pharmacy', $result->first()->get('name'));
        $this->assertEquals('Lyon Pharmacy', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_nested_coordinates(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'Paris Pharmacy',
            'location' => [
                'coordinates' => [
                    'latitude' => 48.8566,
                    'longitude' => 2.3522,
                ],
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Lyon Pharmacy',
            'location' => [
                'coordinates' => [
                    'latitude' => 45.7640,
                    'longitude' => 4.8357,
                ],
            ],
        ]));

        $result = $collection->whereAggregate(
            '{DISTANCE(location.coordinates, 48.8566, 2.3522, km) < 500}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('Paris Pharmacy', $result->first()->get('name'));
        $this->assertEquals('Lyon Pharmacy', $result->last()->get('name'));
    }

    public function test_where_aggregate_with_distance_between_300_and_500_km(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'Paris Pharmacy',
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Lyon Pharmacy',
            'coordinates' => [
                'latitude' => 45.7640,
                'longitude' => 4.8357,
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Marseille Pharmacy',
            'coordinates' => [
                'latitude' => 43.2965,
                'longitude' => 5.3698,
            ],
        ]));

        $result = $collection->whereAggregate(
            '{DISTANCE(coordinates, 48.8566, 2.3522, km) > 300} & {DISTANCE(coordinates, 48.8566, 2.3522, km) < 500}'
        );

        $this->assertCount(1, $result);
        $this->assertEquals('Lyon Pharmacy', $result->first()->get('name'));
    }
}
