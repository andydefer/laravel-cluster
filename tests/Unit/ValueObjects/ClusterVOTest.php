<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\ValueObjects;

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class ClusterVOTest extends TestCase
{
    public function test_flattens_simple_array(): void
    {
        $input = [
            'name' => 'Dupont',
            'age' => 30,
            'active' => 'true',
        ];

        $cluster = new ClusterVO($input);

        $this->assertEquals('Dupont', $cluster->get('name'));
        $this->assertEquals(30, $cluster->get('age'));
        $this->assertEquals('true', $cluster->get('active'));
    }

    public function test_flattens_nested_array_with_dot_notation(): void
    {
        $input = [
            'name' => 'Dupont',
            'address' => [
                'city' => 'Lyon',
                'postal_code' => 69000,
            ],
        ];

        $cluster = new ClusterVO($input);

        $this->assertEquals('Dupont', $cluster->get('name'));
        $this->assertEquals('Lyon', $cluster->get('address.city'));
        $this->assertEquals(69000, $cluster->get('address.postal_code'));
    }

    public function test_expands_indexed_array_to_true_keys(): void
    {
        $input = [
            'name' => 'Dupont',
            'languages' => ['fr', 'en', 'es'],
        ];

        $cluster = new ClusterVO($input);

        $this->assertEquals('Dupont', $cluster->get('name'));
        $this->assertEquals('true', $cluster->get('languages_fr'));
        $this->assertEquals('true', $cluster->get('languages_en'));
        $this->assertEquals('true', $cluster->get('languages_es'));
    }

    public function test_handles_empty_indexed_array_as_null(): void
    {
        $input = [
            'name' => 'Dupont',
            'tags' => [],
        ];

        $cluster = new ClusterVO($input);

        $this->assertEquals('Dupont', $cluster->get('name'));
        $this->assertNull($cluster->get('tags'));
    }

    public function test_handles_boolean_values_converted_to_strings(): void
    {
        $input = [
            'active' => true,
            'verified' => false,
        ];

        $cluster = new ClusterVO($input);

        $this->assertEquals('true', $cluster->get('active'));
        $this->assertEquals('false', $cluster->get('verified'));
    }

    public function test_handles_boolean_in_nested_array(): void
    {
        $input = [
            'user' => [
                'active' => true,
                'verified' => false,
                'settings' => [
                    'notifications' => true,
                    'dark_mode' => false,
                ],
            ],
        ];

        $cluster = new ClusterVO($input);

        $this->assertEquals('true', $cluster->get('user.active'));
        $this->assertEquals('false', $cluster->get('user.verified'));
        $this->assertEquals('true', $cluster->get('user.settings.notifications'));
        $this->assertEquals('false', $cluster->get('user.settings.dark_mode'));
    }

    public function test_flattens_deeply_nested_structure(): void
    {
        $input = [
            'user' => [
                'personal' => [
                    'name' => 'John',
                    'languages' => ['fr', 'en'],
                ],
                'professional' => [
                    'role' => 'admin',
                    'tags' => ['premium', 'verified'],
                ],
            ],
        ];

        $cluster = new ClusterVO($input);

        $this->assertEquals('John', $cluster->get('user.personal.name'));
        $this->assertEquals('true', $cluster->get('user.personal.languages_fr'));
        $this->assertEquals('true', $cluster->get('user.personal.languages_en'));
        $this->assertEquals('admin', $cluster->get('user.professional.role'));
        $this->assertEquals('true', $cluster->get('user.professional.tags_premium'));
        $this->assertEquals('true', $cluster->get('user.professional.tags_verified'));
    }

    public function test_handles_mixed_types(): void
    {
        $input = [
            'string' => 'value',
            'int' => 42,
            'float' => 3.14,
            'null' => null,
            'bool_true' => true,
            'bool_false' => false,
        ];

        $cluster = new ClusterVO($input);

        $this->assertEquals('value', $cluster->get('string'));
        $this->assertEquals(42, $cluster->get('int'));
        $this->assertEquals(3.14, $cluster->get('float'));
        $this->assertNull($cluster->get('null'));
        $this->assertEquals('true', $cluster->get('bool_true'));
        $this->assertEquals('false', $cluster->get('bool_false'));
    }

    public function test_throws_exception_for_object_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster values must be string, int, float, bool, array or null. Got object for key "invalid"');

        $input = [
            'name' => 'John',
            'invalid' => new Request,
        ];

        new ClusterVO($input);
    }

    public function test_json_encodes_nested_indexed_array(): void
    {
        $input = [
            'name' => 'John',
            'tags' => [
                ['php', 'js'],
                ['kotlin', 'rust'],
            ],
        ];

        $cluster = new ClusterVO($input);

        $this->assertEquals('John', $cluster->get('name'));

        $tags = $cluster->get('tags');
        $this->assertIsString($tags);
        $this->assertJson($tags);

        $decoded = json_decode($tags, true);
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

        $cluster = new ClusterVO($input);

        $this->assertEquals('John', $cluster->get('user.name'));

        $tags = $cluster->get('user.tags');
        $this->assertIsString($tags);
        $this->assertJson($tags);

        $decoded = json_decode($tags, true);
        $this->assertEquals([['php', 'js'], ['kotlin', 'rust']], $decoded);
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

        $cluster = new ClusterVO($input);

        $value = $cluster->get('levels.level1.level2.level3');
        $this->assertIsString($value);
        $this->assertJson($value);

        $decoded = json_decode($value, true);
        $this->assertEquals([['a', 'b'], ['c', 'd']], $decoded);
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

        $cluster = new ClusterVO($input);

        $this->assertEquals('true', $cluster->get('data.simple_a'));
        $this->assertEquals('true', $cluster->get('data.simple_b'));
        $this->assertEquals('true', $cluster->get('data.simple_c'));

        $nested = $cluster->get('data.nested');
        $this->assertIsString($nested);
        $this->assertJson($nested);
        $this->assertEquals([['x', 'y'], ['z', 'w']], json_decode($nested, true));

        $this->assertEquals('value1', $cluster->get('data.associative.key1'));
        $this->assertEquals('value2', $cluster->get('data.associative.key2'));
    }

    public function test_throws_exception_for_resource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster values cannot be resources. Got resource for key "invalid"');

        $input = [
            'invalid' => fopen('php://memory', 'r'),
        ];

        new ClusterVO($input);
    }

    public function test_throws_exception_empty_cluster(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster cannot be empty');

        new ClusterVO([]);
    }

    public function test_get_nested_data_returns_original_structure(): void
    {
        $input = [
            'type' => 'user',
            'address' => [
                'city' => 'Paris',
                'postal_code' => 75001,
            ],
            'tags' => [
                ['php', 'js'],
                ['kotlin', 'rust'],
            ],
        ];

        $cluster = new ClusterVO($input);
        $nested = $cluster->getNestedData();

        $this->assertIsArray($nested);
        $this->assertEquals('user', $nested['type']);
        $this->assertEquals('Paris', $nested['address']['city']);
        $this->assertEquals(75001, $nested['address']['postal_code']);
        $this->assertEquals([['php', 'js'], ['kotlin', 'rust']], $nested['tags']);
    }

    public function test_real_cluster_example(): void
    {
        $input = [
            'type' => 'user',
            'status' => 'active',
            'role' => 'admin',
            'verified' => true,
            'lang_fr' => true,
            'lang_en' => false,
            'lang_ln' => true,
            'age' => 25,
            'name' => 'John Doe',
            'address' => [
                'street' => '123 Main St',
                'city' => 'Paris',
                'postal_code' => 75001,
            ],
            'tags' => ['premium', 'verified', 'expert'],
            'score' => 85.5,
        ];

        $cluster = new ClusterVO($input);

        $this->assertEquals('user', $cluster->get('type'));
        $this->assertEquals('active', $cluster->get('status'));
        $this->assertEquals('admin', $cluster->get('role'));
        $this->assertEquals('true', $cluster->get('verified'));
        $this->assertEquals(25, $cluster->get('age'));
        $this->assertEquals('John Doe', $cluster->get('name'));
        $this->assertEquals(85.5, $cluster->get('score'));
        $this->assertEquals('Paris', $cluster->get('address.city'));
        $this->assertEquals(75001, $cluster->get('address.postal_code'));
        $this->assertEquals('true', $cluster->get('tags_premium'));
        $this->assertEquals('true', $cluster->get('tags_verified'));
        $this->assertEquals('true', $cluster->get('tags_expert'));
        $this->assertEquals('true', $cluster->get('lang_fr'));
        $this->assertEquals('false', $cluster->get('lang_en'));
        $this->assertEquals('true', $cluster->get('lang_ln'));

        $this->assertTrue($cluster->has('tags_premium'));
        $this->assertTrue($cluster->has('address.city'));
        $this->assertFalse($cluster->has('nonexistent'));

        $keys = $cluster->keys();
        $this->assertContains('type', $keys);
        $this->assertContains('address.city', $keys);
        $this->assertContains('tags_premium', $keys);
    }

    public function test_real_cluster_with_nested_tags_json_encoded(): void
    {
        $input = [
            'type' => 'user',
            'status' => 'active',
            'name' => 'John Doe',
            'passions' => ['musique', 'danse', 'ballet'],
            'tags' => [
                ['php', 'js'],
                ['kotlin', 'rust'],
            ],
            'address' => [
                'city' => 'Paris',
                'nested' => [
                    ['a', 'b'],
                    ['c', 'd'],
                ],
            ],
        ];

        $cluster = new ClusterVO($input);

        $this->assertEquals('user', $cluster->get('type'));
        $this->assertEquals('active', $cluster->get('status'));
        $this->assertEquals('John Doe', $cluster->get('name'));

        $tags = $cluster->get('tags');
        $this->assertIsString($tags);
        $this->assertJson($tags);
        $this->assertEquals([['php', 'js'], ['kotlin', 'rust']], json_decode($tags, true));

        $this->assertEquals('Paris', $cluster->get('address.city'));

        $nested = $cluster->get('address.nested');
        $this->assertIsString($nested);
        $this->assertJson($nested);
        $this->assertEquals([['a', 'b'], ['c', 'd']], json_decode($nested, true));
    }
}
