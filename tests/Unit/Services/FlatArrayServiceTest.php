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

    // ==================== CLUSTER REAL CASES ====================

    public function test_flattens_real_cluster_with_nested_address(): void
    {
        // Cluster réel avec adresse imbriquée
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
                'postal_code' => '75001',
                'country' => 'France',
            ],
            'score' => 85.5,
        ];

        $expected = [
            'type' => 'user',
            'status' => 'active',
            'role' => 'admin',
            'verified' => 'true',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'lang_ln' => 'true',
            'age' => 25,
            'name' => 'John Doe',
            'address.street' => '123 Main St',
            'address.city' => 'Paris',
            'address.postal_code' => '75001',
            'address.country' => 'France',
            'score' => 85.5,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_real_cluster_with_tags(): void
    {
        // Cluster réel avec tags (tableau indexé)
        $input = [
            'type' => 'user',
            'status' => 'active',
            'role' => 'doctor',
            'verified' => true,
            'lang_fr' => false,
            'lang_en' => true,
            'lang_ln' => false,
            'age' => 30,
            'name' => 'Jane Smith',
            'tags' => ['premium', 'verified', 'expert'],
            'score' => 92.0,
        ];

        $expected = [
            'type' => 'user',
            'status' => 'active',
            'role' => 'doctor',
            'verified' => 'true',
            'lang_fr' => 'false',
            'lang_en' => 'true',
            'lang_ln' => 'false',
            'age' => 30,
            'name' => 'Jane Smith',
            'tags_0' => 'premium',
            'tags_1' => 'verified',
            'tags_2' => 'expert',
            'score' => 92.0,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_real_cluster_with_empty_tags(): void
    {
        // Cluster réel avec tags vide
        $input = [
            'type' => 'user',
            'status' => 'inactive',
            'role' => 'moderator',
            'verified' => false,
            'lang_fr' => false,
            'lang_en' => true,
            'lang_ln' => false,
            'age' => 22,
            'name' => 'Bob Johnson',
            'tags' => [],
            'score' => 45.0,
        ];

        $expected = [
            'type' => 'user',
            'status' => 'inactive',
            'role' => 'moderator',
            'verified' => 'false',
            'lang_fr' => 'false',
            'lang_en' => 'true',
            'lang_ln' => 'false',
            'age' => 22,
            'name' => 'Bob Johnson',
            'tags' => null,
            'score' => 45.0,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_real_cluster_with_nested_settings(): void
    {
        // Cluster réel avec paramètres imbriqués
        $input = [
            'type' => 'user',
            'status' => 'active',
            'role' => 'admin',
            'verified' => true,
            'lang_fr' => true,
            'lang_en' => false,
            'lang_ln' => true,
            'age' => 40,
            'name' => 'Charlie Wilson',
            'settings' => [
                'notifications' => true,
                'theme' => 'dark',
                'language' => 'fr',
                'privacy' => [
                    'profile_visibility' => 'public',
                    'email_visibility' => 'private',
                ],
            ],
            'score' => 95.0,
        ];

        $expected = [
            'type' => 'user',
            'status' => 'active',
            'role' => 'admin',
            'verified' => 'true',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'lang_ln' => 'true',
            'age' => 40,
            'name' => 'Charlie Wilson',
            'settings.notifications' => 'true',
            'settings.theme' => 'dark',
            'settings.language' => 'fr',
            'settings.privacy.profile_visibility' => 'public',
            'settings.privacy.email_visibility' => 'private',
            'score' => 95.0,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_real_cluster_with_skills(): void
    {
        // Cluster réel avec compétences (tableau indexé)
        $input = [
            'type' => 'user',
            'status' => 'active',
            'role' => 'doctor',
            'verified' => true,
            'lang_fr' => true,
            'lang_en' => false,
            'lang_ln' => true,
            'age' => 33,
            'name' => 'Maria Garcia',
            'skills' => ['php', 'laravel', 'javascript', 'vuejs'],
            'experience' => [
                'years' => 8,
                'level' => 'senior',
            ],
            'score' => 90.0,
        ];

        $expected = [
            'type' => 'user',
            'status' => 'active',
            'role' => 'doctor',
            'verified' => 'true',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'lang_ln' => 'true',
            'age' => 33,
            'name' => 'Maria Garcia',
            'skills_0' => 'php',
            'skills_1' => 'laravel',
            'skills_2' => 'javascript',
            'skills_3' => 'vuejs',
            'experience.years' => 8,
            'experience.level' => 'senior',
            'score' => 90.0,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_real_cluster_with_mixed_arrays(): void
    {
        // Cluster réel avec plusieurs tableaux indexés et imbriqués
        $input = [
            'id' => 1,
            'name' => 'Laura Martinez',
            'status' => 'active',
            'role' => 'doctor',
            'verified' => true,
            'languages' => ['fr', 'en', 'es', 'ln'],
            'specialities' => ['cardiology', 'neurology'],
            'certifications' => [
                ['name' => 'ECG Certification', 'year' => 2020],
                ['name' => 'Neuro Advanced', 'year' => 2022],
            ],
            'contact' => [
                'email' => 'laura@example.com',
                'phone' => '+33123456789',
                'address' => [
                    'street' => '123 Rue de Paris',
                    'city' => 'Lyon',
                    'postal_code' => '69000',
                ],
            ],
            'score' => 93.0,
        ];

        $expected = [
            'id' => 1,
            'name' => 'Laura Martinez',
            'status' => 'active',
            'role' => 'doctor',
            'verified' => 'true',
            'languages_0' => 'fr',
            'languages_1' => 'en',
            'languages_2' => 'es',
            'languages_3' => 'ln',
            'specialities_0' => 'cardiology',
            'specialities_1' => 'neurology',
            'certifications_0.name' => 'ECG Certification',
            'certifications_0.year' => 2020,
            'certifications_1.name' => 'Neuro Advanced',
            'certifications_1.year' => 2022,
            'contact.email' => 'laura@example.com',
            'contact.phone' => '+33123456789',
            'contact.address.street' => '123 Rue de Paris',
            'contact.address.city' => 'Lyon',
            'contact.address.postal_code' => '69000',
            'score' => 93.0,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_real_cluster_with_all_boolean_values(): void
    {
        $input = [
            'status' => 'active',
            'lang_fr' => true,
            'lang_en' => false,
            'lang_ln' => true,
            'verified' => true,
            'is_admin' => false,
            'is_doctor' => true,
            'notifications_enabled' => false,
        ];

        $expected = [
            'status' => 'active',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'lang_ln' => 'true',
            'verified' => 'true',
            'is_admin' => 'false',
            'is_doctor' => 'true',
            'notifications_enabled' => 'false',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_real_cluster_with_null_values(): void
    {
        $input = [
            'status' => 'active',
            'role' => null,
            'deleted_at' => null,
            'name' => 'John Doe',
            'age' => 25,
            'score' => null,
        ];

        $expected = [
            'status' => 'active',
            'role' => null,
            'deleted_at' => null,
            'name' => 'John Doe',
            'age' => 25,
            'score' => null,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    // ==================== EXCEPTION CASES ====================

    public function test_throws_exception_for_nested_indexed_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nested indexed arrays are not supported for key "tags_0"');

        $input = [
            'tags' => [
                ['php', 'js'],
                ['kotlin', 'rust'],
            ],
        ];

        $this->service->flatten($input);
    }

    public function test_throws_exception_for_unsupported_value_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported value type "object" for key "invalid"');

        $input = [
            'invalid' => new \stdClass,
        ];

        $this->service->flatten($input);
    }

    // ==================== EDGE CASES ====================

    public function test_flattens_empty_array(): void
    {
        $input = [];

        $expected = [];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_array_with_numeric_keys(): void
    {
        $input = [
            '0' => 'first',
            '1' => 'second',
        ];

        $expected = [
            '0' => 'first',
            '1' => 'second',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flatten_and_normalize(): void
    {
        $input = [
            'active' => true,
            'score' => 95.5,
            'tags' => ['php', 'js'],
        ];

        $expected = [
            'active' => 'true',
            'score' => 95.5,
            'tags_0' => 'php',
            'tags_1' => 'js',
        ];

        $this->assertSame($expected, $this->service->flattenAndNormalize($input));
    }

    public function test_flattens_with_custom_prefix(): void
    {
        $input = [
            'name' => 'John',
            'address' => [
                'city' => 'Paris',
            ],
        ];

        $expected = [
            'user.name' => 'John',
            'user.address.city' => 'Paris',
        ];

        $this->assertSame($expected, $this->service->flatten($input, 'user'));
    }

    public function test_flattens_real_cluster_with_empty_nested_object(): void
    {
        $input = [
            'status' => 'active',
            'metadata' => [],
            'settings' => [
                'notifications' => [],
                'theme' => 'dark',
            ],
        ];

        $expected = [
            'status' => 'active',
            'metadata' => null,
            'settings.notifications' => null,
            'settings.theme' => 'dark',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }
}
