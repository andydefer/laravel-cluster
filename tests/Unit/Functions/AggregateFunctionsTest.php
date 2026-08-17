<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Functions;

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Functions\AllFunction;
use AndyDefer\LaravelCluster\Functions\AvgFunction;
use AndyDefer\LaravelCluster\Functions\CountFunction;
use AndyDefer\LaravelCluster\Functions\DistanceFunction;
use AndyDefer\LaravelCluster\Functions\ExistsFunction;
use AndyDefer\LaravelCluster\Functions\HasFunction;
use AndyDefer\LaravelCluster\Functions\IsEmptyFunction;
use AndyDefer\LaravelCluster\Functions\LengthFunction;
use AndyDefer\LaravelCluster\Functions\MatchesFunction;
use AndyDefer\LaravelCluster\Functions\MaxFunction;
use AndyDefer\LaravelCluster\Functions\MinFunction;
use AndyDefer\LaravelCluster\Functions\SumFunction;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\PhpVo\ValueObjects\CoordinatesVO;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;
use PHPUnit\Framework\TestCase;

final class AggregateFunctionsTest extends TestCase
{
    // ==================== COUNT FUNCTION TESTS ====================

    public function test_count_function_with_array(): void
    {
        $function = new CountFunction;

        $data = ['items' => ['a', 'b', 'c']];

        $result = $function->execute($data, ['items']);

        $this->assertSame(3, $result);
    }

    public function test_count_function_with_string(): void
    {
        $function = new CountFunction;

        $data = ['name' => 'John Doe'];

        $result = $function->execute($data, ['name']);

        $this->assertSame(8, $result);
    }

    public function test_count_function_with_empty_array(): void
    {
        $function = new CountFunction;

        $data = ['items' => []];

        $result = $function->execute($data, ['items']);

        $this->assertSame(0, $result);
    }

    public function test_count_function_with_null_value(): void
    {
        $function = new CountFunction;

        $data = ['items' => null];

        $result = $function->execute($data, ['items']);

        $this->assertSame(0, $result);
    }

    public function test_count_function_with_nested_path(): void
    {
        $function = new CountFunction;

        $data = ['user' => ['addresses' => ['a', 'b', 'c', 'd']]];

        $result = $function->execute($data, ['user.addresses']);

        $this->assertSame(4, $result);
    }

    // ==================== SUM FUNCTION TESTS ====================

    public function test_sum_function_with_integers(): void
    {
        $function = new SumFunction;

        $data = ['prices' => [100, 200, 300]];

        $result = $function->execute($data, ['prices']);

        $this->assertSame(600.0, $result);
    }

    public function test_sum_function_with_floats(): void
    {
        $function = new SumFunction;

        $data = ['values' => [1.5, 2.5, 3.0]];

        $result = $function->execute($data, ['values']);

        $this->assertSame(7.0, $result);
    }

    public function test_sum_function_with_mixed_numeric_and_non_numeric(): void
    {
        $function = new SumFunction;

        $data = ['items' => [10, '20', 30, 'not a number', 40]];

        $result = $function->execute($data, ['items']);

        $this->assertSame(100.0, $result);
    }

    public function test_sum_function_with_empty_array(): void
    {
        $function = new SumFunction;

        $data = ['prices' => []];

        $result = $function->execute($data, ['prices']);

        $this->assertSame(0.0, $result);
    }

    public function test_sum_function_with_nested_array(): void
    {
        $function = new SumFunction;

        $data = ['orders' => [['total' => 100], ['total' => 200], ['total' => 300]]];

        $result = $function->execute($data, ['orders']);

        $this->assertSame(600.0, $result);
    }

    // ==================== AVG FUNCTION TESTS ====================

    public function test_avg_function_with_integers(): void
    {
        $function = new AvgFunction;

        $data = ['scores' => [80, 90, 100]];

        $result = $function->execute($data, ['scores']);

        $this->assertSame(90.0, $result);
    }

