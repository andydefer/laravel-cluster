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

    public function test_where_returns_collection_with_matching_items(): void
    {
        $result = $this->collection->where('status', 'active');

        $this->assertCount(3, $result);
        $this->assertEquals('active', $result->first()?->get('status'));
    }

    public function test_and_where_filters_collection_with_multiple_conditions(): void
    {
        $result = $this->collection
            ->andWhere('status', 'active')
            ->andWhere('role', 'admin');

        $this->assertCount(2, $result);
        $this->assertEquals('admin', $result->first()?->get('role'));
    }

    public function test_where_not_excludes_items_with_matching_value(): void
    {
        $result = $this->collection->whereNot('status', 'inactive');

        $this->assertCount(4, $result);
        $this->assertNotEquals('inactive', $result->first()?->get('status'));
    }

    public function test_where_true_returns_items_with_true_value(): void
    {
        $result = $this->collection->whereTrue('verified');

        $this->assertCount(3, $result);
        $this->assertEquals('true', $result->first()?->get('verified'));
    }

    public function test_where_false_returns_items_with_false_value(): void
    {
        $result = $this->collection->whereFalse('verified');

        $this->assertCount(2, $result);
        $this->assertEquals('false', $result->first()?->get('verified'));
    }

    public function test_or_where_combines_filters_with_or_logic(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->orWhere('status', 'pending');

        $this->assertCount(4, $result);
        $this->assertContains($result->first()?->get('status'), ['active', 'pending']);
    }

    public function test_where_group_applies_conditions_as_a_group(): void
    {
        $result = $this->collection->whereGroup(function (ClusterVOCollection $q) {
            return $q->where('status', 'active')
                ->orWhere('status', 'pending');
        });

        $this->assertCount(4, $result);
    }

    public function test_where_group_with_and_condition_combines_group_with_outer_condition(): void
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

    public function test_where_group_nested_supports_nested_group_conditions(): void
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

    public function test_or_where_group_without_prior_filter_applies_or_group_to_full_collection(): void
    {
        $result = $this->collection->orWhereGroup(function (ClusterVOCollection $q) {
            return $q->where('role', 'admin')
                ->where('verified', 'true');
        });

        $this->assertCount(2, $result);
    }

    public function test_or_where_group_with_prior_filter_combines_filters_with_or(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->orWhereGroup(function (ClusterVOCollection $q) {
                return $q->where('role', 'admin')
                    ->where('verified', 'true');
            });

        $this->assertCount(3, $result);
    }

    public function test_chain_with_or_where_after_group_combines_group_and_single_condition(): void
    {
        $result = $this->collection
            ->whereGroup(function (ClusterVOCollection $q) {
                return $q->where('status', 'active')
                    ->where('role', 'admin');
            })
            ->orWhere('status', 'pending');

        $this->assertCount(3, $result);
    }

    public function test_or_where_group_with_multiple_conditions_combines_multiple_or_groups(): void
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

    public function test_nested_groups_support_deep_nesting_of_conditions(): void
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

    public function test_complex_chaining_with_groups_supports_multiple_group_operations(): void
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

    public function test_complex_chaining_with_or_groups_supports_or_groups_in_chain(): void
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

    public function test_where_has_returns_items_with_existing_key(): void
    {
        $result = $this->collection->whereHas('lang_fr');

        $this->assertCount(5, $result);
        $this->assertTrue($result->first()?->has('lang_fr'));
    }

    public function test_where_missing_returns_items_without_key(): void
    {
        $result = $this->collection->whereMissing('deleted');

        $this->assertCount(5, $result);
        $this->assertFalse($result->first()?->has('deleted'));
    }

    public function test_where_in_returns_items_with_value_in_array(): void
    {
        $result = $this->collection->whereIn('role', ['admin', 'doctor']);

        $this->assertCount(4, $result);
        $this->assertContains($result->first()?->get('role'), ['admin', 'doctor']);
    }

    public function test_where_not_in_excludes_items_with_value_in_array(): void
    {
        $result = $this->collection->whereNotIn('status', ['active', 'pending']);

        $this->assertCount(1, $result);
        $this->assertEquals('inactive', $result->first()?->get('status'));
    }

    public function test_where_greater_than_returns_items_with_value_greater_than_threshold(): void
    {
        $result = $this->collection->whereGreaterThan('age', 25);

        $this->assertCount(2, $result);
        $this->assertGreaterThan(25, $result->first()?->get('age'));
    }

    public function test_where_greater_than_or_equal_returns_items_with_value_greater_than_or_equal_threshold(): void
    {
        $result = $this->collection->whereGreaterThanOrEqual('age', 25);

        $this->assertCount(3, $result);
        $this->assertGreaterThanOrEqual(25, $result->first()?->get('age'));
    }

    public function test_where_less_than_returns_items_with_value_less_than_threshold(): void
    {
        $result = $this->collection->whereLessThan('age', 25);

        $this->assertCount(2, $result);
        $this->assertLessThan(25, $result->first()?->get('age'));
    }

    public function test_where_less_than_or_equal_returns_items_with_value_less_than_or_equal_threshold(): void
    {
        $result = $this->collection->whereLessThanOrEqual('age', 25);

        $this->assertCount(3, $result);
        $this->assertLessThanOrEqual(25, $result->first()?->get('age'));
    }

    public function test_where_between_returns_items_with_value_in_range(): void
    {
        $result = $this->collection->whereBetween('age', 20, 30);

        $this->assertCount(3, $result);
        $this->assertTrue($result->first()?->get('age') >= 20 && $result->first()?->get('age') <= 30);
    }

    public function test_where_not_between_excludes_items_with_value_in_range(): void
    {
        $result = $this->collection->whereNotBetween('age', 20, 30);

        $this->assertCount(2, $result);
        $this->assertTrue($result->first()?->get('age') < 20 || $result->first()?->get('age') > 30);
    }

    public function test_where_null_returns_items_with_null_value(): void
    {
        $result = $this->collection->whereNull('age');

        $this->assertCount(0, $result);
    }

    public function test_where_not_null_returns_items_with_non_null_value(): void
    {
        $result = $this->collection->whereNotNull('age');

        $this->assertCount(5, $result);
        $this->assertNotNull($result->first()?->get('age'));
    }

    public function test_where_contains_returns_items_containing_substring(): void
    {
        $result = $this->collection->whereContains('name', 'Bob');

        $this->assertCount(1, $result);
        $name = $result->first()?->get('name');
        $this->assertIsString($name);
        $this->assertStringContainsString('Bob', (string) $name);
    }

    public function test_where_starts_with_returns_items_starting_with_prefix(): void
    {
        $result = $this->collection->whereStartsWith('name', 'J');

        $this->assertCount(2, $result);
        $name = $result->first()?->get('name');
        $this->assertIsString($name);
        $this->assertStringStartsWith('J', (string) $name);
    }

    public function test_where_ends_with_returns_items_ending_with_suffix(): void
    {
        $result = $this->collection->whereEndsWith('name', 'n');

        $this->assertCount(3, $result);
        $name = $result->first()?->get('name');
        $this->assertIsString($name);
        $this->assertStringEndsWith('n', (string) $name);
    }

    public function test_where_closure_returns_items_matching_custom_callback(): void
    {
        $result = $this->collection->whereClosure(
            fn (ClusterVO $cluster) => $cluster->get('age') > 25 && $cluster->get('role') === 'admin'
        );

        $this->assertCount(1, $result);
        $this->assertEquals('Charlie Wilson', $result->first()?->get('name'));
    }

    public function test_or_where_closure_without_prior_filter_applies_closure_to_full_collection(): void
    {
        $result = $this->collection->orWhereClosure(
            fn (ClusterVO $cluster) => $cluster->get('role') === 'admin'
        );

        $this->assertCount(3, $result);
        $this->assertEquals('admin', $result->first()?->get('role'));
    }

    public function test_or_where_closure_with_prior_filter_combines_filters_with_or(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->orWhereClosure(
                fn (ClusterVO $cluster) => $cluster->get('role') === 'admin'
            );

        $this->assertCount(3, $result);
    }

    public function test_where_closure_with_group_combines_closure_with_other_conditions(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->whereClosure(
                fn (ClusterVO $cluster) => $cluster->get('age') >= 25 && $cluster->get('verified') === 'true'
            );

        $this->assertCount(3, $result);
    }

    public function test_first_where_returns_first_matching_item(): void
    {
        $result = $this->collection->firstWhere('role', 'admin');

        $this->assertNotNull($result);
        $this->assertEquals('admin', $result->get('role'));
        $this->assertEquals(1, $result->get('id'));
    }

    public function test_first_where_returns_null_when_no_match_found(): void
    {
        $result = $this->collection->firstWhere('role', 'super_admin');

        $this->assertNull($result);
    }

    public function test_get_returns_all_items_as_array(): void
    {
        $result = $this->collection->get();

        $this->assertCount(5, $result);
        $this->assertIsArray($result);
    }

    public function test_empty_collection_returns_empty_result_when_filtering(): void
    {
        $emptyCollection = new ClusterVOCollection;

        $result = $emptyCollection->where('status', 'active');

        $this->assertCount(0, $result);
        $this->assertEmpty($result->get());
    }

    public function test_where_with_non_existent_key_returns_empty_result(): void
    {
        $result = $this->collection->where('non_existent_key', 'value');

        $this->assertCount(0, $result);
    }

    public function test_where_null_handles_null_values_correctly(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['key' => null]));

        $result = $collection->whereNull('key');

        $this->assertCount(1, $result);
        $this->assertEquals(null, $result->first()?->get('key'));
    }

    public function test_where_handles_numeric_strings_correctly(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['age' => '25']));
        $collection->add(new ClusterVO(['age' => 30]));

        $result = $collection->where('age', 30);

        $this->assertCount(1, $result);
        $this->assertEquals(30, $result->first()?->get('age'));
    }

    public function test_where_with_boolean_values_combines_true_and_false_filters(): void
    {
        $result = $this->collection
            ->whereTrue('verified')
            ->whereFalse('lang_en');

        $this->assertCount(2, $result);
        $this->assertEquals('true', $result->first()?->get('verified'));
        $this->assertEquals('false', $result->first()?->get('lang_en'));
    }

    public function test_where_in_with_empty_array_returns_empty_result(): void
    {
        $result = $this->collection->whereIn('role', []);

        $this->assertCount(0, $result);
    }

    public function test_where_not_in_with_empty_array_returns_all_items(): void
    {
        $result = $this->collection->whereNotIn('role', []);

        $this->assertCount(5, $result);
    }

    public function test_where_between_with_invalid_values_returns_empty_result(): void
    {
        $result = $this->collection->whereBetween('role', 'a', 'z');

        $this->assertCount(0, $result);
    }

    public function test_chain_with_where_not_after_group_combines_group_and_not_condition(): void
    {
        $result = $this->collection
            ->whereGroup(function (ClusterVOCollection $q) {
                return $q->where('status', 'active')
                    ->orWhere('status', 'pending');
            })
            ->whereNot('role', 'guest');

        $this->assertCount(3, $result);
    }

    public function test_where_like_with_non_string_value_returns_empty_result(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['age' => 25]));

        $result = $collection->whereLike('age', '25');

        $this->assertCount(0, $result);
    }

    public function test_where_like_with_empty_search_returns_all_items(): void
    {
        $result = $this->collection->whereLike('name', '');

        $this->assertCount(5, $result);
    }

    public function test_where_like_with_special_characters_handles_underscore_correctly(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'John_Doe']));
        $collection->add(new ClusterVO(['name' => 'Jane_Doe']));

        $result = $collection->whereLike('name', 'John_');

        $this->assertCount(1, $result);
        $this->assertEquals('John_Doe', $result->first()?->get('name'));
    }

    public function test_where_like_returns_items_matching_pattern(): void
    {
        $result = $this->collection->whereLike('name', 'John');

        $this->assertCount(2, $result);
        $this->assertStringContainsString('John', (string) $result->first()?->get('name'));
    }

    public function test_where_like_returns_multiple_matches_for_pattern(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'Johnny Cash']));
        $collection->add(new ClusterVO(['name' => 'John Lennon']));
        $collection->add(new ClusterVO(['name' => 'Jane Doe']));

        $result = $collection->whereLike('name', 'John');

        $this->assertCount(2, $result);
    }

    public function test_where_like_is_case_insensitive(): void
    {
        $result = $this->collection->whereLike('name', 'john');

        $this->assertCount(2, $result);
        $this->assertStringContainsString('John', (string) $result->first()?->get('name'));
    }

    public function test_where_starts_returns_items_starting_with_prefix(): void
    {
        $result = $this->collection->whereStarts('name', 'J');

        $this->assertCount(2, $result);
        $this->assertStringStartsWith('J', (string) $result->first()?->get('name'));
    }

    public function test_where_starts_is_case_insensitive(): void
    {
        $result = $this->collection->whereStarts('name', 'j');

        $this->assertCount(2, $result);
        $this->assertStringStartsWith('J', (string) $result->first()?->get('name'));
    }

    public function test_where_ends_returns_items_ending_with_suffix(): void
    {
        $result = $this->collection->whereEnds('name', 'e');

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()?->get('name'));
    }

    public function test_where_ends_is_case_insensitive(): void
    {
        $result = $this->collection->whereEnds('name', 'E');

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()?->get('name'));
    }

    public function test_where_not_like_excludes_items_matching_pattern(): void
    {
        $result = $this->collection->whereNotLike('name', 'John');

        $this->assertCount(3, $result);
        $this->assertStringNotContainsString('John', (string) $result->first()?->get('name'));
    }

    public function test_where_not_starts_excludes_items_starting_with_prefix(): void
    {
        $result = $this->collection->whereNotStarts('name', 'J');

        $this->assertCount(3, $result);
        $name = $result->first()?->get('name');
        $this->assertIsString($name);
        $this->assertStringStartsWith('B', (string) $name);
    }

    public function test_where_not_ends_excludes_items_ending_with_suffix(): void
    {
        $result = $this->collection->whereNotEnds('name', 'e');

        $this->assertCount(4, $result);
        $name = $result->first()?->get('name');
        $this->assertIsString($name);
        $this->assertFalse(str_ends_with(strtolower((string) $name), 'e'));
    }

    public function test_where_array_contains_returns_items_with_value_in_array(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => ['php', 'js', 'kotlin'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'tags' => ['php', 'python'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'tags' => ['ruby', 'go'],
        ]));

        $result = $collection->whereArrayContains('tags', 'php');

        $this->assertCount(2, $result);
        $this->assertEquals(1, $result->first()?->get('id'));
    }

    public function test_where_array_not_contains_excludes_items_with_value_in_array(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => ['php', 'js', 'kotlin'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'tags' => ['php', 'python'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'tags' => ['ruby', 'go'],
        ]));

        $result = $collection->whereArrayNotContains('tags', 'php');

        $this->assertCount(1, $result);
        $this->assertEquals(3, $result->first()?->get('id'));
    }

    public function test_or_where_array_contains_combines_conditions_with_or(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'status' => 'active',
            'tags' => ['php', 'js'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'status' => 'inactive',
            'tags' => ['python', 'go'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'status' => 'pending',
            'tags' => ['php', 'ruby'],
        ]));

        $result = $collection
            ->where('status', 'active')
            ->orWhereArrayContains('tags', 'python');

        $this->assertCount(2, $result);
        $this->assertContains($result->first()?->get('id'), [1, 2]);
    }

    public function test_where_array_contains_any_returns_items_with_any_value_in_array(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => ['php', 'js', 'kotlin'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'tags' => ['python', 'ruby'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'tags' => ['go', 'rust'],
        ]));

        $result = $collection->whereArrayContainsAny('tags', ['php', 'python']);

        $this->assertCount(2, $result);
        $this->assertContains($result->first()?->get('id'), [1, 2]);
    }

    public function test_where_array_contains_all_returns_items_with_all_values_in_array(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => ['php', 'js', 'kotlin'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'tags' => ['php', 'python'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'tags' => ['php', 'js', 'ruby'],
        ]));

        $result = $collection->whereArrayContainsAll('tags', ['php', 'js']);

        $this->assertCount(2, $result);
        $this->assertContains($result->first()?->get('id'), [1, 3]);
    }

    public function test_where_array_empty_returns_empty_collection_when_array_has_items(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'tags' => ['php', 'js'],
        ]));

        $result = $collection->whereArrayEmpty('tags');

        $this->assertCount(0, $result);
    }

    public function test_where_array_empty_returns_collection_with_item_when_array_is_empty(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'tags' => [],
        ]));

        $result = $collection->whereArrayEmpty('tags');

        $this->assertCount(1, $result);
    }

    public function test_where_array_not_empty_returns_collection_with_item_when_array_has_items(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'tags' => ['php', 'js'],
        ]));

        $result = $collection->whereArrayNotEmpty('tags');

        $this->assertCount(1, $result);
    }

    public function test_where_array_not_empty_returns_empty_collection_when_array_is_empty(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'tags' => [],
        ]));

        $result = $collection->whereArrayNotEmpty('tags');

        $this->assertCount(0, $result);
    }

    public function test_where_array_empty_returns_false_for_array_with_items(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => ['php', 'js'],
        ]));

        $result = $collection->whereArrayEmpty('tags');

        $this->assertCount(0, $result);
    }

    public function test_where_array_empty_returns_true_for_empty_array(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => [],
        ]));

        $result = $collection->whereArrayEmpty('tags');

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result->get()[0]->get('id'));
    }

    public function test_where_array_empty_with_missing_key_returns_empty_collection(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
        ]));

        $result = $collection->whereArrayEmpty('tags');

        $this->assertCount(0, $result);
    }

    public function test_where_array_empty_with_real_empty_array_returns_collection_with_item(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => [],
        ]));

        $result = $collection->whereArrayEmpty('tags');

        $this->assertCount(1, $result);
    }

    public function test_or_where_array_contains_any_combines_conditions_with_or(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'status' => 'active',
            'tags' => ['php', 'js'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'status' => 'inactive',
            'tags' => ['python', 'go'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'status' => 'pending',
            'tags' => ['ruby', 'rust'],
        ]));

        $result = $collection
            ->where('status', 'active')
            ->orWhereArrayContainsAny('tags', ['python', 'ruby']);

        $this->assertCount(3, $result);
        $this->assertContains($result->first()?->get('id'), [1, 2, 3]);
    }

    public function test_or_where_array_contains_all_combines_conditions_with_or(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'status' => 'active',
            'tags' => ['php', 'js', 'kotlin'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'status' => 'inactive',
            'tags' => ['php', 'js'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'status' => 'pending',
            'tags' => ['php', 'python'],
        ]));

        $result = $collection
            ->where('status', 'active')
            ->orWhereArrayContainsAll('tags', ['php', 'js']);

        $this->assertCount(2, $result);
        $this->assertContains($result->first()?->get('id'), [1, 2]);
    }

    public function test_or_where_array_not_contains_combines_conditions_with_or(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'status' => 'active',
            'tags' => ['php', 'js'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'status' => 'inactive',
            'tags' => ['python', 'go'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'status' => 'pending',
            'tags' => ['php', 'ruby'],
        ]));

        $result = $collection
            ->where('status', 'active')
            ->orWhereArrayNotContains('tags', 'php');

        $this->assertCount(2, $result);
        $this->assertContains($result->first()?->get('id'), [1, 2]);
    }

    public function test_where_array_size_returns_items_with_exact_size(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => ['php', 'js'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'tags' => ['python', 'go', 'ruby'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'tags' => ['php'],
        ]));

        $result = $collection->whereArraySize('tags', 2);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result->first()?->get('id'));
    }

    public function test_where_array_size_greater_than_returns_items_with_size_greater_than_threshold(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => ['php', 'js'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'tags' => ['python', 'go', 'ruby'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'tags' => ['php'],
        ]));

        $result = $collection->whereArraySizeGreaterThan('tags', 1);

        $this->assertCount(2, $result);
        $this->assertContains($result->first()?->get('id'), [1, 2]);
    }

    public function test_where_array_size_less_than_returns_items_with_size_less_than_threshold(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => ['php', 'js'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'tags' => ['python', 'go', 'ruby'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'tags' => ['php'],
        ]));

        $result = $collection->whereArraySizeLessThan('tags', 2);

        $this->assertCount(1, $result);
        $this->assertEquals(3, $result->first()?->get('id'));
    }

    public function test_where_array_empty_returns_items_with_empty_array(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => ['php', 'js'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'tags' => [],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'tags' => ['php'],
        ]));

        $result = $collection->whereArrayEmpty('tags');

        $this->assertCount(1, $result);
        $this->assertEquals(2, $result->first()?->get('id'));
    }

    public function test_where_array_not_empty_returns_items_with_non_empty_array(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => ['php', 'js'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'tags' => [],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'tags' => ['php'],
        ]));

        $result = $collection->whereArrayNotEmpty('tags');

        $this->assertCount(2, $result);
        $this->assertContains($result->first()?->get('id'), [1, 3]);
    }

    public function test_combined_array_filters_apply_multiple_array_conditions(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'status' => 'active',
            'tags' => ['php', 'js', 'kotlin'],
            'languages' => ['fr', 'en'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'status' => 'active',
            'tags' => ['php', 'python'],
            'languages' => ['en'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'status' => 'inactive',
            'tags' => ['ruby', 'go'],
            'languages' => ['fr'],
        ]));

        $result = $collection
            ->where('status', 'active')
            ->whereArrayContains('tags', 'php')
            ->whereArraySizeGreaterThan('languages', 1);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result->first()?->get('id'));
    }

    public function test_where_array_contains_with_non_array_value_returns_empty_collection(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => 'not_an_array',
        ]));

        $result = $collection->whereArrayContains('tags', 'php');

        $this->assertCount(0, $result);
    }

    public function test_where_array_not_contains_with_non_array_value_returns_collection_with_non_array_items(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => 'not_an_array',
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'tags' => ['php', 'js'],
        ]));

        $result = $collection->whereArrayNotContains('tags', 'php');

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result->first()?->get('id'));
    }

    public function test_where_array_size_with_non_array_value_returns_empty_collection(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => 'not_an_array',
        ]));

        $result = $collection->whereArraySize('tags', 3);

        $this->assertCount(0, $result);
    }

    public function test_where_array_empty_with_non_array_value_returns_empty_collection(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => 'not_an_array',
        ]));

        $result = $collection->whereArrayEmpty('tags');

        $this->assertCount(0, $result);
    }

    public function test_where_array_not_empty_with_non_array_value_returns_empty_collection(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'tags' => 'not_an_array',
        ]));

        $result = $collection->whereArrayNotEmpty('tags');

        $this->assertCount(0, $result);
    }

    public function test_complex_array_query_with_groups_supports_grouped_array_conditions(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'status' => 'active',
            'tags' => ['php', 'js'],
            'languages' => ['fr', 'en'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'status' => 'inactive',
            'tags' => ['php', 'python'],
            'languages' => ['en'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'status' => 'pending',
            'tags' => ['ruby', 'go'],
            'languages' => ['fr', 'es'],
        ]));

        $result = $collection->whereGroup(function (ClusterVOCollection $q) {
            return $q->where('status', 'active')
                ->orWhere('status', 'pending');
        })->whereArrayContainsAny('tags', ['php', 'python'])
            ->whereArraySizeGreaterThan('languages', 1);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result->first()?->get('id'));
    }

    public function test_or_where_array_contains_with_chain_combines_multiple_conditions(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John',
            'status' => 'active',
            'tags' => ['php', 'js'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane',
            'status' => 'active',
            'tags' => ['python', 'go'],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob',
            'status' => 'inactive',
            'tags' => ['php', 'ruby'],
        ]));

        $result = $collection
            ->where('status', 'active')
            ->whereArrayNotContains('tags', 'js')
            ->orWhereArrayContains('tags', 'ruby');

        $this->assertCount(2, $result);
        $this->assertContains($result->first()?->get('id'), [2, 3]);
    }

    // ==================== LIKE PATTERN TESTS ====================

    public function test_where_like_pattern_contains(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_smith']));
        $collection->add(new ClusterVO(['name' => 'bob_johnson']));

        $result = $collection->whereLikePattern('name', '%john%');

        $this->assertCount(2, $result);
    }

    public function test_where_like_pattern_starts_with(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_smith']));
        $collection->add(new ClusterVO(['name' => 'johnson']));

        $result = $collection->whereLikePattern('name', 'john%');

        $this->assertCount(2, $result);
    }

    public function test_where_like_pattern_ends_with(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_doe']));
        $collection->add(new ClusterVO(['name' => 'bob_smith']));

        $result = $collection->whereLikePattern('name', '%doe');

        $this->assertCount(2, $result);
    }

    public function test_where_like_pattern_multiple_wildcards(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'johanson']));
        $collection->add(new ClusterVO(['name' => 'johnson']));
        $collection->add(new ClusterVO(['name' => 'jones']));

        $result = $collection->whereLikePattern('name', '%j%h%n');

        // johanson et johnson contiennent j, h, n dans l'ordre
        $this->assertCount(2, $result);
    }

    public function test_where_like_pattern_starts_and_ends(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'johanson']));
        $collection->add(new ClusterVO(['name' => 'johnson']));

        $result = $collection->whereLikePattern('name', 'j%n');

        // johanson commence par j et finit par n
        // johnson commence par j et finit par n
        $this->assertCount(2, $result);
    }

    public function test_where_like_pattern_with_underscore(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_doe']));

        $result = $collection->whereLikePattern('name', 'john_doe');

        $this->assertCount(1, $result);
    }

    public function test_where_like_pattern_multiple_wildcards_with_order(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'johanson']));
        $collection->add(new ClusterVO(['name' => 'johnson']));

        // johanson: j -> h -> n dans l'ordre ✅
        // johnson: j -> h -> n dans l'ordre ✅
        $result = $collection->whereLikePattern('name', '%j%h%n');

        $this->assertCount(2, $result);
    }

    public function test_where_like_pattern_multiple_wildcards_wrong_order(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'johanson']));
        $collection->add(new ClusterVO(['name' => 'johnson']));

        // johanson: j -> n -> h dans l'ordre ❌
        // johnson: j -> n -> h dans l'ordre ❌
        $result = $collection->whereLikePattern('name', '%j%n%h');

        $this->assertCount(0, $result);
    }

    public function test_where_like_pattern_case_insensitive(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'JOHANSON']));
        $collection->add(new ClusterVO(['name' => 'JOHNSON']));

        $result = $collection->whereLikePattern('name', '%j%h%n');

        $this->assertCount(2, $result);
    }

    public function test_where_not_like_pattern(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_smith']));
        $collection->add(new ClusterVO(['name' => 'bob_johnson']));

        $result = $collection->whereNotLikePattern('name', '%john%');

        $this->assertCount(1, $result);
        $this->assertEquals('jane_smith', $result->first()?->get('name'));
    }

    public function test_where_like_pattern_with_empty_string(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => '']));
        $collection->add(new ClusterVO(['name' => 'john_doe']));

        $result = $collection->whereLikePattern('name', '');

        $this->assertCount(2, $result);
    }

    public function test_where_like_pattern_with_only_wildcard(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_smith']));

        $result = $collection->whereLikePattern('name', '%');

        $this->assertCount(2, $result);
    }

    public function test_where_like_pattern_with_non_string_value(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['age' => 25]));

        $result = $collection->whereLikePattern('age', '%25%');

        $this->assertCount(0, $result);
    }

    public function test_where_like_pattern_with_special_characters(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_doe']));

        $result = $collection->whereLikePattern('name', '%_doe');

        // _ correspond à un seul caractère, donc john_doe et jane_doe
        $this->assertCount(2, $result);
    }

    public function test_where_like_pattern_chaining_with_other_conditions(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe', 'status' => 'active']));
        $collection->add(new ClusterVO(['name' => 'john_smith', 'status' => 'active']));
        $collection->add(new ClusterVO(['name' => 'jane_doe', 'status' => 'inactive']));

        $result = $collection
            ->whereLikePattern('name', 'john%')
            ->where('status', 'active');

        $this->assertCount(2, $result);
    }

    public function test_where_like_pattern_or_condition(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_smith']));
        $collection->add(new ClusterVO(['name' => 'bob_johnson']));

        $result = $collection
            ->whereLikePattern('name', '%john%')
            ->orWhereLikePattern('name', '%jane%');

        $this->assertCount(3, $result);
    }

    public function test_or_where_like_pattern_without_prior_filter(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_smith']));
        $collection->add(new ClusterVO(['name' => 'bob_johnson']));

        $result = $collection->orWhereLikePattern('name', '%john%');

        $this->assertCount(2, $result);
    }

    public function test_or_where_like_pattern_with_prior_filter(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe', 'status' => 'active']));
        $collection->add(new ClusterVO(['name' => 'jane_smith', 'status' => 'active']));
        $collection->add(new ClusterVO(['name' => 'bob_johnson', 'status' => 'inactive']));

        $result = $collection
            ->where('status', 'active')
            ->orWhereLikePattern('name', '%johnson%');

        // active: john_doe, jane_smith → 2
        // ou johnson: bob_johnson → 1 (mais pas active)
        // total: 3
        $this->assertCount(3, $result);
    }

    public function test_or_where_not_like_pattern(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_smith']));
        $collection->add(new ClusterVO(['name' => 'bob_johnson']));

        $result = $collection->orWhereNotLikePattern('name', '%john%');

        // jane_smith ne contient pas john → 1
        $this->assertCount(1, $result);
        $this->assertEquals('jane_smith', $result->first()?->get('name'));
    }

    public function test_or_where_like_pattern_with_multiple_patterns(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe']));
        $collection->add(new ClusterVO(['name' => 'jane_smith']));
        $collection->add(new ClusterVO(['name' => 'bob_johnson']));

        $result = $collection
            ->orWhereLikePattern('name', '%john%')
            ->orWhereLikePattern('name', '%jane%');

        $this->assertCount(3, $result);
    }

    public function test_or_where_like_pattern_chain_with_where(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'john_doe', 'status' => 'active']));
        $collection->add(new ClusterVO(['name' => 'jane_smith', 'status' => 'active']));
        $collection->add(new ClusterVO(['name' => 'bob_johnson', 'status' => 'inactive']));
        $collection->add(new ClusterVO(['name' => 'alice_johnson', 'status' => 'active']));

        $result = $collection
            ->where('status', 'active')
            ->orWhereLikePattern('name', '%johnson%');

        // active: john_doe, jane_smith, alice_johnson → 3
        // ou johnson: bob_johnson, alice_johnson → 2 (mais alice déjà dans active)
        // total: 4
        $this->assertCount(4, $result);
    }
}
