<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Proxies;

use AndyDefer\LaravelCluster\Proxies\ClusterVOProxy;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use PHPUnit\Framework\TestCase;

final class ClusterVOProxyTest extends TestCase
{
    public function test_make_converts_simple_boolean_to_yes_no(): void
    {
        $cluster = ClusterVOProxy::make([
            'active' => true,
            'verified' => false,
            'name' => 'John Doe',
        ]);

        $this->assertInstanceOf(ClusterVO::class, $cluster);
        $this->assertSame('yes', $cluster->get('active'));
        $this->assertSame('no', $cluster->get('verified'));
        $this->assertSame('John Doe', $cluster->get('name'));
    }

    public function test_make_converts_string_booleans_to_yes_no(): void
    {
        $cluster = ClusterVOProxy::make([
            'string_true' => 'true',
            'string_false' => 'false',
            'string_TRUE' => 'TRUE',
            'string_FALSE' => 'FALSE',
        ]);

        $this->assertInstanceOf(ClusterVO::class, $cluster);
        $this->assertSame('yes', $cluster->get('string_true'));
        $this->assertSame('no', $cluster->get('string_false'));
        $this->assertSame('yes', $cluster->get('string_TRUE'));
        $this->assertSame('no', $cluster->get('string_FALSE'));
    }

    public function test_make_preserves_yes_no_strings(): void
    {
        $cluster = ClusterVOProxy::make([
            'string_yes' => 'yes',
            'string_no' => 'no',
        ]);

        $this->assertInstanceOf(ClusterVO::class, $cluster);
        $this->assertSame('yes', $cluster->get('string_yes'));
        $this->assertSame('no', $cluster->get('string_no'));
    }

    public function test_make_converts_nested_booleans_to_yes_no(): void
    {
        $cluster = ClusterVOProxy::make([
            'user' => [
                'active' => true,
                'verified' => false,
                'profile' => [
                    'is_public' => true,
                    'is_private' => false,
                    'string_true' => 'true',
                    'string_false' => 'false',
                ],
            ],
        ]);

        $this->assertInstanceOf(ClusterVO::class, $cluster);
        $this->assertSame('yes', $cluster->get('user.active'));
        $this->assertSame('no', $cluster->get('user.verified'));
        $this->assertSame('yes', $cluster->get('user.profile.is_public'));
        $this->assertSame('no', $cluster->get('user.profile.is_private'));
        $this->assertSame('yes', $cluster->get('user.profile.string_true'));
        $this->assertSame('no', $cluster->get('user.profile.string_false'));
    }

    public function test_make_preserves_non_boolean_values(): void
    {
        $cluster = ClusterVOProxy::make([
            'id' => 1,
            'name' => 'John Doe',
            'age' => 30,
            'score' => 85.5,
            'tags' => ['php', 'js'],
            'metadata' => null,
        ]);

        $this->assertInstanceOf(ClusterVO::class, $cluster);
        $this->assertSame(1, $cluster->get('id'));
        $this->assertSame('John Doe', $cluster->get('name'));
        $this->assertSame(30, $cluster->get('age'));
        $this->assertSame(85.5, $cluster->get('score'));
        $this->assertSame('yes', $cluster->get('tags_php'));
        $this->assertSame('yes', $cluster->get('tags_js'));
        $this->assertNull($cluster->get('metadata'));
    }

    public function test_make_handles_deeply_nested_structures(): void
    {
        $cluster = ClusterVOProxy::make([
            'level1' => [
                'level2' => [
                    'level3' => [
                        'active' => true,
                        'string_true' => 'true',
                        'items' => [
                            ['enabled' => true],
                            ['enabled' => false],
                            ['string' => 'true'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertInstanceOf(ClusterVO::class, $cluster);
        $this->assertSame('yes', $cluster->get('level1.level2.level3.active'));
        $this->assertSame('yes', $cluster->get('level1.level2.level3.string_true'));

        $items = $cluster->get('level1.level2.level3.items');
        $this->assertIsString($items);
        $decoded = json_decode($items, true);
        $this->assertSame('yes', $decoded[0]['enabled']);
        $this->assertSame('no', $decoded[1]['enabled']);
        $this->assertSame('yes', $decoded[2]['string']);
    }

    public function test_make_handles_mixed_types_in_array(): void
    {
        $cluster = ClusterVOProxy::make([
            'mixed' => [
                'string' => 'hello',
                'int' => 42,
                'float' => 3.14,
                'bool_true' => true,
                'bool_false' => false,
                'string_true' => 'true',
                'string_false' => 'false',
                'null' => null,
                'nested' => [
                    'active' => true,
                    'string' => 'true',
                ],
            ],
        ]);

        $this->assertInstanceOf(ClusterVO::class, $cluster);
        $this->assertSame('hello', $cluster->get('mixed.string'));
        $this->assertSame(42, $cluster->get('mixed.int'));
        $this->assertSame(3.14, $cluster->get('mixed.float'));
        $this->assertSame('yes', $cluster->get('mixed.bool_true'));
        $this->assertSame('no', $cluster->get('mixed.bool_false'));
        $this->assertSame('yes', $cluster->get('mixed.string_true'));
        $this->assertSame('no', $cluster->get('mixed.string_false'));
        $this->assertNull($cluster->get('mixed.null'));
        $this->assertSame('yes', $cluster->get('mixed.nested.active'));
        $this->assertSame('yes', $cluster->get('mixed.nested.string'));
    }

    public function test_make_returns_cluster_vo_with_array_access(): void
    {
        $cluster = ClusterVOProxy::make([
            'active' => true,
            'verified' => false,
            'string_true' => 'true',
        ]);

        $this->assertTrue(isset($cluster['active']));
        $this->assertTrue(isset($cluster['verified']));
        $this->assertTrue(isset($cluster['string_true']));
        $this->assertSame('yes', $cluster['active']);
        $this->assertSame('no', $cluster['verified']);
        $this->assertSame('yes', $cluster['string_true']);
    }

    public function test_make_handles_empty_array_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cluster cannot be empty');

        ClusterVOProxy::make([]);
    }

    public function test_make_handles_array_with_only_booleans(): void
    {
        $cluster = ClusterVOProxy::make([
            'all_true' => true,
            'all_false' => false,
            'string_true' => 'true',
            'string_false' => 'false',
            'nested' => [
                'true' => true,
                'false' => false,
                'string' => 'true',
            ],
        ]);

        $this->assertInstanceOf(ClusterVO::class, $cluster);
        $this->assertSame('yes', $cluster->get('all_true'));
        $this->assertSame('no', $cluster->get('all_false'));
        $this->assertSame('yes', $cluster->get('string_true'));
        $this->assertSame('no', $cluster->get('string_false'));
        $this->assertSame('yes', $cluster->get('nested.true'));
        $this->assertSame('no', $cluster->get('nested.false'));
        $this->assertSame('yes', $cluster->get('nested.string'));
    }
}