    public function test_avg_function_with_floats(): void
    {
        $function = new AvgFunction;

        $data = ['values' => [1.5, 2.5, 3.0]];

        $result = $function->execute($data, ['values']);

        $this->assertSame(2.3333333333333335, $result);
    }

    public function test_avg_function_with_mixed_numeric_and_non_numeric(): void
    {
        $function = new AvgFunction;

        $data = ['items' => [10, '20', 30, 'not a number', 40]];

        $result = $function->execute($data, ['items']);

        $this->assertSame(25.0, $result);
    }

    public function test_avg_function_with_empty_array(): void
    {
        $function = new AvgFunction;

        $data = ['scores' => []];

        $result = $function->execute($data, ['scores']);

        $this->assertSame(0.0, $result);
    }

    public function test_avg_function_with_single_item(): void
    {
        $function = new AvgFunction;

        $data = ['scores' => [85]];

        $result = $function->execute($data, ['scores']);

        $this->assertSame(85.0, $result);
    }

    // ==================== MIN FUNCTION TESTS ====================

    public function test_min_function_with_integers(): void
    {
        $function = new MinFunction;

        $data = ['scores' => [80, 90, 70, 95]];

        $result = $function->execute($data, ['scores']);

        $this->assertEquals(70, $result);
    }

    public function test_min_function_with_floats(): void
    {
        $function = new MinFunction;

        $data = ['values' => [1.5, 2.5, 0.5, 3.0]];

        $result = $function->execute($data, ['values']);

        $this->assertSame(0.5, $result);
    }

    public function test_min_function_with_mixed_numeric_and_non_numeric(): void
    {
        $function = new MinFunction;

        $data = ['items' => [10, '20', 30, 'not a number', 5]];

        $result = $function->execute($data, ['items']);

        $this->assertSame(5.0, $result);
    }

    public function test_min_function_with_empty_array(): void
    {
        $function = new MinFunction;

        $data = ['scores' => []];

        $result = $function->execute($data, ['scores']);

        $this->assertSame(0, $result);
    }

    // ==================== MAX FUNCTION TESTS ====================

    public function test_max_function_with_integers(): void
    {
        $function = new MaxFunction;

        $data = ['scores' => [80, 90, 70, 95]];

        $result = $function->execute($data, ['scores']);

        $this->assertEquals(95, $result);
    }

    public function test_max_function_with_floats(): void
    {
        $function = new MaxFunction;

        $data = ['values' => [1.5, 2.5, 0.5, 3.0]];

        $result = $function->execute($data, ['values']);

        $this->assertSame(3.0, $result);
    }

    public function test_max_function_with_mixed_numeric_and_non_numeric(): void
    {
        $function = new MaxFunction;

        $data = ['items' => [10, '20', 30, 'not a number', 5]];

        $result = $function->execute($data, ['items']);

        $this->assertSame(30.0, $result);
    }

    public function test_max_function_with_empty_array(): void
    {
        $function = new MaxFunction;

        $data = ['scores' => []];

        $result = $function->execute($data, ['scores']);

        $this->assertSame(0, $result);
    }

    // ==================== LENGTH FUNCTION TESTS ====================

    public function test_length_function_with_string(): void
    {
        $function = new LengthFunction;

        $data = ['name' => 'John Doe'];

        $result = $function->execute($data, ['name']);

        $this->assertSame(8, $result);
    }

    public function test_length_function_with_array(): void
    {
        $function = new LengthFunction;

        $data = ['tags' => ['php', 'js', 'css']];

        $result = $function->execute($data, ['tags']);

        $this->assertSame(3, $result);
    }

    public function test_length_function_with_empty_string(): void
    {
        $function = new LengthFunction;

        $data = ['name' => ''];

        $result = $function->execute($data, ['name']);

        $this->assertSame(0, $result);
    }

    public function test_length_function_with_empty_array(): void
    {
        $function = new LengthFunction;

        $data = ['tags' => []];

        $result = $function->execute($data, ['tags']);

        $this->assertSame(0, $result);
    }

