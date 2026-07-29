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
            'languages_fr' => 'true',
            'languages_en' => 'true',
            'languages_es' => 'true',
            'languages_ln' => 'true',
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
            'scores_10' => 'true',
            'scores_20' => 'true',
            'scores_30' => 'true',
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
            'codes_ABC' => 'true',
            'codes_123' => 'true',
            'codes_DEF' => 'true',
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
            'profile.tags_php' => 'true',
            'profile.tags_js' => 'true',
            'profile.tags_kotlin' => 'true',
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
            'user.settings.notifications_email' => 'true',
            'user.settings.notifications_push' => 'true',
            'user.tags_premium' => 'true',
            'user.tags_verified' => 'true',
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
        $this->assertEquals('true', $result['data.simple_a']);
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
            'user.tags_php' => 'true',
            'user.tags_js' => 'true',
        ];

        $this->assertSame($expected, $this->service->flatten($input, 'user'));
    }

    public function test_handles_tags_with_same_name_as_key(): void
    {
        $input = [
            'status' => 'active',
            'status_active' => 'true',
            'tags' => ['active', 'inactive'],
        ];

        $expected = [
            'status' => 'active',
            'status_active' => 'true',
            'tags_active' => 'true',
            'tags_inactive' => 'true',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_handles_boolean_values(): void
    {
        $input = [
            'active' => true,
            'verified' => false,
        ];

        $expected = [
            'active' => 'true',
            'verified' => 'false',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_handles_null_values_in_array(): void
    {
        $input = [
            'tags' => ['php', null, 'js'],
        ];

        $expected = [
            'tags_php' => 'true',
            'tags_null' => 'true',
            'tags_js' => 'true',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_handles_mixed_types_in_indexed_array(): void
    {
        $input = [
            'values' => ['string', 123, 45.67, true, false, null],
        ];

        $result = $this->service->flatten($input);

        $this->assertArrayHasKey('values_string', $result);
        $this->assertEquals('true', $result['values_string']);
        $this->assertArrayHasKey('values_123', $result);
        $this->assertEquals('true', $result['values_123']);
        $this->assertArrayHasKey('values_45.67', $result);
        $this->assertEquals('true', $result['values_45.67']);
        $this->assertArrayHasKey('values_true', $result);
        $this->assertEquals('true', $result['values_true']);
        $this->assertArrayHasKey('values_false', $result);
        $this->assertEquals('true', $result['values_false']);
        $this->assertArrayHasKey('values_null', $result);
        $this->assertEquals('true', $result['values_null']);
    }

    // ==================== TESTS POUR convertBooleansToStrings ====================

    public function test_flatten_converts_simple_boolean_to_string(): void
    {
        $data = [
            'active' => true,
            'verified' => false,
        ];

        $result = $this->service->flatten($data);

        $this->assertSame('true', $result['active']);
        $this->assertSame('false', $result['verified']);
    }

    public function test_flatten_converts_nested_boolean_to_string(): void
    {
        $data = [
            'user' => [
                'active' => true,
                'verified' => false,
                'name' => 'John',
            ],
        ];

        $result = $this->service->flatten($data);

        $this->assertSame('true', $result['user.active']);
        $this->assertSame('false', $result['user.verified']);
        $this->assertSame('John', $result['user.name']);
    }

    public function test_flatten_converts_deeply_nested_boolean_to_string(): void
    {
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

        $result = $this->service->flatten($data);

        $this->assertSame('true', $result['settings.notifications.email']);
        $this->assertSame('false', $result['settings.notifications.sms']);
        $this->assertSame('true', $result['settings.notifications.push']);
        $this->assertSame('dark', $result['settings.theme']);
    }

    public function test_flatten_converts_booleans_in_indexed_array_with_nested_arrays(): void
    {
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

        $result = $this->service->flatten($data);

        // Les tableaux indexés avec des tableaux imbriqués sont JSON encodés
        $this->assertArrayHasKey('addresses', $result);

        $decoded = json_decode($result['addresses'], true);
        $this->assertIsArray($decoded);

        // Vérifier que les booléens dans le JSON sont convertis en strings
        $this->assertSame('true', $decoded[0]['is_primary']);
        $this->assertSame('false', $decoded[0]['active']);
        $this->assertSame('false', $decoded[1]['is_primary']);
        $this->assertSame('true', $decoded[1]['active']);
    }

    public function test_flatten_converts_booleans_in_deeply_nested_indexed_array(): void
    {
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

        $result = $this->service->flatten($data);

        // Vérifier que settings.notifications est JSON encodé
        $this->assertArrayHasKey('settings.notifications', $result);
        $this->assertArrayHasKey('settings.theme', $result);

        $decoded = json_decode($result['settings.notifications'], true);
        $this->assertIsArray($decoded);

        // Vérifier que les booléens sont convertis en strings
        $this->assertSame('true', $decoded[0]['email']);
        $this->assertSame('false', $decoded[0]['sms']);
        $this->assertSame('true', $decoded[0]['push']);
        $this->assertSame('false', $decoded[1]['email']);
        $this->assertSame('true', $decoded[1]['sms']);
        $this->assertSame('false', $decoded[1]['push']);
        $this->assertSame('dark', $result['settings.theme']);
    }

    public function test_flatten_converts_booleans_in_mixed_array(): void
    {
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

        $result = $this->service->flatten($data);

        // Vérifier les scalaires
        $this->assertSame('John Doe', $result['user.name']);
        $this->assertSame('true', $result['user.active']);
        $this->assertSame(30, $result['user.age']);
        $this->assertSame('true', $result['user.preferences.notifications']);
        $this->assertSame('dark', $result['user.preferences.theme']);
        $this->assertSame('fr', $result['user.preferences.language']);

        // Vérifier que les tags sont expansés (tableau indexé → clés séparées)
        $this->assertSame('true', $result['user.tags_php']);
        $this->assertSame('true', $result['user.tags_js']);
        $this->assertSame('true', $result['user.tags_docker']);

        // Vérifier que 'user.addresses' est JSON encodé (tableau indexé avec des tableaux imbriqués)
        $this->assertArrayHasKey('user.addresses', $result);
        $this->assertIsString($result['user.addresses']);
        $addressesDecoded = json_decode($result['user.addresses'], true);
        $this->assertSame('true', $addressesDecoded[0]['is_primary']);
        $this->assertSame('false', $addressesDecoded[1]['is_primary']);
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

    public function test_flatten_converts_booleans_in_empty_arrays(): void
    {
        $data = [
            'empty_array' => [],
            'nested_empty' => [
                'empty' => [],
                'active' => true,
            ],
        ];

        $result = $this->service->flatten($data);

        $this->assertArrayHasKey('empty_array', $result);
        $this->assertNull($result['empty_array']);
        $this->assertArrayHasKey('nested_empty.empty', $result);
        $this->assertNull($result['nested_empty.empty']);
        $this->assertSame('true', $result['nested_empty.active']);
    }

    public function test_flatten_converts_booleans_with_null_values(): void
    {
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

        $result = $this->service->flatten($data);

        $this->assertSame('John', $result['user.name']);
        $this->assertNull($result['user.email']);
        $this->assertSame('true', $result['user.verified']);
        $this->assertNull($result['user.settings.theme']);
        $this->assertSame('false', $result['user.settings.notifications']);
    }

    public function test_unflatten_converts_string_true_false_to_booleans(): void
    {
        $data = [
            'user.active' => 'true',
            'user.verified' => 'false',
            'user.name' => 'John',
        ];

        $result = $this->service->unflatten($data);

        // Le service convertit 'true'/'false' en vrais booléens via normalizer_chain()
        $this->assertTrue($result['user']['active']);
        $this->assertFalse($result['user']['verified']);
        $this->assertSame('John', $result['user']['name']);
    }

    public function test_flatten_and_unflatten_roundtrip_with_booleans(): void
    {
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

        $flattened = $this->service->flatten($original);
        $unflattened = $this->service->unflatten($flattened);

        // Les booléens deviennent des strings dans les données aplaties
        $this->assertSame('true', $flattened['active']);
        $this->assertSame('true', $flattened['preferences.notifications']);
        $this->assertSame('false', $flattened['preferences.email']);

        // Les tags sont expansés en clés séparées
        $this->assertSame('true', $flattened['tags_php']);
        $this->assertSame('true', $flattened['tags_js']);

        // Les adresses sont JSON encodées (tableau indexé avec tableaux imbriqués)
        $this->assertArrayHasKey('addresses', $flattened);
        $this->assertIsString($flattened['addresses']);
        $addressesDecoded = json_decode($flattened['addresses'], true);
        $this->assertSame('true', $addressesDecoded[0]['is_primary']);

        // Dans le unflatten, les booléens redeviennent des vrais booléens
        $this->assertTrue($unflattened['active']);
        $this->assertTrue($unflattened['preferences']['notifications']);
        $this->assertFalse($unflattened['preferences']['email']);

        // Les tags expansés redeviennent un tableau
        $this->assertSame(['php', 'js'], $unflattened['tags']);

        // Les adresses redeviennent un tableau
        $this->assertSame('Kinshasa', $unflattened['addresses'][0]['city']);
        $this->assertTrue((bool) $unflattened['addresses'][0]['is_primary']);
    }

    public function test_flatten_with_complex_nested_structures(): void
    {
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

        $result = $this->service->flatten($data);

        $this->assertSame('true', $result['level1.level2.level3.active']);
        $this->assertSame('true', $result['level1.level2.level3.settings.notifications']);
        $this->assertSame('light', $result['level1.level2.level3.settings.theme']);

        // Vérifier que les tableaux imbriqués sont JSON encodés avec les booléens convertis
        $this->assertArrayHasKey('level1.level2.level3.items', $result);
        $decoded = json_decode($result['level1.level2.level3.items'], true);
        $this->assertSame('true', $decoded[0]['enabled']);
        $this->assertSame('false', $decoded[1]['enabled']);
    }

    public function test_flatten_converts_boolean_in_associative_array_inside_indexed_array(): void
    {
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

        $result = $this->service->flatten($data);

        // Le tableau 'items' est JSON encodé car c'est un tableau indexé avec des tableaux imbriqués
        $this->assertArrayHasKey('items', $result);
        $decoded = json_decode($result['items'], true);

        $this->assertSame('true', $decoded[0]['active']);
        $this->assertSame('true', $decoded[0]['meta']['public']);
        $this->assertSame('false', $decoded[0]['meta']['hidden']);
        $this->assertSame('false', $decoded[1]['active']);
        $this->assertSame('false', $decoded[1]['meta']['public']);
        $this->assertSame('true', $decoded[1]['meta']['hidden']);
    }
}
