<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Functions;

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Functions\ExtractKeyFunction;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use PHPUnit\Framework\TestCase;

final class AggregateExtractKeyFunctionTest extends TestCase
{
    // ==================== EXECUTE TESTS ====================

    public function test_extract_key_with_valid_object(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'name' => 'Pharmacie A',
                'slug' => 'pharma-a',
            ],
        ];

        $result = $function->execute($data, ['slug', 'pharmacy']);

        $this->assertSame('pharma-a', $result);
    }

    public function test_extract_key_with_different_key(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'name' => 'Pharmacie A',
                'slug' => 'pharma-a',
            ],
        ];

        $result = $function->execute($data, ['name', 'pharmacy']);

        $this->assertSame('Pharmacie A', $result);
    }

    public function test_extract_key_with_nested_object(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'drug' => [
                'name' => 'Paracétamol',
                'slug' => 'paracetamol',
            ],
        ];

        $result = $function->execute($data, ['slug', 'drug']);

        $this->assertSame('paracetamol', $result);
    }

    public function test_extract_key_with_deep_nested_key(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'profile' => [
                    'name' => 'Jean Dupont',
                    'email' => 'jean@example.com',
                ],
            ],
        ];

        $result = $function->execute($data, ['profile.name', 'pharmacy']);

        $this->assertSame('Jean Dupont', $result);
    }

    public function test_extract_key_with_very_deep_nested_path(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'profile' => [
                    'contact' => [
                        'phone' => '0612345678',
                    ],
                ],
            ],
        ];

        $result = $function->execute($data, ['profile.contact.phone', 'pharmacy']);

        $this->assertSame('0612345678', $result);
    }

    public function test_extract_key_without_object_path(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'slug' => 'pharma-a',
            'name' => 'Pharmacie A',
        ];

        $result = $function->execute($data, ['slug']);

        $this->assertSame('pharma-a', $result);
    }

    public function test_extract_key_without_object_path_with_nested_key(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'profile' => [
                'name' => 'Jean Dupont',
            ],
        ];

        $result = $function->execute($data, ['profile.name']);

        $this->assertSame('Jean Dupont', $result);
    }

    public function test_extract_key_with_missing_key(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'name' => 'Pharmacie A',
            ],
        ];

        $result = $function->execute($data, ['slug', 'pharmacy']);

        $this->assertNull($result);
    }

    public function test_extract_key_with_missing_object(): void
    {
        $function = new ExtractKeyFunction;

        $data = [];

        $result = $function->execute($data, ['slug', 'pharmacy']);

        $this->assertNull($result);
    }

    public function test_extract_key_with_null_data(): void
    {
        $function = new ExtractKeyFunction;

        $result = $function->execute([], ['slug', 'pharmacy']);

        $this->assertNull($result);
    }

    public function test_extract_key_with_empty_key(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'slug' => 'pharma-a',
            ],
        ];

        $result = $function->execute($data, ['', 'pharmacy']);

        $this->assertNull($result);
    }

    public function test_extract_key_with_invalid_object_path(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'slug' => 'pharma-a',
            ],
        ];

        $result = $function->execute($data, ['slug', 'invalid']);

        $this->assertNull($result);
    }

    public function test_extract_key_with_non_array_object(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => 'not an array',
        ];

        $result = $function->execute($data, ['slug', 'pharmacy']);

        $this->assertNull($result);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_extract_key_validate_args(): void
    {
        $function = new ExtractKeyFunction;

        // ✅ Valid - 1 argument
        $this->assertTrue($function->validateArgs(['slug']));

        // ✅ Valid - 2 arguments
        $this->assertTrue($function->validateArgs(['slug', 'pharmacy']));
        $this->assertTrue($function->validateArgs(['profile.name', 'pharmacy']));

        // ❌ Invalid - empty
        $this->assertFalse($function->validateArgs([]));

        // ❌ Invalid - empty key
        $this->assertFalse($function->validateArgs(['', 'pharmacy']));

        // ❌ Invalid - empty object path
        $this->assertFalse($function->validateArgs(['slug', '']));

        // ❌ Invalid - 3 arguments
        $this->assertFalse($function->validateArgs(['slug', 'pharmacy', 'extra']));

        // ❌ Invalid - non-string key
        $this->assertFalse($function->validateArgs([123, 'pharmacy']));

        // ❌ Invalid - non-string object path
        $this->assertFalse($function->validateArgs(['slug', 123]));
    }

    // ==================== METADATA TESTS ====================

    public function test_extract_key_get_name(): void
    {
        $function = new ExtractKeyFunction;

        $this->assertSame('EXTRACT_KEY', $function->getName());
    }

    public function test_extract_key_get_return_type(): void
    {
        $function = new ExtractKeyFunction;

        $this->assertSame('mixed', $function->getReturnType());
    }

    public function test_extract_key_get_default_value(): void
    {
        $function = new ExtractKeyFunction;

        $this->assertNull($function->getDefaultValue());
    }

    public function test_extract_key_returns_boolean(): void
    {
        $function = new ExtractKeyFunction;

        $this->assertFalse($function->returnsBoolean());
    }

    public function test_extract_key_get_min_args(): void
    {
        $function = new ExtractKeyFunction;

        $this->assertSame(1, $function->getMinArgs());
    }

    public function test_extract_key_get_max_args(): void
    {
        $function = new ExtractKeyFunction;

        $this->assertSame(2, $function->getMaxArgs());
    }

    // ==================== COLLECTION CONTEXT TESTS ====================

    public function test_extract_key_in_collection_context(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Offer 1',
            'pharmacy' => [
                'name' => 'Pharmacie A',
                'slug' => 'pharma-a',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Offer 2',
            'pharmacy' => [
                'name' => 'Pharmacie B',
                'slug' => 'pharma-b',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Offer 3',
            'pharmacy' => [
                'name' => 'Pharmacie A',
                'slug' => 'pharma-a',
            ],
        ]));

        $result = $collection->whereAggregate('{EXTRACT_KEY(slug, pharmacy) = pharma-a}');

        $this->assertCount(2, $result);
        $this->assertEquals('Offer 1', $result->first()->get('name'));
        $this->assertEquals('Offer 3', $result->last()->get('name'));
    }

    public function test_extract_key_with_nested_in_collection(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Offer 1',
            'pharmacy' => [
                'profile' => [
                    'name' => 'Jean Dupont',
                ],
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Offer 2',
            'pharmacy' => [
                'profile' => [
                    'name' => 'Marie Martin',
                ],
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Offer 3',
            'pharmacy' => [
                'profile' => [
                    'name' => 'Jean Dupont',
                ],
            ],
        ]));

        $result = $collection->whereAggregate('{EXTRACT_KEY(profile.name, pharmacy) = "Jean Dupont"}');

        $this->assertCount(2, $result);
        $this->assertEquals('Offer 1', $result->first()->get('name'));
        $this->assertEquals('Offer 3', $result->last()->get('name'));
    }

    public function test_extract_key_without_object_path_in_collection(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Offer 1',
            'slug' => 'pharma-a',
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Offer 2',
            'slug' => 'pharma-b',
        ]));
        $collection->add(new ClusterVO([
            'id' => 3,
            'name' => 'Offer 3',
            'slug' => 'pharma-a',
        ]));

        $result = $collection->whereAggregate('{EXTRACT_KEY(slug) = pharma-a}');

        $this->assertCount(2, $result);
        $this->assertEquals('Offer 1', $result->first()->get('name'));
        $this->assertEquals('Offer 3', $result->last()->get('name'));
    }

    public function test_extract_key_with_complex_condition(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Offer 1',
            'pharmacy' => [
                'profile' => [
                    'name' => 'Jean Dupont',
                ],
                'status' => 'active',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Offer 2',
            'pharmacy' => [
                'profile' => [
                    'name' => 'Jean Dupont',
                ],
                'status' => 'inactive',
            ],
        ]));

        $result = $collection
            ->whereAggregate('{EXTRACT_KEY(profile.name, pharmacy) = "Jean Dupont"}')
            ->where('pharmacy.status', 'active');

        $this->assertCount(1, $result);
        $this->assertEquals('Offer 1', $result->first()->get('name'));
    }

    public function test_extract_key_with_complex_condition_using_where_query(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'id' => 1,
            'name' => 'Offer 1',
            'pharmacy' => [
                'profile' => [
                    'name' => 'Jean Dupont',
                ],
                'status' => 'active',
            ],
        ]));
        $collection->add(new ClusterVO([
            'id' => 2,
            'name' => 'Offer 2',
            'pharmacy' => [
                'profile' => [
                    'name' => 'Jean Dupont',
                ],
                'status' => 'inactive',
            ],
        ]));

        // ✅ Combiner whereAggregate et where
        $result = $collection
            ->whereAggregate('{EXTRACT_KEY(profile.name, pharmacy) = "Jean Dupont"}')
            ->where('pharmacy.status', 'active');

        $this->assertCount(1, $result);
        $this->assertEquals('Offer 1', $result->first()->get('name'));
    }

    // ==================== MATCHES AGGREGATE TESTS ====================

    public function test_extract_key_with_matches_aggregate(): void
    {
        $cluster = new ClusterVO([
            'pharmacy' => [
                'name' => 'Pharmacie A',
                'slug' => 'pharma-a',
            ],
        ]);

        $collection = new ClusterVOCollection;
        $collection->add($cluster);

        $result = $collection->matchesAggregate($cluster, '{EXTRACT_KEY(slug, pharmacy) = pharma-a}');

        $this->assertTrue($result);

        $result = $collection->matchesAggregate($cluster, '{EXTRACT_KEY(slug, pharmacy) = pharma-b}');

        $this->assertFalse($result);
    }

    public function test_extract_key_with_matches_aggregate_nested(): void
    {
        $cluster = new ClusterVO([
            'pharmacy' => [
                'profile' => [
                    'name' => 'Jean Dupont',
                ],
            ],
        ]);

        $collection = new ClusterVOCollection;
        $collection->add($cluster);

        $result = $collection->matchesAggregate($cluster, '{EXTRACT_KEY(profile.name, pharmacy) = "Jean Dupont"}');

        $this->assertTrue($result);

        $result = $collection->matchesAggregate($cluster, '{EXTRACT_KEY(profile.name, pharmacy) = "Marie Martin"}');

        $this->assertFalse($result);
    }

    public function test_extract_key_with_matches_aggregate_without_object_path(): void
    {
        $cluster = new ClusterVO([
            'slug' => 'pharma-a',
        ]);

        $collection = new ClusterVOCollection;
        $collection->add($cluster);

        $result = $collection->matchesAggregate($cluster, '{EXTRACT_KEY(slug) = pharma-a}');

        $this->assertTrue($result);

        $result = $collection->matchesAggregate($cluster, '{EXTRACT_KEY(slug) = pharma-b}');

        $this->assertFalse($result);
    }

    // ==================== GET AGGREGATE VALUE TESTS ====================

    public function test_extract_key_get_aggregate_value(): void
    {
        $cluster = new ClusterVO([
            'pharmacy' => [
                'name' => 'Pharmacie A',
                'slug' => 'pharma-a',
            ],
        ]);

        $collection = new ClusterVOCollection;
        $collection->add($cluster);

        $value = $collection->getAggregateValue($cluster, 'EXTRACT_KEY', ['slug', 'pharmacy']);

        $this->assertSame('pharma-a', $value);
    }

    public function test_extract_key_get_aggregate_value_nested(): void
    {
        $cluster = new ClusterVO([
            'pharmacy' => [
                'profile' => [
                    'name' => 'Jean Dupont',
                ],
            ],
        ]);

        $collection = new ClusterVOCollection;
        $collection->add($cluster);

        $value = $collection->getAggregateValue($cluster, 'EXTRACT_KEY', ['profile.name', 'pharmacy']);

        $this->assertSame('Jean Dupont', $value);
    }

    public function test_extract_key_get_aggregate_value_without_object_path(): void
    {
        $cluster = new ClusterVO([
            'slug' => 'pharma-a',
        ]);

        $collection = new ClusterVOCollection;
        $collection->add($cluster);

        $value = $collection->getAggregateValue($cluster, 'EXTRACT_KEY', ['slug']);

        $this->assertSame('pharma-a', $value);
    }

    public function test_extract_key_get_aggregate_value_not_found(): void
    {
        $cluster = new ClusterVO([
            'pharmacy' => [
                'name' => 'Pharmacie A',
            ],
        ]);

        $collection = new ClusterVOCollection;
        $collection->add($cluster);

        $value = $collection->getAggregateValue($cluster, 'EXTRACT_KEY', ['slug', 'pharmacy']);

        $this->assertNull($value);
    }

    // ==================== VALIDATE AGGREGATE TESTS ====================

    public function test_extract_key_validate_aggregate(): void
    {
        $collection = new ClusterVOCollection;

        $this->assertTrue($collection->validateAggregate('{EXTRACT_KEY(slug, pharmacy) = pharma-a}'));
        $this->assertTrue($collection->validateAggregate('{EXTRACT_KEY(profile.name, pharmacy) = "Jean Dupont"}'));
        $this->assertTrue($collection->validateAggregate('{EXTRACT_KEY(slug) = pharma-a}'));

        // ❌ Invalid - extra closing brace
        $this->assertFalse($collection->validateAggregate('{EXTRACT_KEY(slug, pharmacy) = pharma-a}}'));

        // ❌ Invalid - no closing brace
        $this->assertFalse($collection->validateAggregate('{EXTRACT_KEY(slug, pharmacy) = pharma-a'));
    }

    // ==================== EDGE CASES ====================

    public function test_extract_key_with_empty_collection(): void
    {
        $collection = new ClusterVOCollection;

        $result = $collection->whereAggregate('{EXTRACT_KEY(slug, pharmacy) = pharma-a}');

        $this->assertCount(0, $result);
    }

    public function test_extract_key_with_special_characters_in_value(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'id' => 1,
            'pharmacy' => [
                'name' => 'Pharmacie A & B',
                'slug' => 'pharma-a-b',
            ],
        ]));

        $result = $collection->whereAggregate('{EXTRACT_KEY(name, pharmacy) = "Pharmacie A & B"}');

        $this->assertCount(1, $result);
        $this->assertEquals('pharma-a-b', $result->first()->get('pharmacy.slug'));
    }

    public function test_extract_key_with_numeric_key(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'id' => 123,
                'name' => 'Pharmacie A',
            ],
        ];

        $result = $function->execute($data, ['id', 'pharmacy']);

        $this->assertSame(123, $result);
    }

    public function test_extract_key_with_boolean_value(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'active' => true,
                'name' => 'Pharmacie A',
            ],
        ];

        $result = $function->execute($data, ['active', 'pharmacy']);

        $this->assertTrue($result);
    }

    public function test_extract_key_with_array_value(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'tags' => ['pharma', 'health', 'care'],
                'name' => 'Pharmacie A',
            ],
        ];

        $result = $function->execute($data, ['tags', 'pharmacy']);

        $this->assertSame(['pharma', 'health', 'care'], $result);
    }

    public function test_extract_key_with_nested_path_and_array(): void
    {
        $function = new ExtractKeyFunction;

        $data = [
            'pharmacy' => [
                'profile' => [
                    'contacts' => ['email', 'phone', 'address'],
                ],
            ],
        ];

        $result = $function->execute($data, ['profile.contacts', 'pharmacy']);

        $this->assertSame(['email', 'phone', 'address'], $result);
    }
}