    public function test_length_function_with_null_value(): void
    {
        $function = new LengthFunction;

        $data = ['name' => null];

        $result = $function->execute($data, ['name']);

        $this->assertSame(0, $result);
    }

    // ==================== EXISTS FUNCTION TESTS ====================

    public function test_exists_function_with_existing_value(): void
    {
        $function = new ExistsFunction;

        $data = ['user' => ['name' => 'John']];

        $result = $function->execute($data, ['user.name']);

        $this->assertTrue($result);
    }

    public function test_exists_function_with_existing_empty_string(): void
    {
        $function = new ExistsFunction;

        $data = ['user' => ['name' => '']];

        $result = $function->execute($data, ['user.name']);

        $this->assertFalse($result);
    }

    public function test_exists_function_with_existing_null(): void
    {
        $function = new ExistsFunction;

        $data = ['user' => ['name' => null]];

        $result = $function->execute($data, ['user.name']);

        $this->assertFalse($result);
    }

    public function test_exists_function_with_path_not_existing(): void
    {
        $function = new ExistsFunction;

        $data = ['user' => ['name' => 'John']];

        $result = $function->execute($data, ['user.email']);

        $this->assertFalse($result);
    }

    public function test_exists_function_with_nested_path(): void
    {
        $function = new ExistsFunction;

        $data = ['user' => ['profile' => ['active' => 'true']]];

        $result = $function->execute($data, ['user.profile.active']);

        $this->assertTrue($result);
    }

    // ==================== HAS FUNCTION TESTS ====================

    public function test_has_function_with_two_arguments_found(): void
    {
        $function = new HasFunction;

        $data = ['tags' => ['php', 'js', 'css']];

        $result = $function->execute($data, ['tags', 'php']);

        $this->assertTrue($result);
    }

    public function test_has_function_with_two_arguments_not_found(): void
    {
        $function = new HasFunction;

        $data = ['tags' => ['php', 'js', 'css']];

        $result = $function->execute($data, ['tags', 'python']);

        $this->assertFalse($result);
    }

    public function test_has_function_with_three_arguments_found(): void
    {
        $function = new HasFunction;

        $data = ['addresses' => [['city' => 'Kinshasa'], ['city' => 'Paris']]];

        $result = $function->execute($data, ['addresses', 'city', 'Kinshasa']);

        $this->assertTrue($result);
    }

    public function test_has_function_with_three_arguments_not_found(): void
    {
        $function = new HasFunction;

        $data = ['addresses' => [['city' => 'Kinshasa'], ['city' => 'Paris']]];

        $result = $function->execute($data, ['addresses', 'city', 'London']);

        $this->assertFalse($result);
    }

    public function test_has_function_with_empty_array(): void
    {
        $function = new HasFunction;

        $data = ['tags' => []];

        $result = $function->execute($data, ['tags', 'php']);

        $this->assertFalse($result);
    }

    public function test_has_function_with_nested_path(): void
    {
        $function = new HasFunction;

        $data = ['settings' => ['notifications' => [['type' => 'email'], ['type' => 'sms']]]];

        $result = $function->execute($data, ['settings.notifications', 'type', 'email']);

        $this->assertTrue($result);
    }

    // ==================== ALL FUNCTION TESTS ====================

    public function test_all_function_all_items_match(): void
    {
        $function = new AllFunction;

        $data = ['items' => [['status' => 'active'], ['status' => 'active']]];

        $result = $function->execute($data, ['items', 'status', 'active']);

        $this->assertTrue($result);
    }

    public function test_all_function_not_all_items_match(): void
    {
        $function = new AllFunction;

        $data = ['items' => [['status' => 'active'], ['status' => 'inactive']]];

        $result = $function->execute($data, ['items', 'status', 'active']);

        $this->assertFalse($result);
    }

    public function test_all_function_with_empty_array(): void
    {
        $function = new AllFunction;

        $data = ['items' => []];

        $result = $function->execute($data, ['items', 'status', 'active']);

        $this->assertFalse($result);
    }

