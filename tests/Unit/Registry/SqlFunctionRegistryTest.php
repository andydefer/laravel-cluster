<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\Registry;

use AndyDefer\LaravelCluster\Contracts\SqlFunctionInterface;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\Registry\SqlFunctionRegistry;
use AndyDefer\LaravelCluster\SqlFunctions\ContainsFunction;
use AndyDefer\LaravelCluster\SqlFunctions\CountFunction;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SqlFunctionRegistryTest extends TestCase
{
    private SqlFunctionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new SqlFunctionRegistry;
    }

    // ============================================================
    // TESTS D'ENREGISTREMENT
    // ============================================================

    public function test_register_default_functions(): void
    {
        $expectedFunctions = [
            'AVG',
            'CONTAINS',
            'COUNT',
            'DISTANCE',
            'JSON_LENGTH',
            'LENGTH',
            'MAX',
            'MIN',
            'REGEXP',
            'SUM',
            'EXTRACT_KEY',
        ];

        $names = $this->registry->getNames();
        sort($names);
        sort($expectedFunctions);

        $this->assertEquals($expectedFunctions, $names);
    }

    public function test_register_new_function(): void
    {
        $stub = $this->createStub(SqlFunctionInterface::class);
        $stub->method('getName')->willReturn('CUSTOM');

        $this->registry->register($stub);

        $this->assertTrue($this->registry->has('CUSTOM'));
        $this->assertSame($stub, $this->registry->get('CUSTOM'));
    }

    public function test_register_duplicate_function_throws_exception(): void
    {
        $stub1 = $this->createStub(SqlFunctionInterface::class);
        $stub1->method('getName')->willReturn('CUSTOM');

        $stub2 = $this->createStub(SqlFunctionInterface::class);
        $stub2->method('getName')->willReturn('CUSTOM');

        $this->registry->register($stub1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function "CUSTOM" is already registered. Cannot register duplicate.');

        $this->registry->register($stub2);
    }

    public function test_register_duplicate_with_different_case_throws_exception(): void
    {
        $stub1 = $this->createStub(SqlFunctionInterface::class);
        $stub1->method('getName')->willReturn('CUSTOM');

        $stub2 = $this->createStub(SqlFunctionInterface::class);
        $stub2->method('getName')->willReturn('CUSTOM');

        $this->registry->register($stub1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function "CUSTOM" is already registered. Cannot register duplicate.');

        $this->registry->register($stub2);
    }

    public function test_register_duplicate_default_function_throws_exception(): void
    {
        $stub = $this->createStub(SqlFunctionInterface::class);
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
        $this->assertTrue($this->registry->has('CONTAINS'));
        $this->assertTrue($this->registry->has('DISTANCE'));
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

        $containsFunction = $this->registry->get('CONTAINS');
        $this->assertInstanceOf(ContainsFunction::class, $containsFunction);
    }

    public function test_get_returns_null_for_unregistered_function(): void
    {
        $this->assertNull($this->registry->get('UNKNOWN'));
        $this->assertNull($this->registry->get('CUSTOM'));
    }

    // ============================================================
    // TESTS DE GÉNÉRATION SQL
    // ============================================================

    public function test_to_sql_returns_sql_for_registered_function(): void
    {
        $sql = $this->registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::MYSQL);
        $this->assertIsString($sql);
        $this->assertStringContainsString('JSON_LENGTH', $sql);

        $sql = $this->registry->toSql('COUNT', 'clusters', 'addresses', DatabaseDriver::SQLITE);
        $this->assertIsString($sql);
        $this->assertStringContainsString('json_array_length', $sql);
    }

    public function test_to_sql_returns_null_for_unregistered_function(): void
    {
        $sql = $this->registry->toSql('UNKNOWN', 'clusters', 'addresses', DatabaseDriver::MYSQL);
        $this->assertNull($sql);
    }

    public function test_to_sql_with_contains_function(): void
    {
        $sql = $this->registry->toSql('CONTAINS', 'clusters', 'languages', DatabaseDriver::SQLITE, ['languages', 'fr']);
        $this->assertIsString($sql);
        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('json_each', $sql);

        $sql = $this->registry->toSql('CONTAINS', 'clusters', 'languages', DatabaseDriver::MYSQL, ['languages', 'fr']);
        $this->assertIsString($sql);
        $this->assertStringContainsString('JSON_SEARCH', $sql);
    }

    // ============================================================
    // TESTS D'EXÉCUTION
    // ============================================================

    public function test_execute_returns_result_for_registered_function(): void
    {
        $result = $this->registry->execute('COUNT', ['a', 'b', 'c']);
        $this->assertEquals(3, $result);

        $result = $this->registry->execute('SUM', [10, 20, 30]);
        $this->assertEquals(60.0, $result);

        $result = $this->registry->execute('CONTAINS', ['fr', 'en'], ['languages', 'fr']);
        $this->assertTrue($result);

        $result = $this->registry->execute('CONTAINS', ['fr', 'en'], ['languages', 'de']);
        $this->assertFalse($result);
    }

    public function test_execute_returns_original_value_for_unregistered_function(): void
    {
        $value = ['a', 'b', 'c'];
        $result = $this->registry->execute('UNKNOWN', $value);
        $this->assertSame($value, $result);
    }

    // ============================================================
    // TESTS DE VALIDATION
    // ============================================================

    public function test_validate_args_returns_false_for_invalid_arguments(): void
    {
        $this->assertFalse($this->registry->validateArgs('COUNT', []));
        $this->assertFalse($this->registry->validateArgs('COUNT', ['addresses', 'extra']));
        $this->assertFalse($this->registry->validateArgs('CONTAINS', ['languages']));
        $this->assertFalse($this->registry->validateArgs('CONTAINS', ['', 'fr']));
        $this->assertFalse($this->registry->validateArgs('CONTAINS', ['languages', '']));
        $this->assertFalse($this->registry->validateArgs('CONTAINS', ['languages', 'fr', '']));
        $this->assertFalse($this->registry->validateArgs('CONTAINS', ['languages', 'fr', 123]));
    }

    public function test_validate_args_returns_true_for_valid_arguments(): void
    {
        $this->assertTrue($this->registry->validateArgs('COUNT', ['addresses']));
        $this->assertTrue($this->registry->validateArgs('CONTAINS', ['languages', 'fr']));
        $this->assertTrue($this->registry->validateArgs('CONTAINS', ['languages', 'fr', 'en']));
        $this->assertTrue($this->registry->validateArgs('CONTAINS', ['languages', 'fr', 'en', 'es']));
    }

    public function test_validate_args_returns_false_for_unregistered_function(): void
    {
        $this->assertFalse($this->registry->validateArgs('UNKNOWN', ['args']));
    }

    // ============================================================
    // TESTS DES MÉTADONNÉES
    // ============================================================

    public function test_get_min_args(): void
    {
        $this->assertEquals(1, $this->registry->getMinArgs('COUNT'));
        $this->assertEquals(2, $this->registry->getMinArgs('CONTAINS'));
        $this->assertNull($this->registry->getMinArgs('UNKNOWN'));
    }

    public function test_get_max_args(): void
    {
        $this->assertEquals(PHP_INT_MAX, $this->registry->getMaxArgs('COUNT'));
        $this->assertNull($this->registry->getMaxArgs('UNKNOWN'));
    }

    public function test_get_return_type(): void
    {
        $this->assertEquals('int', $this->registry->getReturnType('COUNT'));
        $this->assertEquals('float', $this->registry->getReturnType('SUM'));
        $this->assertEquals('bool', $this->registry->getReturnType('CONTAINS'));
        $this->assertNull($this->registry->getReturnType('UNKNOWN'));
    }

    public function test_get_default_value(): void
    {
        $this->assertEquals(0, $this->registry->getDefaultValue('COUNT'));
        $this->assertFalse($this->registry->getDefaultValue('CONTAINS'));
        $this->assertNull($this->registry->getDefaultValue('UNKNOWN'));
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
        $this->assertArrayHasKey('CONTAINS', $all);
        $this->assertArrayHasKey('DISTANCE', $all);
        $this->assertInstanceOf(CountFunction::class, $all['COUNT']);
        $this->assertInstanceOf(ContainsFunction::class, $all['CONTAINS']);
    }

    public function test_get_names_returns_all_function_names(): void
    {
        $names = $this->registry->getNames();
        $this->assertIsArray($names);
        $this->assertCount(11, $names);
        $this->assertContains('COUNT', $names);
        $this->assertContains('CONTAINS', $names);
        $this->assertContains('DISTANCE', $names);
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
        $stub = $this->createStub(SqlFunctionInterface::class);
        $stub->method('getName')->willReturn('CUSTOM');

        $this->registry->register($stub);

        $this->assertTrue($this->registry->has('CUSTOM'));
        $this->assertTrue($this->registry->has('custom'));
        $this->assertTrue($this->registry->has('Custom'));
    }

    // ============================================================
    // TESTS DE VALIDATION DES NOMS DE FONCTIONS
    // ============================================================

    public function test_register_with_valid_function_name(): void
    {
        $stub = $this->createStub(SqlFunctionInterface::class);
        $stub->method('getName')->willReturn('VALID_NAME');

        $this->registry->register($stub);

        $this->assertTrue($this->registry->has('VALID_NAME'));
    }

    public function test_register_with_valid_function_name_with_numbers(): void
    {
        $stub = $this->createStub(SqlFunctionInterface::class);
        $stub->method('getName')->willReturn('FUNCTION_123');

        $this->registry->register($stub);

        $this->assertTrue($this->registry->has('FUNCTION_123'));
    }

    public function test_register_with_invalid_function_name_lowercase_throws_exception(): void
    {
        $stub = $this->createStub(SqlFunctionInterface::class);
        $stub->method('getName')->willReturn('invalid_name');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid function name "invalid_name". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.');

        $this->registry->register($stub);
    }

    public function test_register_with_invalid_function_name_starts_with_number_throws_exception(): void
    {
        $stub = $this->createStub(SqlFunctionInterface::class);
        $stub->method('getName')->willReturn('1FUNCTION');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid function name "1FUNCTION". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.');

        $this->registry->register($stub);
    }

    public function test_register_with_invalid_function_name_contains_special_characters_throws_exception(): void
    {
        $stub = $this->createStub(SqlFunctionInterface::class);
        $stub->method('getName')->willReturn('FUNCTION-NAME');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid function name "FUNCTION-NAME". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.');

        $this->registry->register($stub);
    }

    public function test_register_with_invalid_function_name_contains_space_throws_exception(): void
    {
        $stub = $this->createStub(SqlFunctionInterface::class);
        $stub->method('getName')->willReturn('FUNCTION NAME');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid function name "FUNCTION NAME". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.');

        $this->registry->register($stub);
    }

    public function test_register_with_invalid_function_name_empty_throws_exception(): void
    {
        $stub = $this->createStub(SqlFunctionInterface::class);
        $stub->method('getName')->willReturn('');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid function name "". Function names must be in SCREAMING_SNAKE_CASE format: start with a letter, contain only uppercase letters, numbers, and underscores.');

        $this->registry->register($stub);
    }
}
