<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Services;

use AndyDefer\LaravelCluster\Services\FlatArrayService;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class FlatArrayServiceTest extends TestCase
{
    private FlatArrayService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FlatArrayService;
    }

    // ==================== BASIC TESTS ====================

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

    // ==================== INDEXED ARRAY TESTS ====================

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

    // ==================== NESTED ARRAY TESTS ====================

    public function test_flattens_nested_associative_array_with_dot_notation(): void
    {
        $input = [
            'name' => 'Dupont',
            'address' => [
                'city' => 'Lyon',
                'postal_code' => '69000',
                'country' => 'France',
            ],
        ];

        $expected = [
            'name' => 'Dupont',
            'address.city' => 'Lyon',
            'address.postal_code' => '69000',
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

    // ==================== NESTED WITH INDEXED ARRAYS TESTS ====================

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

    public function test_handles_empty_indexed_array_inside_nested(): void
    {
        $input = [
            'profile' => [
                'name' => 'John',
                'tags' => [],
            ],
        ];

        $expected = [
            'profile.name' => 'John',
            'profile.tags' => null,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    // ==================== REAL CLUSTER TESTS ====================

    public function test_flattens_real_cluster_with_nested_address(): void
    {
        $input = [
            'type' => 'user',
            'status' => 'active',
            'role' => 'admin',
            'verified' => 'true',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'lang_ln' => 'true',
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

    public function test_flattens_real_cluster_with_expanded_tags(): void
    {
        $input = [
            'type' => 'user',
            'status' => 'active',
            'role' => 'doctor',
            'verified' => true,
            'lang_fr' => 'false',
            'lang_en' => 'true',
            'lang_ln' => 'false',
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
            'tags_premium' => 'true',
            'tags_verified' => 'true',
            'tags_expert' => 'true',
            'score' => 92.0,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_real_cluster_with_nested_settings_and_tags(): void
    {
        $input = [
            'type' => 'user',
            'status' => 'active',
            'role' => 'admin',
            'verified' => 'true',
            'lang_fr' => 'true',
            'lang_en' => 'false',
            'lang_ln' => 'true',
            'age' => 40,
            'name' => 'Charlie Wilson',
            'settings' => [
                'notifications' => ['email', 'push', 'sms'],
                'theme' => 'dark',
                'language' => 'fr',
                'privacy' => [
                    'profile_visibility' => 'public',
                    'email_visibility' => 'private',
                ],
            ],
            'tags' => ['admin', 'verified', 'premium'],
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
            'settings.notifications_email' => 'true',
            'settings.notifications_push' => 'true',
            'settings.notifications_sms' => 'true',
            'settings.theme' => 'dark',
            'settings.language' => 'fr',
            'settings.privacy.profile_visibility' => 'public',
            'settings.privacy.email_visibility' => 'private',
            'tags_admin' => 'true',
            'tags_verified' => 'true',
            'tags_premium' => 'true',
            'score' => 95.0,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_real_cluster_with_nested_tags_in_deep_structure(): void
    {
        $input = [
            'user' => [
                'id' => 1,
                'name' => 'Laura Martinez',
                'status' => 'active',
                'professional' => [
                    'role' => 'doctor',
                    'department' => 'cardiology',
                    'specialities' => ['cardiology', 'neurology', 'pediatrics'],
                    'certifications' => ['ECG', 'Neuro', 'Pediatric'],
                ],
                'personal' => [
                    'languages' => ['fr', 'en', 'es', 'ln'],
                    'interests' => ['sports', 'music', 'reading'],
                ],
                'tags' => ['verified', 'premium', 'expert'],
            ],
        ];

        $expected = [
            'user.id' => 1,
            'user.name' => 'Laura Martinez',
            'user.status' => 'active',
            'user.professional.role' => 'doctor',
            'user.professional.department' => 'cardiology',
            'user.professional.specialities_cardiology' => 'true',
            'user.professional.specialities_neurology' => 'true',
            'user.professional.specialities_pediatrics' => 'true',
            'user.professional.certifications_ECG' => 'true',
            'user.professional.certifications_Neuro' => 'true',
            'user.professional.certifications_Pediatric' => 'true',
            'user.personal.languages_fr' => 'true',
            'user.personal.languages_en' => 'true',
            'user.personal.languages_es' => 'true',
            'user.personal.languages_ln' => 'true',
            'user.personal.interests_sports' => 'true',
            'user.personal.interests_music' => 'true',
            'user.personal.interests_reading' => 'true',
            'user.tags_verified' => 'true',
            'user.tags_premium' => 'true',
            'user.tags_expert' => 'true',
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    public function test_flattens_cluster_with_nested_empty_tags(): void
    {
        $input = [
            'user' => [
                'name' => 'John',
                'settings' => [
                    'notifications' => [],
                    'theme' => 'dark',
                ],
                'tags' => [],
            ],
        ];

        $expected = [
            'user.name' => 'John',
            'user.settings.notifications' => null,
            'user.settings.theme' => 'dark',
            'user.tags' => null,
        ];

        $this->assertSame($expected, $this->service->flatten($input));
    }

    // ==================== ERROR CASES ====================

    public function test_throws_exception_for_nested_indexed_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nested arrays are not supported for key "tags"');

        $input = [
            'tags' => [
                ['php', 'js'],
                ['kotlin', 'rust'],
            ],
        ];

        $this->service->flatten($input);
    }

    public function test_throws_exception_for_nested_indexed_array_inside_nested(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nested arrays are not supported for key "user.tags"');

        $input = [
            'user' => [
                'name' => 'John',
                'tags' => [
                    ['php', 'js'],
                    ['kotlin', 'rust'],
                ],
            ],
        ];

        $this->service->flatten($input);
    }

    public function test_throws_exception_for_unsupported_value_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No normalizer found for type Illuminate\Http\Request');

        $input = [
            'invalid' => new Request,
        ];

        dump($this->service->flatten($input));
        $this->service->flatten($input);
    }

    // ==================== EDGE CASES ====================

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

    // ==================== UNFLATTEN TESTS ====================

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
            'address.postal_code' => '69000',
            'address.country' => 'France',
        ];

        $expected = [
            'address' => [
                'city' => 'Lyon',
                'postal_code' => '69000',
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
}