    public function test_all_function_with_non_array(): void
    {
        $function = new AllFunction;

        $data = ['items' => 'not an array'];

        $result = $function->execute($data, ['items', 'status', 'active']);

        $this->assertFalse($result);
    }

    public function test_all_function_with_missing_key_in_item(): void
    {
        $function = new AllFunction;

        $data = ['items' => [['status' => 'active'], ['name' => 'John']]];

        $result = $function->execute($data, ['items', 'status', 'active']);

        $this->assertFalse($result);
    }

    // ==================== IS_EMPTY FUNCTION TESTS ====================

    public function test_is_empty_function_with_empty_array(): void
    {
        $function = new IsEmptyFunction;

        $data = ['tags' => []];

        $result = $function->execute($data, ['tags']);

        $this->assertTrue($result);
    }

    public function test_is_empty_function_with_non_empty_array(): void
    {
        $function = new IsEmptyFunction;

        $data = ['tags' => ['php', 'js']];

        $result = $function->execute($data, ['tags']);

        $this->assertFalse($result);
    }

    public function test_is_empty_function_with_empty_string(): void
    {
        $function = new IsEmptyFunction;

        $data = ['name' => ''];

        $result = $function->execute($data, ['name']);

        $this->assertTrue($result);
    }

    public function test_is_empty_function_with_non_empty_string(): void
    {
        $function = new IsEmptyFunction;

        $data = ['name' => 'John'];

        $result = $function->execute($data, ['name']);

        $this->assertFalse($result);
    }

    public function test_is_empty_function_with_null_value(): void
    {
        $function = new IsEmptyFunction;

        $data = ['name' => null];

        $result = $function->execute($data, ['name']);

        $this->assertTrue($result);
    }

    public function test_is_empty_function_with_zero_value(): void
    {
        $function = new IsEmptyFunction;

        $data = ['score' => 0];

        $result = $function->execute($data, ['score']);

        $this->assertFalse($result);
    }

    public function test_is_empty_function_with_false_value(): void
    {
        $function = new IsEmptyFunction;

        $data = ['active' => false];

        $result = $function->execute($data, ['active']);

        $this->assertFalse($result);
    }

    // ==================== MATCHES FUNCTION TESTS (REGEX) ====================

    public function test_matches_function_with_two_arguments_found(): void
    {
        $function = new MatchesFunction;

        $data = ['tags' => ['php', 'javascript', 'css']];

        $result = $function->execute($data, ['tags', '/^ja.*/']);

        $this->assertTrue($result);
    }

    public function test_matches_function_with_two_arguments_not_found(): void
    {
        $function = new MatchesFunction;

        $data = ['tags' => ['php', 'js', 'css']];

        $result = $function->execute($data, ['tags', '/^python.*/']);

        $this->assertFalse($result);
    }

    public function test_matches_function_with_three_arguments_found(): void
    {
        $function = new MatchesFunction;

        $data = ['addresses' => [['city' => 'Kinshasa'], ['city' => 'Paris']]];

        $result = $function->execute($data, ['addresses', 'city', '/^Kin.*/']);

        $this->assertTrue($result);
    }

    public function test_matches_function_with_three_arguments_not_found(): void
    {
        $function = new MatchesFunction;

        $data = ['addresses' => [['city' => 'Kinshasa'], ['city' => 'Paris']]];

        $result = $function->execute($data, ['addresses', 'city', '/^Lon.*/']);

        $this->assertFalse($result);
    }

    public function test_matches_function_with_case_insensitive_regex(): void
    {
        $function = new MatchesFunction;

        $data = ['tags' => ['php', 'JavaScript', 'css']];

        $result = $function->execute($data, ['tags', '/^java.*/i']);

        $this->assertTrue($result);
    }

    public function test_matches_function_with_empty_array(): void
    {
        $function = new MatchesFunction;

        $data = ['tags' => []];

        $result = $function->execute($data, ['tags', '/^php.*/']);

        $this->assertFalse($result);
    }

