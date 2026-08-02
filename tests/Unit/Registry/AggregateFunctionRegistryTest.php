<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Registry;

use AndyDefer\LaravelCluster\Contracts\AggregateFunctionInterface;
use AndyDefer\LaravelCluster\Functions\CountFunction;
use AndyDefer\LaravelCluster\Functions\ExistsFunction;
use AndyDefer\LaravelCluster\Registry\AggregateFunctionRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AggregateFunctionRegistryTest extends TestCase
{
    private AggregateFunctionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new AggregateFunctionRegistry;
    }

    // ============================================================
    // TESTS D'ENREGISTREMENT
    // ============================================================

    public function test_register_default_functions(): void
    {
        $expectedFunctions = [
            'COUNT',
            'SUM',
            'AVG',
            'MIN',
            'MAX',
            'LENGTH',
            'EXISTS',
            'HAS',
            'ALL',
            'IS_EMPTY',
            'MATCHES',
        ];

        $names = $this->registry->getNames();
        sort($names);
        sort($expectedFunctions);

        $this->assertEquals($expectedFunctions, $names);
    }

    public function test_register_new_function(): void
    {
        $stub = $this->createStub(AggregateFunctionInterface::class);
        $stub->method('getName')->willReturn('CUSTOM');

        $this->registry->register($stub);

        $this->assertTrue($this->registry->has('CUSTOM'));
        $this->assertSame($stub, $this->registry->get('CUSTOM'));
    }

    public function test_register_duplicate_function_throws_exception(): void
    {
        $stub1 = $this->createStub(AggregateFunctionInterface::class);
        $stub1->method('getName')->willReturn('CUSTOM');

        $stub2 = $this->createStub(AggregateFunctionInterface::class);
        $stub2->method('getName')->willReturn('CUSTOM');

        $this->registry->register($stub1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function "CUSTOM" is already registered. Cannot register duplicate.');

        $this->registry->register($stub2);
    }

    public function test_register_duplicate_with_different_case_throws_exception(): void
    {
        $stub1 = $this->createStub(AggregateFunctionInterface::class);
        $stub1->method('getName')->willReturn('CUSTOM');

        $stub2 = $this->createStub(AggregateFunctionInterface::class);
        $stub2->method('getName')->willReturn('CUSTOM');

        $this->registry->register($stub1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function "CUSTOM" is already registered. Cannot register duplicate.');

        $this->registry->register($stub2);
    }

    public function test_register_duplicate_default_function_throws_exception(): void
    {
        $stub = $this->createStub(AggregateFunctionInterface::class);
        $stub->method('getName')->willReturn('COUNT');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function "COUNT" is already registered. Cannot register duplicate.');

        $this->registry->register($stub);
    }

    // ============================================================
    // TESTS DE RECHERCHE
    // ============================================================

    public function test_has_returns_true_for_registered_function(): void
    {
        $this->assertTrue($this->registry->has('COUNT'));
        $this->assertTrue($this->registry->has('count'));
        $this->assertTrue($this->registry->has('SUM'));
        $this->assertTrue($this->registry->has('AVG'));
        $this->assertTrue($this->registry->has('EXISTS'));
        $this->assertTrue($this->registry->has('HAS'));
    }

    public function test_has_returns_false_for_unregistered_function(): void
    {
        $this->assertFalse($this->registry->has('UNKNOWN'));
        $this->assertFalse($this->registry->has('CUSTOM'));
    }

    public function test_get_returns_function_instance(): void
    {
        $countFunction = $this->registry->get('COUNT');
        $this->assertInstanceOf(CountFunction::class, $countFunction);

        $existsFunction = $this->registry->get('EXISTS');
        $this->assertInstanceOf(ExistsFunction::class, $existsFunction);
    }

    public function test_get_returns_null_for_unregistered_function(): void
    {
        $this->assertNull($this->registry->get('UNKNOWN'));
        $this->assertNull($this->registry->get('CUSTOM'));
    }

    // ============================================================
    // TESTS D'EXÉCUTION
    // ============================================================

    public function test_execute_returns_result_for_registered_function(): void
    {
        $data = ['addresses' => ['a', 'b', 'c']];
        $result = $this->registry->execute('COUNT', $data, ['addresses']);
        $this->assertEquals(3, $result);

        $data = ['prices' => [10, 20, 30]];
        $result = $this->registry->execute('SUM', $data, ['prices']);
        $this->assertEquals(60.0, $result);

        $data = ['tags' => ['php', 'js', 'docker']];
        $result = $this->registry->execute('HAS', $data, ['tags', 'php']);
        $this->assertTrue($result);

        $result = $this->registry->execute('HAS', $data, ['tags', 'python']);
        $this->assertFalse($result);
    }

    public function test_execute_throws_exception_for_unregistered_function(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function "UNKNOWN" not registered');

        $this->registry->execute('UNKNOWN', [], []);
    }

    // ============================================================
    // TESTS DE BOOLEAN FUNCTIONS
    // ============================================================

    public function test_get_boolean_functions(): void
    {
        $booleanFunctions = $this->registry->getBooleanFunctions();
        $names = array_keys($booleanFunctions);

        $expected = ['EXISTS', 'HAS', 'ALL', 'IS_EMPTY', 'MATCHES'];
        sort($names);
        sort($expected);

        $this->assertEquals($expected, $names);

        foreach ($booleanFunctions as $function) {
            $this->assertTrue($function->returnsBoolean());
        }
    }

    public function test_get_numeric_functions(): void
    {
        $numericFunctions = $this->registry->getNumericFunctions();
        $names = array_keys($numericFunctions);

        $expected = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'LENGTH'];
        sort($names);
        sort($expected);

        $this->assertEquals($expected, $names);

        foreach ($numericFunctions as $function) {
            $this->assertFalse($function->returnsBoolean());
        }
    }

    // ============================================================
    // TESTS DES MÉTADONNÉES
    // ============================================================

    public function test_get_default_value(): void
    {
        $this->assertEquals(0, $this->registry->getDefaultValue('COUNT'));
        $this->assertFalse($this->registry->getDefaultValue('EXISTS'));
        $this->assertEquals(0, $this->registry->getDefaultValue('UNKNOWN'));
    }

    // ============================================================
    // TESTS DE LISTE
    // ============================================================

    public function test_all_returns_all_registered_functions(): void
    {
        $all = $this->registry->all();
        $this->assertIsArray($all);
        $this->assertCount(11, $all);
        $this->assertArrayHasKey('COUNT', $all);
        $this->assertArrayHasKey('EXISTS', $all);
        $this->assertInstanceOf(CountFunction::class, $all['COUNT']);
        $this->assertInstanceOf(ExistsFunction::class, $all['EXISTS']);
    }

    public function test_get_names_returns_all_function_names(): void
    {
        $names = $this->registry->getNames();
        $this->assertIsArray($names);
        $this->assertCount(11, $names);
        $this->assertContains('COUNT', $names);
        $this->assertContains('EXISTS', $names);
        $this->assertContains('HAS', $names);
        $this->assertContains('MATCHES', $names);
    }

    // ============================================================
    // TESTS DE CASSE
    // ============================================================

    public function test_function_names_are_case_insensitive(): void
    {
        $this->assertTrue($this->registry->has('count'));
        $this->assertTrue($this->registry->has('Count'));
        $this->assertTrue($this->registry->has('COUNT'));

        $this->assertInstanceOf(CountFunction::class, $this->registry->get('count'));
        $this->assertInstanceOf(CountFunction::class, $this->registry->get('Count'));
        $this->assertInstanceOf(CountFunction::class, $this->registry->get('COUNT'));
    }

    public function test_register_with_different_case_normalizes_to_uppercase(): void
    {
        $stub = $this->createStub(AggregateFunctionInterface::class);
        $stub->method('getName')->willReturn('CUSTOM');

        $this->registry->register($stub);

        $this->assertTrue($this->registry->has('CUSTOM'));
        $this->assertTrue($this->registry->has('custom'));
        $this->assertTrue($this->registry->has('Custom'));
    }

    // ============================================================
    // TESTS D'EXÉCUTION AVEC DONNÉES COMPLEXES
    // ============================================================

    public function test_execute_has_with_nested_data(): void
    {
        $data = [
            'profile' => [
                'skills' => ['php', 'laravel', 'vuejs'],
            ],
        ];

        $result = $this->registry->execute('HAS', $data, ['profile.skills', 'php']);
        $this->assertTrue($result);

        $result = $this->registry->execute('HAS', $data, ['profile.skills', 'python']);
        $this->assertFalse($result);
    }

    public function test_execute_all_with_multiple_values(): void
    {
        $data = [
            'items' => [
                ['status' => 'active'],
                ['status' => 'active'],
                ['status' => 'active'],
            ],
        ];

        $result = $this->registry->execute('ALL', $data, ['items', 'status', 'active']);
        $this->assertTrue($result);

        $data = [
            'items' => [
                ['status' => 'active'],
                ['status' => 'inactive'],
                ['status' => 'active'],
            ],
        ];

        $result = $this->registry->execute('ALL', $data, ['items', 'status', 'active']);
        $this->assertFalse($result);
    }

    public function test_execute_matches_with_regex(): void
    {
        $data = [
            'name' => 'John Doe',
        ];

        $result = $this->registry->execute('MATCHES', $data, ['name', '/^John/']);
        $this->assertTrue($result);

        $result = $this->registry->execute('MATCHES', $data, ['name', '/^Jane/']);
        $this->assertFalse($result);

        $data = [
            'tags' => ['php', 'laravel', 'vuejs'],
        ];

        $result = $this->registry->execute('MATCHES', $data, ['tags', '/^l/']);
        $this->assertTrue($result);

        $result = $this->registry->execute('MATCHES', $data, ['tags', '/^x/']);
        $this->assertFalse($result);

        $data = [
            'items' => [
                ['name' => 'John Doe'],
                ['name' => 'Jane Smith'],
                ['name' => 'Bob Johnson'],
            ],
        ];

        $result = $this->registry->execute('MATCHES', $data, ['items', 'name', '/^John/']);
        $this->assertTrue($result);

        $result = $this->registry->execute('MATCHES', $data, ['items', 'name', '/^Alice/']);
        $this->assertFalse($result);

        $data = [
            'email' => 'john.doe@example.com',
        ];

        $result = $this->registry->execute('MATCHES', $data, ['email', '/.*@example\.com$/']);
        $this->assertTrue($result);

        $result = $this->registry->execute('MATCHES', $data, ['email', '/.*@gmail\.com$/']);
        $this->assertFalse($result);
    }

    public function test_execute_is_empty(): void
    {
        $data = [
            'empty_array' => [],
            'non_empty_array' => ['a', 'b'],
            'empty_string' => '',
            'non_empty_string' => 'hello',
        ];

        $result = $this->registry->execute('IS_EMPTY', $data, ['empty_array']);
        $this->assertTrue($result);

        $result = $this->registry->execute('IS_EMPTY', $data, ['non_empty_array']);
        $this->assertFalse($result);

        $result = $this->registry->execute('IS_EMPTY', $data, ['empty_string']);
        $this->assertTrue($result);

        $result = $this->registry->execute('IS_EMPTY', $data, ['non_empty_string']);
        $this->assertFalse($result);
    }

    public function test_execute_exists(): void
    {
        $data = [
            'present' => 'value',
            'null_value' => null,
        ];

        $result = $this->registry->execute('EXISTS', $data, ['present']);
        $this->assertTrue($result);

        $result = $this->registry->execute('EXISTS', $data, ['null_value']);
        $this->assertFalse($result);

        $result = $this->registry->execute('EXISTS', $data, ['missing']);
        $this->assertFalse($result);
    }

    // ============================================================
    // TESTS DE VALIDATION DES NOMS DE FONCTIONS
    // ============================================================

    public function test_register_with_valid_function_name(): void
    {
        $stub = $this->createStub(AggregateFunctionInterface::class);
        $stub->method('getName')->willReturn('VALID_NAME');

        $this->registry->register($stub);

        $this->assertTrue($this->registry->has('VALID_NAME'));
    }

    public function test_register_with_valid_function_name_with_numbers(): void
    {
        $stub = $this->createStub(AggregateFunctionInterface::class);
        $stub->method('getName')->willReturn('FUNCTION_123');

        $this->registry->register($stub);

        $this->assertTrue($this->registry->has('FUNCTION_123'));
    }

    public function test_register_with_invalid_function_name_lowercase_throws_exception(): void
    {
        $stub = $this->createStub(AggregateFunctionInterface::class);
        $stub->method('getName')->willReturn('invalid_name');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid function name "invalid_name". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.');

        $this->registry->register($stub);
    }

    public function test_register_with_invalid_function_name_starts_with_number_throws_exception(): void
    {
        $stub = $this->createStub(AggregateFunctionInterface::class);
        $stub->method('getName')->willReturn('1FUNCTION');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid function name "1FUNCTION". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.');

        $this->registry->register($stub);
    }

    public function test_register_with_invalid_function_name_contains_special_characters_throws_exception(): void
    {
        $stub = $this->createStub(AggregateFunctionInterface::class);
        $stub->method('getName')->willReturn('FUNCTION-NAME');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid function name "FUNCTION-NAME". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.');

        $this->registry->register($stub);
    }

    public function test_register_with_invalid_function_name_contains_space_throws_exception(): void
    {
        $stub = $this->createStub(AggregateFunctionInterface::class);
        $stub->method('getName')->willReturn('FUNCTION NAME');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid function name "FUNCTION NAME". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.');

        $this->registry->register($stub);
    }

    public function test_register_with_invalid_function_name_empty_throws_exception(): void
    {
        $stub = $this->createStub(AggregateFunctionInterface::class);
        $stub->method('getName')->willReturn('');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid function name "". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.');

        $this->registry->register($stub);
    }
}
