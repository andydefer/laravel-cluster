<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Services;

use AndyDefer\LaravelCluster\Services\FlatArrayService;
use PHPUnit\Framework\TestCase;

final class FlatArrayServiceTest extends TestCase
{
    private FlatArrayService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FlatArrayService;
    }

    public function test_flattens_simple_array(): void
    {
        $input = [
            'name' => 'Dupont',
            'firstname' => 'Jean',
            'age' => 30,
        ];

        $expected = [
            'name' => 'Dupont',
            'firstname' => 'Jean',
            'age' => 30,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_expands_indexed_array_to_true_keys(): void
    {
        $input = [
            'name' => 'Dupont',
            'languages' => ['fr', 'en', 'es', 'ln'],
        ];

        $expected = [
            'name' => 'Dupont',
            'languages_fr' => 'yes',
            'languages_en' => 'yes',
            'languages_es' => 'yes',
            'languages_ln' => 'yes',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_expands_indexed_array_with_numeric_values(): void
    {
        $input = [
            'name' => 'John',
            'scores' => [10, 20, 30],
        ];

        $expected = [
            'name' => 'John',
            'scores_10' => 'yes',
            'scores_20' => 'yes',
            'scores_30' => 'yes',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_expands_indexed_array_with_mixed_values(): void
    {
        $input = [
            'name' => 'John',
            'codes' => ['ABC', 123, 'DEF'],
        ];

        $expected = [
            'name' => 'John',
            'codes_ABC' => 'yes',
            'codes_123' => 'yes',
            'codes_DEF' => 'yes',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_handles_empty_indexed_array_as_null(): void
    {
        $input = [
            'name' => 'Dupont',
            'tags' => [],
        ];

        $expected = [
            'name' => 'Dupont',
            'tags' => null,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_nested_associative_array_with_dot_notation(): void
    {
        $input = [
            'name' => 'Dupont',
            'address' => [
                'city' => 'Lyon',
                'postal_code' => 69000,
                'country' => 'France',
            ],
        ];

        $expected = [
            'name' => 'Dupont',
            'address.city' => 'Lyon',
            'address.postal_code' => 69000,
            'address.country' => 'France',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_deeply_nested_array_with_dot_notation(): void
    {
        $input = [
            'user' => [
                'personal' => [
                    'name' => 'Dupont',
                    'firstname' => 'Jean',
                ],
                'professional' => [
                    'role' => 'admin',
                    'department' => 'IT',
                ],
            ],
        ];

        $expected = [
            'user.personal.name' => 'Dupont',
            'user.personal.firstname' => 'Jean',
            'user.professional.role' => 'admin',
            'user.professional.department' => 'IT',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_expands_indexed_array_inside_nested_array(): void
    {
        $input = [
            'profile' => [
                'name' => 'John',
                'tags' => ['php', 'js', 'kotlin'],
            ],
        ];

        $expected = [
            'profile.name' => 'John',
            'profile.tags_php' => 'yes',
            'profile.tags_js' => 'yes',
            'profile.tags_kotlin' => 'yes',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_nested_with_indexed_and_associative(): void
    {
        $input = [
            'user' => [
                'name' => 'John',
                'settings' => [
                    'theme' => 'dark',
                    'notifications' => ['email', 'push'],
                ],
                'tags' => ['premium', 'verified'],
            ],
        ];

        $expected = [
            'user.name' => 'John',
            'user.settings.theme' => 'dark',
            'user.settings.notifications_email' => 'yes',
            'user.settings.notifications_push' => 'yes',
            'user.tags_premium' => 'yes',
            'user.tags_verified' => 'yes',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_json_encodes_nested_indexed_array(): void
    {
        $input = [
            'tags' => [
                ['php', 'js'],
                ['kotlin', 'rust'],
            ],
        ];

        $result = $this->service->flatten($input);

        $this->assertArrayHasKey('tags', $result);
        $this->assertIsString($result['tags']);
        $this->assertJson($result['tags']);

        $decoded = json_decode($result['tags'], true);
        $this->assertEquals([['php', 'js'], ['kotlin', 'rust']], $decoded);
    }

    public function test_json_encodes_nested_indexed_array_inside_nested(): void
    {
        $input = [
            'user' => [
                'name' => 'John',
                'tags' => [
                    ['php', 'js'],
                    ['kotlin', 'rust'],
                ],
            ],
        ];

        $result = $this->service->flatten($input);

        $this->assertArrayHasKey('user.name', $result);
        $this->assertEquals('John', $result['user.name']);
        $this->assertArrayHasKey('user.tags', $result);
        $this->assertIsString($result['user.tags']);
        $this->assertJson($result['user.tags']);

        $decoded = json_decode($result['user.tags'], true);
        $this->assertEquals([['php', 'js'], ['kotlin', 'rust']], $decoded);
    }

    public function test_json_encodes_mixed_nested_arrays(): void
    {
        $input = [
            'data' => [
                'simple' => ['a', 'b', 'c'],
                'nested' => [
                    ['x', 'y'],
                    ['z', 'w'],
                ],
                'associative' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
            ],
        ];

        $result = $this->service->flatten($input);

        $this->assertArrayHasKey('data.simple_a', $result);
        $this->assertEquals('yes', $result['data.simple_a']);
        $this->assertArrayHasKey('data.nested', $result);
        $this->assertIsString($result['data.nested']);
        $this->assertJson($result['data.nested']);
        $this->assertArrayHasKey('data.associative.key1', $result);
        $this->assertEquals('value1', $result['data.associative.key1']);
    }

    public function test_json_encodes_deep_nested_arrays(): void
    {
        $input = [
            'levels' => [
                'level1' => [
                    'level2' => [
                        'level3' => [
                            ['a', 'b'],
                            ['c', 'd'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->flatten($input);

        $this->assertArrayHasKey('levels.level1.level2.level3', $result);
        $this->assertIsString($result['levels.level1.level2.level3']);
        $this->assertJson($result['levels.level1.level2.level3']);

        $decoded = json_decode($result['levels.level1.level2.level3'], true);
        $this->assertEquals([['a', 'b'], ['c', 'd']], $decoded);
    }

    public function test_unflatten_simple_array(): void
    {
        $input = [
            'name' => 'Dupont',
            'age' => 30,
        ];

        $expected = [
            'name' => 'Dupont',
            'age' => 30,
        ];

        $this->assertSame($expected, $this->service->unflatten($input));
    }

    public function test_unflatten_dot_notation(): void
    {
        $input = [
            'address.city' => 'Lyon',
            'address.postal_code' => 69000,
            'address.country' => 'France',
        ];

        $expected = [
            'address' => [
                'city' => 'Lyon',
                'postal_code' => 69000,
                'country' => 'France',
            ],
        ];

        $this->assertSame($expected, $this->service->unflatten($input));
    }

    public function test_unflatten_deep_dot_notation(): void
    {
        $input = [
            'user.personal.name' => 'John',
            'user.personal.age' => 30,
            'user.professional.role' => 'admin',
        ];

        $expected = [
            'user' => [
                'personal' => [
                    'name' => 'John',
                    'age' => 30,
                ],
                'professional' => [
                    'role' => 'admin',
                ],
            ],
        ];

        $this->assertSame($expected, $this->service->unflatten($input));
    }

    public function test_unflatten_with_json_encoded_values(): void
    {
        $input = [
            'user.tags' => json_encode([['php', 'js'], ['kotlin', 'rust']]),
            'user.name' => 'John',
        ];

        $result = $this->service->unflatten($input);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('tags', $result['user']);
        $this->assertArrayHasKey('name', $result['user']);
        $this->assertEquals('John', $result['user']['name']);
        $this->assertEquals([['php', 'js'], ['kotlin', 'rust']], $result['user']['tags']);
    }

    public function test_unflatten_with_nested_json_encoded_values(): void
    {
        $input = [
            'user.settings.nested' => json_encode([['a', 'b'], ['c', 'd']]),
            'user.settings.theme' => 'dark',
        ];

        $result = $this->service->unflatten($input);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('settings', $result['user']);
        $this->assertArrayHasKey('nested', $result['user']['settings']);
        $this->assertArrayHasKey('theme', $result['user']['settings']);
        $this->assertEquals('dark', $result['user']['settings']['theme']);
        $this->assertEquals([['a', 'b'], ['c', 'd']], $result['user']['settings']['nested']);
    }

    public function test_flattens_empty_array(): void
    {
        $input = [];
        $expected = [];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_handles_mixed_types(): void
    {
        $input = [
            'string' => 'value',
            'int' => 42,
            'float' => 3.14,
            'null' => null,
        ];

        $expected = [
            'string' => 'value',
            'int' => 42,
            'float' => 3.14,
            'null' => null,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_with_custom_prefix(): void
    {
        $input = [
            'name' => 'John',
            'address' => [
                'city' => 'Paris',
            ],
            'tags' => ['php', 'js'],
        ];

        $expected = [
            'user.name' => 'John',
            'user.address.city' => 'Paris',
            'user.tags_php' => 'yes',
            'user.tags_js' => 'yes',
        ];

        $this->assertSame($expected, $this->service->flatten($input, 'user'));
    }

    public function test_handles_tags_with_same_name_as_key(): void
    {
        $input = [
            'status' => 'active',
            'status_active' => 'yes',
            'tags' => ['active', 'inactive'],
        ];

        $expected = [
            'status' => 'active',
            'status_active' => 'yes',
            'tags_active' => 'yes',
            'tags_inactive' => 'yes',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_handles_boolean_values_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean values are not allowed');

        $input = [
            'active' => true,
            'verified' => false,
        ];

        $this->service->flatten($input);
    }

    public function test_handles_null_values_in_array(): void
    {
        $input = [
            'tags' => ['php', null, 'js'],
        ];

        $expected = [
            'tags_php' => 'yes',
            'tags_null' => 'yes',
            'tags_js' => 'yes',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_handles_mixed_types_in_indexed_array(): void
    {
        $input = [
            'values' => ['string', 123, 45.67, null],
        ];

        $result = $this->service->flatten($input);

        $this->assertArrayHasKey('values_string', $result);
        $this->assertEquals('yes', $result['values_string']);
        $this->assertArrayHasKey('values_123', $result);
        $this->assertEquals('yes', $result['values_123']);
        $this->assertArrayHasKey('values_45.67', $result);
        $this->assertEquals('yes', $result['values_45.67']);
        $this->assertArrayHasKey('values_null', $result);
        $this->assertEquals('yes', $result['values_null']);
    }

    public function test_flatten_converts_simple_boolean_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean values are not allowed');

        $data = [
            'active' => true,
            'verified' => false,
        ];

        $this->service->flatten($data);
    }

    public function test_flatten_converts_nested_boolean_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean values are not allowed');

        $data = [
            'user' => [
                'active' => true,
                'verified' => false,
                'name' => 'John',
            ],
        ];

        $this->service->flatten($data);
    }

    public function test_flatten_converts_deeply_nested_boolean_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean values are not allowed');

        $data = [
            'settings' => [
                'notifications' => [
                    'email' => true,
                    'sms' => false,
                    'push' => true,
                ],
                'theme' => 'dark',
            ],
        ];

        $this->service->flatten($data);
    }

    public function test_flatten_converts_booleans_in_indexed_array_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean values are not allowed in arrays');

        $data = [
            'addresses' => [
                [
                    'city' => 'Kinshasa',
                    'is_primary' => true,
                    'active' => false,
                ],
                [
                    'city' => 'Lubumbashi',
                    'is_primary' => false,
                    'active' => true,
                ],
            ],
        ];

        $this->service->flatten($data);
    }

    public function test_flatten_converts_booleans_in_deeply_nested_indexed_array_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean values are not allowed in arrays');

        $data = [
            'settings' => [
                'notifications' => [
                    [
                        'email' => true,
                        'sms' => false,
                        'push' => true,
                    ],
                    [
                        'email' => false,
                        'sms' => true,
                        'push' => false,
                    ],
                ],
                'theme' => 'dark',
            ],
        ];

        $this->service->flatten($data);
    }

    public function test_flatten_converts_booleans_in_mixed_array_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean values are not allowed');

        $data = [
            'user' => [
                'name' => 'John Doe',
                'active' => true,
                'age' => 30,
                'tags' => ['php', 'js', 'docker'],
                'preferences' => [
                    'theme' => 'dark',
                    'notifications' => true,
                    'language' => 'fr',
                ],
                'addresses' => [
                    [
                        'city' => 'Kinshasa',
                        'is_primary' => true,
                    ],
                    [
                        'city' => 'Lubumbashi',
                        'is_primary' => false,
                    ],
                ],
            ],
        ];

        $this->service->flatten($data);
    }

    public function test_flatten_preserves_non_boolean_values(): void
    {
        $data = [
            'name' => 'John',
            'age' => 30,
            'height' => 1.75,
            'tags' => ['php', 'js'],
            'nested' => [
                'value' => 'test',
                'number' => 42,
                'float' => 3.14,
            ],
        ];

        $result = $this->service->flatten($data);

        $this->assertSame('John', $result['name']);
        $this->assertSame(30, $result['age']);
        $this->assertSame(1.75, $result['height']);
        $this->assertSame('test', $result['nested.value']);
        $this->assertSame(42, $result['nested.number']);
        $this->assertSame(3.14, $result['nested.float']);
    }

    public function test_flatten_converts_booleans_in_empty_arrays_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean values are not allowed');

        $data = [
            'empty_array' => [],
            'nested_empty' => [
                'empty' => [],
                'active' => true,
            ],
        ];

        $this->service->flatten($data);
    }

    public function test_flatten_converts_booleans_with_null_values_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean values are not allowed');

        $data = [
            'user' => [
                'name' => 'John',
                'email' => null,
                'verified' => true,
                'settings' => [
                    'theme' => null,
                    'notifications' => false,
                ],
            ],
        ];

        $this->service->flatten($data);
    }

    public function test_unflatten_converts_string_true_false_to_booleans(): void
    {
        $data = [
            'user.active' => 'yes',
            'user.verified' => 'no',
            'user.name' => 'John',
        ];

        $result = $this->service->unflatten($data);

        $this->assertSame('yes', $result['user']['active']);
        $this->assertSame('no', $result['user']['verified']);
        $this->assertSame('John', $result['user']['name']);
    }

    public function test_flatten_and_unflatten_roundtrip_with_booleans_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean values are not allowed');

        $original = [
            'name' => 'John',
            'active' => true,
            'preferences' => [
                'theme' => 'dark',
                'notifications' => true,
                'email' => false,
            ],
            'tags' => ['php', 'js'],
            'addresses' => [
                [
                    'city' => 'Kinshasa',
                    'is_primary' => true,
                ],
            ],
        ];

        $this->service->flatten($original);
    }

    public function test_flatten_with_complex_nested_structures_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean values are not allowed');

        $data = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'active' => true,
                        'items' => [
                            ['id' => 1, 'enabled' => true],
                            ['id' => 2, 'enabled' => false],
                        ],
                        'settings' => [
                            'notifications' => true,
                            'theme' => 'light',
                        ],
                    ],
                ],
            ],
        ];

        $this->service->flatten($data);
    }

    public function test_flatten_converts_boolean_in_associative_array_inside_indexed_array_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean values are not allowed in arrays');

        $data = [
            'items' => [
                [
                    'name' => 'Item 1',
                    'active' => true,
                    'tags' => ['a', 'b'],
                    'meta' => [
                        'public' => true,
                        'hidden' => false,
                    ],
                ],
                [
                    'name' => 'Item 2',
                    'active' => false,
                    'tags' => ['c', 'd'],
                    'meta' => [
                        'public' => false,
                        'hidden' => true,
                    ],
                ],
            ],
        ];

        $this->service->flatten($data);
    }
}
