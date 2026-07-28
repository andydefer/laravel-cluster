<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Collections;

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use PHPUnit\Framework\TestCase;

final class ClusterVOCollectionTest extends TestCase
{
    private ClusterVOCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = new ClusterVOCollection;

        $this->collection->add(new ClusterVO([
            'id' => 1,
            'status' => 'active',
            'role' => 'admin',
            'verified' => 'true',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'age' => 25,
            'name' => 'John Doe',
        ]));

        $this->collection->add(new ClusterVO([
            'id' => 2,
            'status' => 'active',
            'role' => 'doctor',
            'verified' => 'true',
            'lang_fr' => 'false',
            'lang_en' => 'true',
            'age' => 30,
            'name' => 'Jane Smith',
        ]));

        $this->collection->add(new ClusterVO([
            'id' => 3,
            'status' => 'inactive',
            'role' => 'admin',
            'verified' => 'false',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'age' => 22,
            'name' => 'Bob Johnson',
        ]));

        $this->collection->add(new ClusterVO([
            'id' => 4,
            'status' => 'pending',
            'role' => 'guest',
            'verified' => 'false',
            'lang_fr' => 'false',
            'lang_en' => 'true',
            'age' => 18,
            'name' => 'Alice Brown',
        ]));

        $this->collection->add(new ClusterVO([
            'id' => 5,
            'status' => 'active',
            'role' => 'admin',
            'verified' => 'true',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'age' => 40,
            'name' => 'Charlie Wilson',
        ]));
    }

    // ==================== WHERE TESTS ====================

    public function test_where(): void
    {
        $result = $this->collection->where('status', 'active');

        $this->assertCount(3, $result);
        $this->assertEquals('active', $result->first()?->get('status'));
    }

    public function test_and_where(): void
    {
        $result = $this->collection
            ->andWhere('status', 'active')
            ->andWhere('role', 'admin');

        $this->assertCount(2, $result);
        $this->assertEquals('admin', $result->first()?->get('role'));
    }

    public function test_where_not(): void
    {
        $result = $this->collection->whereNot('status', 'inactive');

        $this->assertCount(4, $result);
        $this->assertNotEquals('inactive', $result->first()?->get('status'));
    }

    public function test_where_true(): void
    {
        $result = $this->collection->whereTrue('verified');

        $this->assertCount(3, $result);
        $this->assertEquals('true', $result->first()?->get('verified'));
    }

    public function test_where_false(): void
    {
        $result = $this->collection->whereFalse('verified');

        $this->assertCount(2, $result);
        $this->assertEquals('false', $result->first()?->get('verified'));
    }

    public function test_or_where(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->orWhere('status', 'pending');

        $this->assertCount(4, $result);
        $this->assertContains($result->first()?->get('status'), ['active', 'pending']);
    }

    // ==================== GROUP TESTS ====================

    public function test_where_group(): void
    {
        $result = $this->collection->whereGroup(function (ClusterVOCollection $q) {
            return $q->where('status', 'active')
                ->orWhere('status', 'pending');
        });

        $this->assertCount(4, $result);
    }

    public function test_where_group_with_and_condition(): void
    {
        $result = $this->collection
            ->whereGroup(function (ClusterVOCollection $q) {
                return $q->where('status', 'active')
                    ->orWhere('status', 'pending');
            })
            ->andWhere('role', 'admin');

        $this->assertCount(2, $result);
        $this->assertEquals('admin', $result->first()?->get('role'));
    }

    public function test_where_group_nested(): void
    {
        $result = $this->collection->whereGroup(function (ClusterVOCollection $q) {
            return $q->whereGroup(function (ClusterVOCollection $q2) {
                return $q2->where('status', 'active')
                    ->where('role', 'admin');
            })->orWhereGroup(function (ClusterVOCollection $q2) {
                return $q2->where('status', 'active')
                    ->where('role', 'doctor');
            });
        });

        $this->assertCount(3, $result);
    }

    public function test_or_where_group_without_prior_filter(): void
    {
        $result = $this->collection->orWhereGroup(function (ClusterVOCollection $q) {
            return $q->where('role', 'admin')
                ->where('verified', 'true');
        });

        $this->assertCount(2, $result);
    }

    public function test_or_where_group_with_prior_filter(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->orWhereGroup(function (ClusterVOCollection $q) {
                return $q->where('role', 'admin')
                    ->where('verified', 'true');
            });

        $this->assertCount(3, $result);
    }

    public function test_chain_with_or_where_after_group(): void
    {
        $result = $this->collection
            ->whereGroup(function (ClusterVOCollection $q) {
                return $q->where('status', 'active')
                    ->where('role', 'admin');
            })
            ->orWhere('status', 'pending');

        $this->assertCount(3, $result);
    }

    public function test_or_where_group_with_multiple_conditions(): void
    {
        $result = $this->collection
            ->whereGroup(function (ClusterVOCollection $q) {
                return $q->where('status', 'active');
            })
            ->orWhereGroup(function (ClusterVOCollection $q) {
                return $q->where('role', 'admin')
                    ->where('verified', 'true');
            });

        $this->assertCount(3, $result);
    }

    public function test_nested_groups(): void
    {
        $result = $this->collection->whereGroup(function (ClusterVOCollection $q) {
            return $q->whereGroup(function (ClusterVOCollection $q2) {
                return $q2->where('status', 'active')
                    ->where('role', 'admin');
            })->orWhereGroup(function (ClusterVOCollection $q2) {
                return $q2->where('status', 'active')
                    ->where('role', 'doctor');
            });
        })->andWhere('verified', 'true');

        $this->assertCount(3, $result);
    }

    public function test_complex_chaining_with_groups(): void
    {
        $result = $this->collection
            ->whereGroup(function (ClusterVOCollection $q) {
                return $q->where('status', 'active')
                    ->orWhere('status', 'pending');
            })
            ->andWhere('role', 'admin')
            ->whereTrue('verified')
            ->whereGreaterThanOrEqual('age', 25);

        $this->assertCount(2, $result);
    }

    public function test_complex_chaining_with_or_groups(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->orWhereGroup(function (ClusterVOCollection $q) {
                return $q->where('status', 'pending')
                    ->where('role', 'guest');
            })
            ->andWhere('verified', 'true');

        $this->assertCount(3, $result);
    }

    // ==================== WHERE HAS / MISSING TESTS ====================

    public function test_where_has(): void
    {
        $result = $this->collection->whereHas('lang_fr');

        $this->assertCount(5, $result);
        $this->assertTrue($result->first()?->has('lang_fr'));
    }

    public function test_where_missing(): void
    {
        $result = $this->collection->whereMissing('deleted');

        $this->assertCount(5, $result);
        $this->assertFalse($result->first()?->has('deleted'));
    }

    // ==================== WHERE IN / NOT IN TESTS ====================

    public function test_where_in(): void
    {
        $result = $this->collection->whereIn('role', ['admin', 'doctor']);

        $this->assertCount(4, $result);
        $this->assertContains($result->first()?->get('role'), ['admin', 'doctor']);
    }

    public function test_where_not_in(): void
    {
        $result = $this->collection->whereNotIn('status', ['active', 'pending']);

        $this->assertCount(1, $result);
        $this->assertEquals('inactive', $result->first()?->get('status'));
    }

    // ==================== NUMERIC COMPARISON TESTS ====================

    public function test_where_greater_than(): void
    {
        $result = $this->collection->whereGreaterThan('age', 25);

        $this->assertCount(2, $result);
        $this->assertGreaterThan(25, $result->first()?->get('age'));
    }

    public function test_where_greater_than_or_equal(): void
    {
        $result = $this->collection->whereGreaterThanOrEqual('age', 25);

        $this->assertCount(3, $result);
        $this->assertGreaterThanOrEqual(25, $result->first()?->get('age'));
    }

    public function test_where_less_than(): void
    {
        $result = $this->collection->whereLessThan('age', 25);

        $this->assertCount(2, $result);
        $this->assertLessThan(25, $result->first()?->get('age'));
    }

    public function test_where_less_than_or_equal(): void
    {
        $result = $this->collection->whereLessThanOrEqual('age', 25);

        $this->assertCount(3, $result);
        $this->assertLessThanOrEqual(25, $result->first()?->get('age'));
    }

    // ==================== WHERE BETWEEN TESTS ====================

    public function test_where_between(): void
    {
        $result = $this->collection->whereBetween('age', 20, 30);

        $this->assertCount(3, $result);
        $this->assertTrue($result->first()?->get('age') >= 20 && $result->first()?->get('age') <= 30);
    }

    public function test_where_not_between(): void
    {
        $result = $this->collection->whereNotBetween('age', 20, 30);

        $this->assertCount(2, $result);
        $this->assertTrue($result->first()?->get('age') < 20 || $result->first()?->get('age') > 30);
    }

    // ==================== WHERE NULL / NOT NULL TESTS ====================

    public function test_where_null(): void
    {
        $result = $this->collection->whereNull('age');

        $this->assertCount(0, $result);
    }

    public function test_where_not_null(): void
    {
        $result = $this->collection->whereNotNull('age');

        $this->assertCount(5, $result);
        $this->assertNotNull($result->first()?->get('age'));
    }

    // ==================== STRING SEARCH TESTS ====================

    public function test_where_contains(): void
    {
        $result = $this->collection->whereContains('name', 'Bob');

        $this->assertCount(1, $result);
        $name = $result->first()?->get('name');
        $this->assertIsString($name);
        $this->assertStringContainsString('Bob', (string) $name);
    }

    public function test_where_starts_with(): void
    {
        $result = $this->collection->whereStartsWith('name', 'J');

        $this->assertCount(2, $result);
        $name = $result->first()?->get('name');
        $this->assertIsString($name);
        $this->assertStringStartsWith('J', (string) $name);
    }

    public function test_where_ends_with(): void
    {
        $result = $this->collection->whereEndsWith('name', 'n');

        $this->assertCount(3, $result);
        $name = $result->first()?->get('name');
        $this->assertIsString($name);
        $this->assertStringEndsWith('n', (string) $name);
    }

    // ==================== WHERE CLOSURE TESTS ====================

    public function test_where_closure(): void
    {
        $result = $this->collection->whereClosure(
            fn (ClusterVO $cluster) => $cluster->get('age') > 25 && $cluster->get('role') === 'admin'
        );

        $this->assertCount(1, $result);
        $this->assertEquals('Charlie Wilson', $result->first()?->get('name'));
    }

    public function test_or_where_closure_without_prior_filter(): void
    {
        $result = $this->collection->orWhereClosure(
            fn (ClusterVO $cluster) => $cluster->get('role') === 'admin'
        );

        $this->assertCount(3, $result);
        $this->assertEquals('admin', $result->first()?->get('role'));
    }

    public function test_or_where_closure_with_prior_filter(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->orWhereClosure(
                fn (ClusterVO $cluster) => $cluster->get('role') === 'admin'
            );

        $this->assertCount(3, $result);
    }

    public function test_where_closure_with_group(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->whereClosure(
                fn (ClusterVO $cluster) => $cluster->get('age') >= 25 && $cluster->get('verified') === 'true'
            );

        $this->assertCount(3, $result);
    }

    // ==================== FIRST WHERE TESTS ====================

    public function test_first_where(): void
    {
        $result = $this->collection->firstWhere('role', 'admin');

        $this->assertNotNull($result);
        $this->assertEquals('admin', $result->get('role'));
        $this->assertEquals(1, $result->get('id'));
    }

    public function test_first_where_not_found(): void
    {
        $result = $this->collection->firstWhere('role', 'super_admin');

        $this->assertNull($result);
    }

    // ==================== GET TESTS ====================

    public function test_get(): void
    {
        $result = $this->collection->get();

        $this->assertCount(5, $result);
        $this->assertIsArray($result);
    }

    // ==================== EDGE CASES TESTS ====================

    public function test_empty_collection(): void
    {
        $emptyCollection = new ClusterVOCollection;

        $result = $emptyCollection->where('status', 'active');

        $this->assertCount(0, $result);
        $this->assertEmpty($result->get());
    }

    public function test_non_existent_key(): void
    {
        $result = $this->collection->where('non_existent_key', 'value');

        $this->assertCount(0, $result);
    }

    public function test_null_values(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['key' => null]));

        $result = $collection->whereNull('key');

        $this->assertCount(1, $result);
        $this->assertEquals(null, $result->first()?->get('key'));
    }

    public function test_numeric_strings(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['age' => '25']));
        $collection->add(new ClusterVO(['age' => 30]));

        $result = $collection->where('age', 30);

        $this->assertCount(1, $result);
        $this->assertEquals(30, $result->first()?->get('age'));
    }

    public function test_where_with_boolean_values(): void
    {
        $result = $this->collection
            ->whereTrue('verified')
            ->whereFalse('lang_en');

        $this->assertCount(2, $result);
        $this->assertEquals('true', $result->first()?->get('verified'));
        $this->assertEquals('false', $result->first()?->get('lang_en'));
    }

    public function test_where_in_with_empty_array(): void
    {
        $result = $this->collection->whereIn('role', []);

        $this->assertCount(0, $result);
    }

    public function test_where_not_in_with_empty_array(): void
    {
        $result = $this->collection->whereNotIn('role', []);

        $this->assertCount(5, $result);
    }

    public function test_where_between_with_invalid_values(): void
    {
        $result = $this->collection->whereBetween('role', 'a', 'z');

        $this->assertCount(0, $result);
    }

    public function test_chain_with_where_not_after_group(): void
    {
        $result = $this->collection
            ->whereGroup(function (ClusterVOCollection $q) {
                return $q->where('status', 'active')
                    ->orWhere('status', 'pending');
            })
            ->whereNot('role', 'guest');

        $this->assertCount(3, $result);
    }
}