    public function test_matches_function_with_non_array_value(): void
    {
        $function = new MatchesFunction;

        $data = ['tags' => 'not an array'];

        $result = $function->execute($data, ['tags', '/^php.*/']);

        $this->assertFalse($result);
    }

    public function test_matches_function_with_regex_special_characters(): void
    {
        $function = new MatchesFunction;

        $data = ['codes' => ['ABC-123', 'DEF-456', 'GHI-789']];

        $result = $function->execute($data, ['codes', '/^[A-Z]{3}-\d{3}$/']);

        $this->assertTrue($result);
    }

    public function test_matches_function_with_regex_boundaries(): void
    {
        $function = new MatchesFunction;

        $data = ['names' => ['John Doe', 'Jane Smith', 'Bob Johnson']];

        $result = $function->execute($data, ['names', '/^John.*/']);

        $this->assertTrue($result);

        $result = $function->execute($data, ['names', '/.*Smith$/']);

        $this->assertTrue($result);
    }

    public function test_matches_function_with_nested_path_and_regex(): void
    {
        $function = new MatchesFunction;

        $data = [
            'users' => [
                ['profile' => ['username' => 'john_doe']],
                ['profile' => ['username' => 'jane_smith']],
            ],
        ];

        $result = $function->execute($data, ['users.profile', 'username', '/^john.*/']);

        $this->assertTrue($result);
    }

    public function test_matches_function_with_quoted_pattern(): void
    {
        $function = new MatchesFunction;

        $data = ['tags' => ['php', 'javascript', 'css']];

        $result = $function->execute($data, ['tags', '"/^ja.*/"']);

        $this->assertTrue($result);
    }

    // ==================== DISTANCE FUNCTION TESTS ====================

    public function test_distance_function_with_coordinates_vo(): void
    {
        $function = new DistanceFunction;

        $coords = new CoordinatesVO(
            FloatVO::from(48.8566),
            FloatVO::from(2.3522)
        );

        $data = ['coordinates' => $coords];

        $result = $function->execute($data, ['coordinates', 45.7640, 4.8357]);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
        $this->assertGreaterThan(391000, $result);
        $this->assertLessThan(392000, $result);
    }

    public function test_distance_function_with_array_coordinates(): void
    {
        $function = new DistanceFunction;

        $data = [
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ];

        $result = $function->execute($data, ['coordinates', 45.7640, 4.8357]);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
        $this->assertGreaterThan(391000, $result);
        $this->assertLessThan(392000, $result);
    }

    public function test_distance_function_with_km_unit(): void
    {
        $function = new DistanceFunction;

        $data = [
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ];

        $result = $function->execute($data, ['coordinates', 45.7640, 4.8357, 'km']);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
        $this->assertGreaterThan(391, $result);
        $this->assertLessThan(392, $result);
    }

    public function test_distance_function_with_m_unit(): void
    {
        $function = new DistanceFunction;

        $data = [
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ];

        $result = $function->execute($data, ['coordinates', 45.7640, 4.8357, 'm']);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
        $this->assertGreaterThan(391000, $result);
        $this->assertLessThan(392000, $result);
    }

    public function test_distance_function_with_same_coordinates(): void
    {
        $function = new DistanceFunction;

        $data = [
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ];

        $result = $function->execute($data, ['coordinates', 48.8566, 2.3522]);

        $this->assertIsFloat($result);
        $this->assertEquals(0.0, $result);
    }

    public function test_distance_function_with_nested_path(): void
    {
        $function = new DistanceFunction;

        $data = [
            'user' => [
                'location' => [
                    'coordinates' => [
                        'latitude' => 48.8566,
                        'longitude' => 2.3522,
                    ],
                ],
            ],
        ];

        $result = $function->execute($data, ['user.location.coordinates', 45.7640, 4.8357]);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(391000, $result);
        $this->assertLessThan(392000, $result);
    }

    public function test_distance_function_with_invalid_coordinates(): void
    {
        $function = new DistanceFunction;

        $data = ['coordinates' => 'not a coordinate'];

        $result = $function->execute($data, ['coordinates', 45.7640, 4.8357]);

        $this->assertSame(0.0, $result);
    }

