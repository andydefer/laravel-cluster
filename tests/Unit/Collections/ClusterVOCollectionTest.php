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

        // John Doe avec adresses et scores
        $this->collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'John Doe',
            'status' => 'active',
            'role' => 'admin',
            'age' => 30,
            'verified' => 'true',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'addresses' => [
                ['city' => 'Kinshasa', 'street' => 'Avenue de la Paix', 'country' => 'RDC'],
                ['city' => 'Lubumbashi', 'street' => 'Avenue Lumumba', 'country' => 'RDC'],
            ],
            'scores' => [80, 90, 85], // ✅ Ajout des scores
            'tags' => ['php', 'js', 'docker'],
            'settings' => [
                'notifications' => [
                    ['email' => 'true', 'sms' => 'false', 'push' => 'true'],
                ],
                'theme' => 'dark',
            ],
        ]));

        // Jane Smith avec adresses et scores
        $this->collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Jane Smith',
            'status' => 'inactive',
            'role' => 'doctor',
            'age' => 25,
            'verified' => 'true',
            'lang_fr' => 'false',
            'lang_en' => 'true',
            'addresses' => [
                ['city' => 'Paris', 'street' => 'Rue de Rivoli', 'country' => 'France'],
            ],
            'scores' => [70, 75, 80], // ✅ Ajout des scores
            'tags' => ['python', 'react'],
            'settings' => [
                'notifications' => [
                    ['email' => 'false', 'sms' => 'true', 'push' => 'false'],
                ],
                'theme' => 'light',
            ],
        ]));

        // Bob Johnson avec adresses et scores
        $this->collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Bob Johnson',
            'status' => 'active',
            'role' => 'doctor',
            'age' => 35,
            'verified' => 'true',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'addresses' => [
                ['city' => 'Kinshasa', 'street' => 'Boulevard du 30 Juin', 'country' => 'RDC'],
                ['city' => 'Paris', 'street' => 'Avenue des Champs-Élysées', 'country' => 'France'],
                ['city' => 'London', 'street' => 'Oxford Street', 'country' => 'UK'],
            ],
            'scores' => [95, 98, 92], // ✅ Ajout des scores
            'tags' => ['php', 'laravel', 'vuejs'],
            'settings' => [
                'notifications' => [
                    ['email' => 'true', 'sms' => 'true', 'push' => 'true'],
                ],
                'theme' => 'dark',
            ],
        ]));

        // Alice Wonder sans adresses mais avec scores
        $this->collection->add(new ClusterVO([
            'id' => 4,
            'name' => 'Alice Wonder',
            'status' => 'pending',
            'role' => 'guest',
            'age' => 28,
            'verified' => 'false',
            'lang_fr' => 'false',
            'lang_en' => 'true',
            'addresses' => [],
            'scores' => [85, 90, 88], // ✅ Ajout des scores
            'tags' => ['go', 'rust'],
            'settings' => [
                'notifications' => [
                    ['email' => 'false', 'sms' => 'false', 'push' => 'false'],
                ],
                'theme' => 'light',
            ],
        ]));
    }

    public function test_where_returns_collection_with_matching_items(): void
    {
        $result = $this->collection->where('status', 'active');

        $this->assertCount(2, $result); // John Doe et Bob Johnson
        $this->assertEquals('active', $result->first()?->get('status'));
    }

    public function test_and_where_filters_collection_with_multiple_conditions(): void
    {
        $result = $this->collection
            ->andWhere('status', 'active')
            ->andWhere('role', 'admin');

        $this->assertCount(1, $result); // John Doe
        $this->assertEquals('admin', $result->first()?->get('role'));
    }

    public function test_where_not_excludes_items_with_matching_value(): void
    {
        $result = $this->collection->whereNot('status', 'inactive');

        $this->assertCount(3, $result); // John, Bob, Alice
        $this->assertNotEquals('inactive', $result->first()?->get('status'));
    }

    public function test_where_true_returns_items_with_true_value(): void
    {
        $result = $this->collection->whereTrue('verified');

        $this->assertCount(3, $result); // John, Jane, Bob
        $this->assertEquals('true', $result->first()?->get('verified'));
    }

    public function test_where_false_returns_items_with_false_value(): void
    {
        $result = $this->collection->whereFalse('verified');

        $this->assertCount(1, $result); // Alice
        $this->assertEquals('false', $result->first()?->get('verified'));
    }

    public function test_or_where_combines_filters_with_or_logic(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->orWhere('status', 'pending');

        $this->assertCount(3, $result); // John, Bob, Alice
        $this->assertContains($result->first()?->get('status'), ['active', 'pending']);
    }

    public function test_where_group_applies_conditions_as_a_group(): void
    {
        $result = $this->collection->whereGroup(function (ClusterVOCollection $q) {
            return $q->where('status', 'active')
                ->orWhere('status', 'pending');
        });

        $this->assertCount(3, $result); // John, Bob, Alice
    }

    public function test_where_group_with_and_condition_combines_group_with_outer_condition(): void
    {
        $result = $this->collection
            ->whereGroup(function (ClusterVOCollection $q) {
                return $q->where('status', 'active')
                    ->orWhere('status', 'pending');
            })
            ->andWhere('role', 'admin');

        $this->assertCount(1, $result); // John Doe
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

        $this->assertCount(2, $result); // John (admin), Bob (doctor)
    }

    public function test_or_where_group_without_prior_filter_applies_or_group_to_full_collection(): void
    {
        $result = $this->collection->orWhereGroup(function (ClusterVOCollection $q) {
            return $q->where('role', 'admin')
                ->where('verified', 'true');
        });

        $this->assertCount(1, $result); // John Doe
    }

    public function test_or_where_group_with_prior_filter_combines_filters_with_or(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->orWhereGroup(function (ClusterVOCollection $q) {
                return $q->where('role', 'admin')
                    ->where('verified', 'true');
            });

        $this->assertCount(2, $result); // John (active), Bob (active)
    }

    public function test_chain_with_or_where_after_group_combines_group_and_single_condition(): void
    {
        $result = $this->collection
            ->whereGroup(function (ClusterVOCollection $q) {
                return $q->where('status', 'active')
                    ->where('role', 'admin');
            })
            ->orWhere('status', 'pending');

        $this->assertCount(2, $result); // John (active+admin), Alice (pending)
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

        $this->assertCount(2, $result); // John, Bob (active)
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

        $this->assertCount(2, $result); // John, Bob (verified=true)
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

        $this->assertCount(1, $result); // John Doe
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

        $this->assertCount(2, $result); // John, Bob (active + verified)
    }

    public function test_where_has_returns_items_with_existing_key(): void
    {
        $result = $this->collection->whereHas('lang_fr');

        // Tous les 4 clusters ont la clé 'lang_fr'
        $this->assertCount(4, $result);
        $this->assertTrue($result->first()?->has('lang_fr'));
    }

    public function test_where_missing_returns_items_without_key(): void
    {
        $result = $this->collection->whereMissing('deleted');

        $this->assertCount(4, $result);
        $this->assertFalse($result->first()?->has('deleted'));
    }

    public function test_where_in_returns_items_with_value_in_array(): void
    {
        $result = $this->collection->whereIn('role', ['admin', 'doctor']);

        $this->assertCount(3, $result); // John (admin), Jane (doctor), Bob (doctor)
        $this->assertContains($result->first()?->get('role'), ['admin', 'doctor']);
    }

    public function test_where_not_in_excludes_items_with_value_in_array(): void
    {
        $result = $this->collection->whereNotIn('status', ['active', 'pending']);

        $this->assertCount(1, $result); // Jane (inactive)
        $this->assertEquals('inactive', $result->first()?->get('status'));
    }

    public function test_where_greater_than_returns_items_with_value_greater_than_threshold(): void
    {
        $result = $this->collection->whereGreaterThan('age', 25);

        // John (30), Bob (35), Alice (28) → 3
        $this->assertCount(3, $result);
        $this->assertGreaterThan(25, $result->first()?->get('age'));
    }

    public function test_where_greater_than_or_equal_returns_items_with_value_greater_than_or_equal_threshold(): void
    {
        $result = $this->collection->whereGreaterThanOrEqual('age', 25);

        // Tous les 4 clusters ont un âge >= 25
        $this->assertCount(4, $result);
        $this->assertGreaterThanOrEqual(25, $result->first()?->get('age'));
    }

    public function test_where_less_than_returns_items_with_value_less_than_threshold(): void
    {
        $result = $this->collection->whereLessThan('age', 25);

        $this->assertCount(0, $result);
    }

    public function test_where_less_than_or_equal_returns_items_with_value_less_than_or_equal_threshold(): void
    {
        $result = $this->collection->whereLessThanOrEqual('age', 25);

        $this->assertCount(1, $result); // Jane (25)
        $this->assertLessThanOrEqual(25, $result->first()?->get('age'));
    }

    public function test_where_between_returns_items_with_value_in_range(): void
    {
        $result = $this->collection->whereBetween('age', 28, 30);

        $this->assertCount(2, $result); // Jane (25), John (30)
        $this->assertTrue($result->first()?->get('age') >= 28 && $result->first()?->get('age') <= 30);
    }

    public function test_where_not_between_excludes_items_with_value_in_range(): void
    {
        $result = $this->collection->whereNotBetween('age', 20, 30);

        $this->assertCount(1, $result);
        $this->assertEquals('Bob Johnson', $result->first()?->get('name'));
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

        $this->assertCount(4, $result);
        $this->assertNotNull($result->first()?->get('age'));
    }

    public function test_where_contains_returns_items_containing_substring(): void
    {
        $result = $this->collection->whereContains('name', 'Bob');

        $this->assertCount(1, $result); // Bob Johnson
        $name = $result->first()?->get('name');
        $this->assertIsString($name);
        $this->assertStringContainsString('Bob', (string) $name);
    }

    public function test_where_starts_with_returns_items_starting_with_prefix(): void
    {
        $result = $this->collection->whereStartsWith('name', 'J');

        $this->assertCount(2, $result); // John Doe, Jane Smith
        $name = $result->first()?->get('name');
        $this->assertIsString($name);
        $this->assertStringStartsWith('J', (string) $name);
    }

    public function test_where_ends_with_returns_items_ending_with_suffix(): void
    {
        $result = $this->collection->whereEndsWith('name', 'n');

        // John Doe (e), Jane Smith (h), Bob Johnson (n), Alice Wonder (r)
        $this->assertCount(1, $result); // Bob Johnson
        $name = $result->first()?->get('name');
        $this->assertIsString($name);
        $this->assertStringEndsWith('n', (string) $name);
    }

    public function test_where_closure_returns_items_matching_custom_callback(): void
    {
        $result = $this->collection->whereClosure(
            fn (ClusterVO $cluster) => $cluster->get('age') > 25 && $cluster->get('role') === 'admin'
        );

        $this->assertCount(1, $result); // John Doe
        $this->assertEquals('John Doe', $result->first()?->get('name'));
    }

    public function test_or_where_closure_without_prior_filter_applies_closure_to_full_collection(): void
    {
        $result = $this->collection->orWhereClosure(
            fn (ClusterVO $cluster) => $cluster->get('role') === 'admin'
        );

        $this->assertCount(1, $result); // John Doe
        $this->assertEquals('admin', $result->first()?->get('role'));
    }

    public function test_or_where_closure_with_prior_filter_combines_filters_with_or(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->orWhereClosure(
                fn (ClusterVO $cluster) => $cluster->get('role') === 'admin'
            );

        $this->assertCount(2, $result); // John (active+admin), Bob (active)
    }

    public function test_where_closure_with_group_combines_closure_with_other_conditions(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->whereClosure(
                fn (ClusterVO $cluster) => $cluster->get('age') >= 25 && $cluster->get('verified') === 'true'
            );

        $this->assertCount(2, $result); // John, Bob
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

        $this->assertCount(4, $result);
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

        $this->assertCount(2, $result); // John, Bob
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

        $this->assertCount(4, $result);
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

        $this->assertCount(2, $result); // John, Bob
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

        $this->assertCount(4, $result);
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

        $this->assertCount(2, $result); // John Doe, Bob Johnson (contient John)
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

        $this->assertCount(2, $result); // John Doe, Bob Johnson
        $this->assertStringContainsString('John', (string) $result->first()?->get('name'));
    }

    public function test_where_starts_returns_items_starting_with_prefix(): void
    {
        $result = $this->collection->whereStarts('name', 'J');

        $this->assertCount(2, $result); // John Doe, Jane Smith
        $this->assertStringStartsWith('J', (string) $result->first()?->get('name'));
    }

    public function test_where_starts_is_case_insensitive(): void
    {
        $result = $this->collection->whereStarts('name', 'j');

        $this->assertCount(2, $result); // John Doe, Jane Smith
        $this->assertStringStartsWith('J', (string) $result->first()?->get('name'));
    }

    public function test_where_ends_returns_items_ending_with_suffix(): void
    {
        $result = $this->collection->whereEnds('name', 'e');

        $this->assertCount(1, $result); // John Doe
        $this->assertEquals('John Doe', $result->first()?->get('name'));
    }

    public function test_where_ends_is_case_insensitive(): void
    {
        $result = $this->collection->whereEnds('name', 'E');

        $this->assertCount(1, $result); // John Doe
        $this->assertEquals('John Doe', $result->first()?->get('name'));
    }

    public function test_where_not_like_excludes_items_matching_pattern(): void
    {
        $result = $this->collection->whereNotLike('name', 'John');

        $this->assertCount(2, $result); // Jane Smith, Alice Wonder
        $this->assertStringNotContainsString('John', (string) $result->first()?->get('name'));
    }

    public function test_where_not_starts_excludes_items_starting_with_prefix(): void
    {
        $result = $this->collection->whereNotStarts('name', 'J');

        $this->assertCount(2, $result); // Bob Johnson, Alice Wonder
        $name = $result->first()?->get('name');
        $this->assertIsString($name);
        $this->assertStringStartsWith('B', (string) $name);
    }

    public function test_where_not_ends_excludes_items_ending_with_suffix(): void
    {
        $result = $this->collection->whereNotEnds('name', 'e');

        $this->assertCount(3, $result); // Jane Smith, Bob Johnson, Alice Wonder
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

        $this->assertCount(4, $result);
    }

    // ==================== WHERE QUERY TESTS (SOUS-CONDITIONS) ====================

    public function test_where_query_simple_subcondition(): void
    {
        // addresses[city=kinshasa]
        $result = $this->collection->whereQuery('addresses[city=kinshasa]');

        $this->assertCount(2, $result); // John et Bob
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_query_subcondition_with_and(): void
    {
        // addresses[city=kinshasa & country=rdc]
        $result = $this->collection->whereQuery('addresses[city=kinshasa & country=rdc]');

        $this->assertCount(2, $result); // John et Bob
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_where_query_subcondition_with_or(): void
    {
        // addresses[city=kinshasa | city=paris]
        $result = $this->collection->whereQuery('addresses[city=kinshasa | city=paris]');

        $this->assertCount(3, $result); // John, Jane, Bob
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_query_subcondition_with_like(): void
    {
        // addresses[city=~kin%]
        $result = $this->collection->whereQuery('addresses[city=~kin%]');

        $this->assertCount(2, $result); // John et Bob
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
    }

    public function test_where_query_subcondition_with_not_like(): void
    {
        // addresses[city!~kin%]
        // Retourne les clusters qui ont au moins une adresse dont la ville ne commence PAS par 'kin'
        $result = $this->collection->whereQuery('addresses[city!~kin%]');

        // John (a Lubumbashi), Jane (Paris), Bob (Paris/London) → 3
        $this->assertCount(3, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_query_subcondition_with_nested_path(): void
    {
        // settings.notifications[email=true]
        $result = $this->collection->whereQuery('settings.notifications[email=true]');

        $this->assertCount(2, $result); // John et Bob
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_query_subcondition_with_exists(): void
    {
        // addresses[*] ou addresses[]
        $result = $this->collection->whereQuery('addresses[]');

        $this->assertCount(3, $result); // John, Jane, Bob (ont des adresses)
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_query_subcondition_with_not_exists(): void
    {
        // addresses[#city]
        $result = $this->collection->whereQuery('addresses[#city]');

        $this->assertCount(1, $result); // Alice
        $this->assertEquals('Alice Wonder', $result->first()?->get('name'));
    }

    public function test_where_query_combined_with_condition(): void
    {
        // status=active & addresses[city=kinshasa]
        $result = $this->collection->whereQuery('status=active & addresses[city=kinshasa]');

        $this->assertCount(2, $result); // John et Bob
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_query_with_or_and_parentheses(): void
    {

        // (status=active | status=pending) & addresses[city=kinshasa | city=paris]
        $result = $this->collection->whereQuery('(status=active | status=pending) & addresses[city=kinshasa | city=paris]');

        // John (active + Kinshasa) et Bob (active + Kinshasa/Paris) → 2
        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_query_complex_subcondition(): void
    {
        // addresses[(city=kinshasa & country=rdc) | (city=paris & country=france)]
        $result = $this->collection->whereQuery('addresses[(city=kinshasa & country=rdc) | (city=paris & country=france)]');

        $this->assertCount(3, $result); // John, Jane, Bob
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_query_subcondition_with_multiple_array_conditions(): void
    {
        // addresses[city=kinshasa] & tags_php=true
        $result = $this->collection->whereQuery('addresses[city=kinshasa] & tags_php=true');

        $this->assertCount(2, $result); // John et Bob
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_where_query_with_chaining_collection_methods(): void
    {
        // Mélanger whereQuery avec les méthodes de collection
        $result = $this->collection
            ->whereQuery('addresses[city=kinshasa]')
            ->where('status', 'active')
            ->whereGreaterThan('age', 30);

        $this->assertCount(1, $result); // Bob
        $this->assertEquals('Bob Johnson', $result->first()?->get('name'));
    }

    public function test_where_query_returns_new_collection(): void
    {
        $originalCount = $this->collection->count();
        $result = $this->collection->whereQuery('addresses[city=kinshasa]');

        $this->assertCount(2, $result);
        $this->assertCount($originalCount, $this->collection); // Original inchangé
        $this->assertNotSame($this->collection, $result);
    }

    public function test_where_query_with_empty_collection(): void
    {
        $emptyCollection = new ClusterVOCollection;
        $result = $emptyCollection->whereQuery('addresses[city=kinshasa]');

        $this->assertCount(0, $result);
        $this->assertEmpty($result->get());
    }

    public function test_where_query_with_no_matches(): void
    {
        $result = $this->collection->whereQuery('addresses[city=tokyo]');

        $this->assertCount(0, $result);
        $this->assertEmpty($result->get());
    }

    public function test_where_query_with_simple_condition(): void
    {
        // Vérifier que whereQuery fonctionne aussi avec des conditions simples
        $result = $this->collection->whereQuery('status=active');

        $this->assertCount(2, $result); // John et Bob
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_query_with_age_comparison(): void
    {
        // age>30
        $result = $this->collection->whereQuery('age>30');

        $this->assertCount(1, $result); // Bob
        $this->assertEquals('Bob Johnson', $result->first()?->get('name'));
    }

    public function test_where_query_with_presence(): void
    {
        // lang_fr (présence de la clé)
        $result = $this->collection->whereQuery('lang_fr');

        $this->assertCount(2, $result); // John et Bob
    }

    public function test_where_query_with_addresses_path_and_country(): void
    {
        // addresses[country=rdc]
        $result = $this->collection->whereQuery('addresses[country=rdc]');

        $this->assertCount(2, $result); // John et Bob
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    // ==================== AGGREGATE WHERE TESTS ====================

    public function test_where_aggregate_count_with_comparison(): void
    {
        // ✅ CORRECTION : COUNT(addresses) >= 2 (ou > 1)
        // John (2) et Bob (3) → 2
        $result = $this->collection->whereAggregate('{COUNT(addresses) >= 2}');

        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_aggregate_count_equals(): void
    {
        // COUNT(addresses) = 2
        $result = $this->collection->whereAggregate('{COUNT(addresses) = 2}');

        // John (2) et Bob (3) → 1
        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()?->get('name'));
    }

    public function test_where_aggregate_count_less_than(): void
    {
        // COUNT(addresses) < 2
        $result = $this->collection->whereAggregate('{COUNT(addresses) < 2}');

        // Jane (1) et Alice (0) → 2
        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('Jane Smith', $names);
        $this->assertContains('Alice Wonder', $names);
        $this->assertNotContains('John Doe', $names);
        $this->assertNotContains('Bob Johnson', $names);
    }

    public function test_where_aggregate_sum(): void
    {
        // SUM(prices) > 1000
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'prices' => [100, 200, 300],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'prices' => [50, 75],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'prices' => [500, 600, 700],
        ]));

        $result = $collection->whereAggregate('{SUM(prices) > 1000}');

        $this->assertCount(1, $result);
        $this->assertEquals('Bob', $result->first()?->get('name'));
    }

    public function test_where_aggregate_avg(): void
    {
        // AVG(scores) >= 85
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => [80, 90, 85],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'scores' => [70, 75, 80],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'scores' => [95, 98, 92],
        ]));

        $result = $collection->whereAggregate('{AVG(scores) >= 85}');

        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John', $names);
        $this->assertContains('Bob', $names);
        $this->assertNotContains('Jane', $names);
    }

    public function test_where_aggregate_min(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => [80, 90, 85],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'scores' => [70, 75, 80],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'scores' => [95, 98, 92],
        ]));

        $result = $collection->whereAggregate('{MIN(scores) > 80}');

        $this->assertCount(1, $result);
        $this->assertEquals('Bob', $result->first()?->get('name'));
    }

    public function test_where_aggregate_max(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => [80, 90, 85],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Jane',
            'scores' => [70, 75, 80],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Bob',
            'scores' => [95, 98, 92],
        ]));

        $result = $collection->whereAggregate('{MAX(scores) < 95}');

        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John', $names);
        $this->assertContains('Jane', $names);
        $this->assertNotContains('Bob', $names);
    }

    public function test_where_aggregate_length(): void
    {
        // LENGTH(name) > 5
        $result = $this->collection->whereAggregate('{LENGTH(name) > 5}');

        // John Doe (8), Jane Smith (10), Bob Johnson (11), Alice Wonder (12) → 4
        $this->assertCount(4, $result);
    }

    public function test_where_aggregate_with_nested_path(): void
    {
        // COUNT(settings.notifications) > 1
        $result = $this->collection->whereAggregate('{COUNT(settings.notifications) > 1}');

        $this->assertCount(0, $result); // Tous ont 1 notification
    }

    public function test_where_aggregate_direct_count(): void
    {
        // COUNT(addresses) > 0 automatique
        $result = $this->collection->whereAggregateDirect('COUNT', ['addresses']);

        // John, Jane, Bob ont des adresses → 3
        $this->assertCount(3, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_aggregate_direct_sum(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO(['name' => 'John', 'prices' => [100, 200, 300]]));
        $collection->add(new ClusterVO(['name' => 'Jane', 'prices' => []]));
        $collection->add(new ClusterVO(['name' => 'Bob', 'prices' => [500, 600, 700]]));

        // SUM(prices) > 0 automatique
        $result = $collection->whereAggregateDirect('SUM', ['prices']);

        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John', $names);
        $this->assertContains('Bob', $names);
        $this->assertNotContains('Jane', $names);
    }

    public function test_where_aggregate_direct_exists(): void
    {
        // EXISTS(addresses) retourne booléen
        $result = $this->collection->whereAggregateDirect('EXISTS', ['addresses']);

        // John, Jane, Bob ont des adresses → 3
        $this->assertCount(3, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_aggregate_direct_has(): void
    {
        // HAS(addresses, city, "Kinshasa")
        $result = $this->collection->whereAggregateDirect('HAS', ['addresses', 'city', 'Kinshasa']);

        // John et Bob ont Kinshasa → 2
        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_aggregate_direct_all(): void
    {
        // ALL(addresses, country, "RDC") - tous les pays sont RDC
        $result = $this->collection->whereAggregateDirect('ALL', ['addresses', 'country', 'RDC']);

        // John a 2 adresses RDC ✅, Bob a RDC, France, UK ❌
        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()?->get('name'));
    }

    public function test_where_aggregate_direct_is_empty(): void
    {
        // IS_EMPTY(addresses)
        $result = $this->collection->whereAggregateDirect('IS_EMPTY', ['addresses']);

        // Alice n'a pas d'adresses → 1
        $this->assertCount(1, $result);
        $this->assertEquals('Alice Wonder', $result->first()?->get('name'));
    }

    public function test_matches_aggregate_count(): void
    {
        $cluster = $this->collection->first();

        $result = $this->collection->matchesAggregate($cluster, '{COUNT(addresses) > 1}');

        $this->assertTrue($result);
    }

    public function test_matches_aggregate_count_false(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'Jane Smith');

        $result = $this->collection->matchesAggregate($cluster, '{COUNT(addresses) > 1}');

        $this->assertFalse($result);
    }

    public function test_matches_aggregate_exists(): void
    {
        $cluster = $this->collection->first();

        $result = $this->collection->matchesAggregate($cluster, '{EXISTS(addresses)}');

        $this->assertTrue($result);
    }

    public function test_matches_aggregate_exists_false(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'Alice Wonder');

        $result = $this->collection->matchesAggregate($cluster, '{EXISTS(addresses)}');

        $this->assertFalse($result);
    }

    public function test_matches_aggregate_has(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'John Doe');

        $result = $this->collection->matchesAggregate($cluster, '{HAS(addresses, city, "Kinshasa")}');

        $this->assertTrue($result);
    }

    public function test_matches_aggregate_has_false(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'Jane Smith');

        $result = $this->collection->matchesAggregate($cluster, '{HAS(addresses, city, "Kinshasa")}');

        $this->assertFalse($result);
    }

    public function test_matches_aggregate_all(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'John Doe');

        // John a 2 adresses en RDC
        $result = $this->collection->matchesAggregate($cluster, '{ALL(addresses, country, "RDC")}');

        $this->assertTrue($result);
    }

    public function test_matches_aggregate_all_false(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'Bob Johnson');

        // Bob a RDC, France, UK → pas tous RDC
        $result = $this->collection->matchesAggregate($cluster, '{ALL(addresses, country, "RDC")}');

        $this->assertFalse($result);
    }

    public function test_matches_aggregate_direct_count(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'John Doe');

        $result = $this->collection->matchesAggregateDirect($cluster, 'COUNT', ['addresses']);

        $this->assertTrue($result); // 2 > 0
    }

    public function test_matches_aggregate_direct_count_false(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'Alice Wonder');

        $result = $this->collection->matchesAggregateDirect($cluster, 'COUNT', ['addresses']);

        $this->assertFalse($result); // 0 > 0 = false
    }

    public function test_matches_aggregate_direct_exists(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'Bob Johnson');

        $result = $this->collection->matchesAggregateDirect($cluster, 'EXISTS', ['addresses']);

        $this->assertTrue($result);
    }

    public function test_matches_aggregate_direct_exists_false(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'Alice Wonder');

        $result = $this->collection->matchesAggregateDirect($cluster, 'EXISTS', ['addresses']);

        $this->assertFalse($result);
    }

    public function test_get_aggregate_value_count(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'John Doe');

        $result = $this->collection->getAggregateValue($cluster, 'COUNT', ['addresses']);

        $this->assertEquals(2, $result);
    }

    public function test_get_aggregate_value_count_zero(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'Alice Wonder');

        $result = $this->collection->getAggregateValue($cluster, 'COUNT', ['addresses']);

        $this->assertEquals(0, $result);
    }

    public function test_get_aggregate_value_sum(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'prices' => [100, 200, 300],
        ]));

        $cluster = $collection->first();
        $result = $collection->getAggregateValue($cluster, 'SUM', ['prices']);

        $this->assertEquals(600.0, $result);
    }

    public function test_get_aggregate_value_avg(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => [80, 90, 85],
        ]));

        $cluster = $collection->first();
        $result = $collection->getAggregateValue($cluster, 'AVG', ['scores']);

        $this->assertEquals(85.0, $result);
    }

    public function test_get_aggregate_value_min(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => [80, 90, 85],
        ]));

        $cluster = $collection->first();
        $result = $collection->getAggregateValue($cluster, 'MIN', ['scores']);

        $this->assertEquals(80.0, $result);
    }

    public function test_get_aggregate_value_max(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => [80, 90, 85],
        ]));

        $cluster = $collection->first();
        $result = $collection->getAggregateValue($cluster, 'MAX', ['scores']);

        $this->assertEquals(90.0, $result);
    }

    public function test_get_aggregate_value_length(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'John Doe');

        $result = $this->collection->getAggregateValue($cluster, 'LENGTH', ['name']);

        $this->assertEquals(8, $result); // "John Doe" = 8 caractères
    }

    public function test_get_aggregate_value_exists(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'John Doe');

        $result = $this->collection->getAggregateValue($cluster, 'EXISTS', ['addresses']);

        $this->assertTrue($result);
    }

    public function test_get_aggregate_value_exists_false(): void
    {
        $cluster = $this->collection->find(fn ($c) => $c->get('name') === 'Alice Wonder');

        $result = $this->collection->getAggregateValue($cluster, 'EXISTS', ['addresses']);

        $this->assertFalse($result);
    }

    public function test_validate_aggregate_valid_expression(): void
    {
        $result = $this->collection->validateAggregate('{COUNT(addresses) > 2}');

        $this->assertTrue($result);
    }

    public function test_validate_aggregate_valid_complex_expression(): void
    {
        $result = $this->collection->validateAggregate('{COUNT(addresses) > 2} & {AVG(scores) >= 85}');

        $this->assertTrue($result);
    }

    public function test_validate_aggregate_valid_boolean_function(): void
    {
        $result = $this->collection->validateAggregate('{EXISTS(addresses)}');

        $this->assertTrue($result);
    }

    public function test_validate_aggregate_invalid_expression(): void
    {
        $result = $this->collection->validateAggregate('{INVALID_FUNCTION(addresses) > 2}');

        $this->assertFalse($result);
    }

    public function test_validate_aggregate_invalid_syntax(): void
    {
        $result = $this->collection->validateAggregate('{COUNT(addresses > 2}');

        $this->assertFalse($result);
    }

    public function test_where_aggregate_complex_with_and(): void
    {
        // COUNT(addresses) > 1 ET AVG(scores) >= 85
        // John (2, 85) et Bob (3, 95) → 2
        $result = $this->collection->whereAggregate('{COUNT(addresses) > 1} & {AVG(scores) >= 85}');

        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_aggregate_complex_with_or(): void
    {
        // COUNT(addresses) > 1 OU AVG(scores) >= 95
        // John (2 > 1) et Bob (3 > 1) → 2
        $result = $this->collection->whereAggregate('{COUNT(addresses) > 1} | {AVG(scores) >= 95}');

        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_aggregate_complex_with_boolean_and_numeric(): void
    {
        // EXISTS(addresses) ET COUNT(addresses) > 1
        $result = $this->collection->whereAggregate('{EXISTS(addresses)} & {COUNT(addresses) > 1}');

        // John (2) et Bob (3) → 2
        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_aggregate_complex_with_has(): void
    {
        // HAS(addresses, city, "Kinshasa") ET COUNT(addresses) > 1
        $result = $this->collection->whereAggregate('{HAS(addresses, city, "Kinshasa")} & {COUNT(addresses) > 1}');

        // John (2, Kinshasa) et Bob (3, Kinshasa) → 2
        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_where_aggregate_complex_with_all(): void
    {
        // ALL(addresses, country, "RDC") ET COUNT(addresses) > 1
        $result = $this->collection->whereAggregate('{ALL(addresses, country, "RDC")} & {COUNT(addresses) > 1}');

        // John a 2 adresses RDC → 1
        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()?->get('name'));
    }

    public function test_where_aggregate_complex_with_is_empty(): void
    {
        // IS_EMPTY(addresses) OR COUNT(addresses) > 2
        $result = $this->collection->whereAggregate('{IS_EMPTY(addresses)} | {COUNT(addresses) > 2}');

        // Alice (vide) et Bob (3) → 2
        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('Alice Wonder', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_where_query_detects_aggregate(): void
    {
        // whereQuery détecte automatiquement les agrégations
        $result = $this->collection->whereQuery('{COUNT(addresses) > 2}');

        $this->assertCount(1, $result);
        $this->assertEquals('Bob Johnson', $result->first()?->get('name'));
    }

    public function test_where_query_mixed_aggregate_and_normal(): void
    {
        // Mélange d'agrégation et condition normale
        $result = $this->collection->whereQuery('{COUNT(addresses) > 2} & status=active');

        $this->assertCount(1, $result);
        $this->assertEquals('Bob Johnson', $result->first()?->get('name'));
    }

    public function test_where_query_boolean_aggregate(): void
    {
        // whereQuery avec fonction booléenne
        $result = $this->collection->whereQuery('{EXISTS(addresses)}');

        $this->assertCount(3, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_query_complex_with_aggregate_and_normal(): void
    {
        // whereQuery avec agrégation + condition normale + fonction booléenne
        $result = $this->collection->whereQuery(
            '{COUNT(addresses) > 1} & status=active & {EXISTS(addresses)}'
        );

        // John (active, 2 adresses) et Bob (active, 3 adresses) → 2
        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
    }

    public function test_where_aggregate_with_empty_collection(): void
    {
        $emptyCollection = new ClusterVOCollection;

        $result = $emptyCollection->whereAggregate('{COUNT(addresses) > 2}');

        $this->assertCount(0, $result);
    }

    public function test_where_aggregate_with_numeric_string_values(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => ['80', '90', '85'], // Strings numériques
        ]));

        $result = $collection->whereAggregate('{AVG(scores) > 80}');

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->first()?->get('name'));
    }

    public function test_where_aggregate_with_nested_path_complex(): void
    {
        // Vérifier que les notifications avec email=true existent
        // Utiliser HAS pour vérifier la présence d'un élément avec email=true
        $result = $this->collection->whereAggregate('{HAS(settings.notifications, email, true)}');

        // John (email=true) et Bob (email=true) → 2
        $this->assertCount(2, $result);
        $names = array_map(fn ($c) => $c->get('name'), $result->get());
        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
        $this->assertNotContains('Alice Wonder', $names);
    }

    public function test_where_aggregate_with_invalid_expression_returns_empty(): void
    {
        $result = $this->collection->whereAggregate('{INVALID(addresses) > 2}');

        $this->assertCount(0, $result);
    }

    public function test_where_aggregate_with_missing_path_returns_empty(): void
    {
        $result = $this->collection->whereAggregate('{COUNT(non_existent_path) > 2}');

        $this->assertCount(0, $result);
    }

    public function test_where_aggregate_with_non_array_value(): void
    {
        $collection = new ClusterVOCollection;
        $collection->add(new ClusterVO([
            'name' => 'John',
            'scores' => 'not_an_array', // String au lieu d'un tableau
        ]));

        $result = $collection->whereAggregate('{COUNT(scores) > 0}');

        // COUNT sur une string retourne sa longueur
        $this->assertCount(1, $result);
    }

    public function test_where_aggregate_chaining_with_where(): void
    {
        $result = $this->collection
            ->where('status', 'active')
            ->whereAggregate('{COUNT(addresses) > 2}')  // ✅ > 2 au lieu de > 1
            ->whereAggregate('{AVG(scores) >= 85}');

        $this->assertCount(1, $result);
        $this->assertEquals('Bob Johnson', $result->first()?->get('name'));
    }

    private function normalize(ClusterVOCollection $collection): array
    {
        return normalizer_chain(true)->normalize($collection);
    }
}