    public function test_distance_function_with_missing_coordinates(): void
    {
        $function = new DistanceFunction;

        $data = ['something' => 'else'];

        $result = $function->execute($data, ['coordinates', 45.7640, 4.8357]);

        $this->assertSame(0.0, $result);
    }

    public function test_distance_function_with_invalid_unit(): void
    {
        $function = new DistanceFunction;

        $data = [
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ];

        $result = $function->execute($data, ['coordinates', 45.7640, 4.8357, 'invalid']);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(391000, $result);
        $this->assertLessThan(392000, $result);
    }

    public function test_distance_function_validate_args(): void
    {
        $function = new DistanceFunction;

        $this->assertTrue($function->validateArgs(['coordinates', 48.8566, 2.3522]));
        $this->assertTrue($function->validateArgs(['coordinates', 48.8566, 2.3522, 'km']));
        $this->assertTrue($function->validateArgs(['coordinates', 48.8566, 2.3522, 'm']));

        $this->assertFalse($function->validateArgs(['coordinates', 48.8566]));
        $this->assertFalse($function->validateArgs(['coordinates']));
        $this->assertFalse($function->validateArgs([]));
        $this->assertFalse($function->validateArgs(['coordinates', 48.8566, 2.3522, 'invalid']));
    }

    public function test_distance_function_get_metadata(): void
    {
        $function = new DistanceFunction;

        $this->assertSame('DISTANCE', $function->getName());
        $this->assertSame('float', $function->getReturnType());
        $this->assertSame(0.0, $function->getDefaultValue());
        $this->assertFalse($function->returnsBoolean());
        $this->assertSame(3, $function->getMinArgs());
        $this->assertSame(4, $function->getMaxArgs());
    }

    public function test_distance_function_with_multiple_pharmacies(): void
    {
        $function = new DistanceFunction;

        $data = [
            'pharmacies' => [
                [
                    'name' => 'Paris Pharmacy',
                    'coordinates' => [
                        'latitude' => 48.8566,
                        'longitude' => 2.3522,
                    ],
                ],
                [
                    'name' => 'Lyon Pharmacy',
                    'coordinates' => [
                        'latitude' => 45.7640,
                        'longitude' => 4.8357,
                    ],
                ],
                [
                    'name' => 'Marseille Pharmacy',
                    'coordinates' => [
                        'latitude' => 43.2965,
                        'longitude' => 5.3698,
                    ],
                ],
            ],
        ];

        $distances = [];
        foreach ($data['pharmacies'] as $pharmacy) {
            $distance = $function->execute(
                ['coords' => $pharmacy['coordinates']],
                ['coords', 48.8566, 2.3522, 'km']
            );
            $distances[$pharmacy['name']] = $distance;
        }

        $this->assertGreaterThan(390, $distances['Lyon Pharmacy']);
        $this->assertGreaterThan(660, $distances['Marseille Pharmacy']);
    }

    public function test_distance_function_in_collection_context(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'Paris Pharmacy',
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Lyon Pharmacy',
            'coordinates' => [
                'latitude' => 45.7640,
                'longitude' => 4.8357,
            ],
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Marseille Pharmacy',
            'coordinates' => [
                'latitude' => 43.2965,
                'longitude' => 5.3698,
            ],
        ]));

        $result = $collection->whereAggregate(
            '{DISTANCE(coordinates, 48.8566, 2.3522, km) < 500}'
        );

        $this->assertCount(2, $result);
        $this->assertEquals('Paris Pharmacy', $result->first()->get('name'));
        $this->assertEquals('Lyon Pharmacy', $result->last()->get('name'));
    }

    public function test_distance_function_with_group_and_condition(): void
    {
        $collection = new ClusterVOCollection;

        $collection->add(new ClusterVO([
            'name' => 'Paris Pharmacy',
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
            'status' => 'active',
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Lyon Pharmacy',
            'coordinates' => [
                'latitude' => 45.7640,
                'longitude' => 4.8357,
            ],
            'status' => 'inactive',
        ]));
        $collection->add(new ClusterVO([
            'name' => 'Marseille Pharmacy',
            'coordinates' => [
                'latitude' => 43.2965,
                'longitude' => 5.3698,
            ],
            'status' => 'active',
        ]));

        // ✅ Utiliser whereAggregate avec le GROUP et la condition status=active
        // On utilise GROUP pour grouper la condition de distance
        // Et on ajoute status=active comme condition simple
        $result = $collection
            ->whereAggregate('{DISTANCE(coordinates, 48.8566, 2.3522, km) < 500}')
            ->where('status', 'active');

        // Ou bien on utilise whereQuery qui gère les deux
        // $result = $collection->whereQuery('{DISTANCE(coordinates, 48.8566, 2.3522, km) < 500} & status=active');

        $this->assertCount(1, $result);
        $this->assertEquals('Paris Pharmacy', $result->first()->get('name'));
    }

    public function test_distance_function_with_different_coordinates_format(): void
    {
        $function = new DistanceFunction;

        $data = [
            'coordinates' => [
                'lat' => 48.8566,
                'lng' => 2.3522,
            ],
        ];

        $result = $function->execute($data, ['coordinates', 45.7640, 4.8357]);

        $this->assertSame(0.0, $result);
    }

    public function test_distance_function_with_less_than_min_args(): void
    {
        $function = new DistanceFunction;

        $data = [
            'coordinates' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ];

        $result = $function->execute($data, ['coordinates']);

        $this->assertSame(0.0, $result);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_count_function_validate_args(): void
    {
        $function = new CountFunction;

        $this->assertTrue($function->validateArgs(['path']));
        $this->assertFalse($function->validateArgs(['path', 'extra']));
        $this->assertFalse($function->validateArgs([]));
    }

    public function test_has_function_validate_args(): void
    {
        $function = new HasFunction;

        $this->assertTrue($function->validateArgs(['path', 'key']));
        $this->assertTrue($function->validateArgs(['path', 'key', 'value']));
        $this->assertFalse($function->validateArgs(['path']));
        $this->assertFalse($function->validateArgs([]));
    }

    public function test_all_function_validate_args(): void
    {
        $function = new AllFunction;

        $this->assertTrue($function->validateArgs(['path', 'key', 'value']));
        $this->assertFalse($function->validateArgs(['path', 'key']));
        $this->assertFalse($function->validateArgs([]));
    }

    public function test_matches_function_validate_args(): void
    {
        $function = new MatchesFunction;

        $this->assertTrue($function->validateArgs(['path', 'pattern']));
        $this->assertTrue($function->validateArgs(['path', 'key', 'pattern']));
        $this->assertFalse($function->validateArgs(['path']));
        $this->assertFalse($function->validateArgs([]));
        $this->assertFalse($function->validateArgs(['path', 'key', 'pattern', 'extra']));
    }

    public function test_distance_function_validate_args_with_invalid_unit(): void
    {
        $function = new DistanceFunction;

        $this->assertFalse($function->validateArgs(['coordinates', 48.8566, 2.3522, 'invalid_unit']));
    }

    // ==================== ABSTRACT FUNCTION TESTS ====================

    public function test_function_returns_correct_metadata(): void
    {
        $functions = [
            new CountFunction,
            new SumFunction,
            new AvgFunction,
            new MinFunction,
            new MaxFunction,
            new LengthFunction,
            new ExistsFunction,
            new HasFunction,
            new AllFunction,
            new IsEmptyFunction,
            new MatchesFunction,
            new DistanceFunction,
        ];

        foreach ($functions as $function) {
            $this->assertIsString($function->getName());
            $this->assertIsString($function->getReturnType());
            $this->assertIsBool($function->returnsBoolean());
            $this->assertIsInt($function->getMinArgs());
            $this->assertIsInt($function->getMaxArgs());
        }
    }
}
