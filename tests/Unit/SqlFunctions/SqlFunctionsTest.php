<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests\Unit\SqlFunctions;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelCluster\SqlFunctions\AvgFunction;
use AndyDefer\LaravelCluster\SqlFunctions\CountFunction;
use AndyDefer\LaravelCluster\SqlFunctions\JsonLengthFunction;
use AndyDefer\LaravelCluster\SqlFunctions\LengthFunction;
use AndyDefer\LaravelCluster\SqlFunctions\MaxFunction;
use AndyDefer\LaravelCluster\SqlFunctions\MinFunction;
use AndyDefer\LaravelCluster\SqlFunctions\SumFunction;
use PHPUnit\Framework\TestCase;

final class SqlFunctionsTest extends TestCase
{
    private const COLUMN = 'clusters';

    private const PATH = 'addresses';

    // ==================== EXECUTE TESTS ====================

    public function test_count_function_execute_with_array(): void
    {
        $function = new CountFunction;

        $result = $function->execute(['a', 'b', 'c']);

        $this->assertSame(3, $result);
    }

    public function test_count_function_execute_with_string(): void
    {
        $function = new CountFunction;

        $result = $function->execute('hello');

        $this->assertSame(5, $result);
    }

    public function test_count_function_execute_with_empty_array(): void
    {
        $function = new CountFunction;

        $result = $function->execute([]);

        $this->assertSame(0, $result);
    }

    public function test_count_function_execute_with_null(): void
    {
        $function = new CountFunction;

        $result = $function->execute(null);

        $this->assertSame(0, $result);
    }

    public function test_count_function_execute_with_nested_array(): void
    {
        $function = new CountFunction;

        $result = $function->execute([['a', 'b'], ['c', 'd']]);

        $this->assertSame(2, $result);
    }

    // ==================== SUM FUNCTION TESTS ====================

    public function test_sum_function_execute_with_integers(): void
    {
        $function = new SumFunction;

        $result = $function->execute([10, 20, 30]);

        $this->assertSame(60.0, $result);
    }

    public function test_sum_function_execute_with_floats(): void
    {
        $function = new SumFunction;

        $result = $function->execute([1.5, 2.5, 3.0]);

        $this->assertSame(7.0, $result);
    }

    public function test_sum_function_execute_with_mixed_values(): void
    {
        $function = new SumFunction;

        $result = $function->execute([10, '20', 30, 'not a number', 40]);

        $this->assertSame(100.0, $result);
    }

    public function test_sum_function_execute_with_empty_array(): void
    {
        $function = new SumFunction;

        $result = $function->execute([]);

        $this->assertSame(0.0, $result);
    }

    public function test_sum_function_execute_with_nested_array(): void
    {
        $function = new SumFunction;

        $result = $function->execute([[10, 20], [30, 40]]);

        $this->assertSame(100.0, $result);
    }

    // ==================== AVG FUNCTION TESTS ====================

    public function test_avg_function_execute_with_integers(): void
    {
        $function = new AvgFunction;

        $result = $function->execute([10, 20, 30]);

        $this->assertSame(20.0, $result);
    }

    public function test_avg_function_execute_with_floats(): void
    {
        $function = new AvgFunction;

        $result = $function->execute([1.5, 2.5, 3.0]);

        $this->assertSame(2.3333333333333335, $result);
    }

    public function test_avg_function_execute_with_mixed_values(): void
    {
        $function = new AvgFunction;

        $result = $function->execute([10, '20', 30, 'not a number', 40]);

        $this->assertSame(25.0, $result);
    }

    public function test_avg_function_execute_with_empty_array(): void
    {
        $function = new AvgFunction;

        $result = $function->execute([]);

        $this->assertSame(0.0, $result);
    }

    public function test_avg_function_execute_with_single_value(): void
    {
        $function = new AvgFunction;

        $result = $function->execute([85]);

        $this->assertSame(85.0, $result);
    }

    // ==================== MIN FUNCTION TESTS ====================

    public function test_min_function_execute_with_integers(): void
    {
        $function = new MinFunction;

        $result = $function->execute([10, 30, 20, 5]);

        $this->assertEquals(5, $result);
    }

    public function test_min_function_execute_with_floats(): void
    {
        $function = new MinFunction;

        $result = $function->execute([1.5, 2.5, 0.5, 3.0]);

        $this->assertSame(0.5, $result);
    }

    public function test_min_function_execute_with_mixed_values(): void
    {
        $function = new MinFunction;

        $result = $function->execute([10, '20', 30, 'not a number', 5]);

        $this->assertEquals(5.0, $result);
    }

    public function test_min_function_execute_with_empty_array(): void
    {
        $function = new MinFunction;

        $result = $function->execute([]);

        $this->assertSame(0, $result);
    }

    // ==================== MAX FUNCTION TESTS ====================

    public function test_max_function_execute_with_integers(): void
    {
        $function = new MaxFunction;

        $result = $function->execute([10, 30, 20, 5]);

        $this->assertEquals(30, $result);
    }

    public function test_max_function_execute_with_floats(): void
    {
        $function = new MaxFunction;

        $result = $function->execute([1.5, 2.5, 0.5, 3.0]);

        $this->assertSame(3.0, $result);
    }

    public function test_max_function_execute_with_mixed_values(): void
    {
        $function = new MaxFunction;

        $result = $function->execute([10, '20', 30, 'not a number', 5]);

        $this->assertEquals(30.0, $result);
    }

    public function test_max_function_execute_with_empty_array(): void
    {
        $function = new MaxFunction;

        $result = $function->execute([]);

        $this->assertSame(0, $result);
    }

    // ==================== LENGTH FUNCTION TESTS ====================

    public function test_length_function_execute_with_string(): void
    {
        $function = new LengthFunction;

        $result = $function->execute('hello');

        $this->assertSame(5, $result);
    }

    public function test_length_function_execute_with_array(): void
    {
        $function = new LengthFunction;

        $result = $function->execute(['a', 'b', 'c']);

        $this->assertSame(3, $result);
    }

    public function test_length_function_execute_with_empty_string(): void
    {
        $function = new LengthFunction;

        $result = $function->execute('');

        $this->assertSame(0, $result);
    }

    public function test_length_function_execute_with_empty_array(): void
    {
        $function = new LengthFunction;

        $result = $function->execute([]);

        $this->assertSame(0, $result);
    }

    public function test_length_function_execute_with_null(): void
    {
        $function = new LengthFunction;

        $result = $function->execute(null);

        $this->assertSame(0, $result);
    }

    // ==================== JSON_LENGTH FUNCTION TESTS ====================

    public function test_json_length_function_execute_with_array(): void
    {
        $function = new JsonLengthFunction;

        $result = $function->execute(['a', 'b', 'c']);

        $this->assertSame(3, $result);
    }

    public function test_json_length_function_execute_with_empty_array(): void
    {
        $function = new JsonLengthFunction;

        $result = $function->execute([]);

        $this->assertSame(0, $result);
    }

    public function test_json_length_function_execute_with_string(): void
    {
        $function = new JsonLengthFunction;

        $result = $function->execute('hello');

        $this->assertSame(0, $result);
    }

    public function test_json_length_function_execute_with_null(): void
    {
        $function = new JsonLengthFunction;

        $result = $function->execute(null);

        $this->assertSame(0, $result);
    }

    // ==================== TO SQL TESTS ====================

    public function test_count_function_to_sql_sqlite(): void
    {
        $function = new CountFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::SQLITE);

        $this->assertSame(
            'json_array_length(clusters, \'$.addresses\')',
            $sql
        );
    }

    public function test_count_function_to_sql_mysql(): void
    {
        $function = new CountFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::MYSQL);

        $this->assertSame(
            'JSON_LENGTH(clusters, \'$.addresses\')',
            $sql
        );
    }

    public function test_count_function_to_sql_pgsql(): void
    {
        $function = new CountFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::PGSQL);

        $this->assertSame(
            'jsonb_array_length(clusters->\'addresses\')',
            $sql
        );
    }

    public function test_sum_function_to_sql_sqlite(): void
    {
        $function = new SumFunction;

        $sql = $function->toSql(self::COLUMN, 'prices', DatabaseDriver::SQLITE);

        $this->assertSame(
            'CAST(json_extract(clusters, \'$.prices\') AS NUMERIC)',
            $sql
        );
    }

    public function test_sum_function_to_sql_mysql(): void
    {
        $function = new SumFunction;

        $sql = $function->toSql(self::COLUMN, 'prices', DatabaseDriver::MYSQL);

        $this->assertSame(
            'CAST(JSON_EXTRACT(clusters, \'$.prices\') AS DECIMAL(10,2))',
            $sql
        );
    }

    public function test_sum_function_to_sql_pgsql(): void
    {
        $function = new SumFunction;

        $sql = $function->toSql(self::COLUMN, 'prices', DatabaseDriver::PGSQL);

        $this->assertSame(
            '(clusters->>\'prices\')::numeric',
            $sql
        );
    }

    public function test_avg_function_to_sql_sqlite(): void
    {
        $function = new AvgFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::SQLITE);

        $this->assertSame(
            'AVG(CAST(json_extract(clusters, \'$.scores\') AS NUMERIC))',
            $sql
        );
    }

    public function test_avg_function_to_sql_mysql(): void
    {
        $function = new AvgFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::MYSQL);

        $this->assertSame(
            'AVG(CAST(JSON_EXTRACT(clusters, \'$.scores\') AS DECIMAL(10,2)))',
            $sql
        );
    }

    public function test_avg_function_to_sql_pgsql(): void
    {
        $function = new AvgFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::PGSQL);

        $this->assertSame(
            'AVG((clusters->>\'scores\')::numeric)',
            $sql
        );
    }

    public function test_min_function_to_sql_sqlite(): void
    {
        $function = new MinFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::SQLITE);

        $this->assertSame(
            'MIN(CAST(json_extract(clusters, \'$.scores\') AS NUMERIC))',
            $sql
        );
    }

    public function test_min_function_to_sql_mysql(): void
    {
        $function = new MinFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::MYSQL);

        $this->assertSame(
            'MIN(CAST(JSON_EXTRACT(clusters, \'$.scores\') AS DECIMAL(10,2)))',
            $sql
        );
    }

    public function test_min_function_to_sql_pgsql(): void
    {
        $function = new MinFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::PGSQL);

        $this->assertSame(
            'MIN((clusters->>\'scores\')::numeric)',
            $sql
        );
    }

    public function test_max_function_to_sql_sqlite(): void
    {
        $function = new MaxFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::SQLITE);

        $this->assertSame(
            'MAX(CAST(json_extract(clusters, \'$.scores\') AS NUMERIC))',
            $sql
        );
    }

    public function test_max_function_to_sql_mysql(): void
    {
        $function = new MaxFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::MYSQL);

        $this->assertSame(
            'MAX(CAST(JSON_EXTRACT(clusters, \'$.scores\') AS DECIMAL(10,2)))',
            $sql
        );
    }

    public function test_max_function_to_sql_pgsql(): void
    {
        $function = new MaxFunction;

        $sql = $function->toSql(self::COLUMN, 'scores', DatabaseDriver::PGSQL);

        $this->assertSame(
            'MAX((clusters->>\'scores\')::numeric)',
            $sql
        );
    }

    public function test_length_function_to_sql_sqlite(): void
    {
        $function = new LengthFunction;

        $sql = $function->toSql(self::COLUMN, 'name', DatabaseDriver::SQLITE);

        $this->assertSame(
            'LENGTH(json_extract(clusters, \'$.name\'))',
            $sql
        );
    }

    public function test_length_function_to_sql_mysql(): void
    {
        $function = new LengthFunction;

        $sql = $function->toSql(self::COLUMN, 'name', DatabaseDriver::MYSQL);

        $this->assertSame(
            'LENGTH(JSON_EXTRACT(clusters, \'$.name\'))',
            $sql
        );
    }

    public function test_length_function_to_sql_pgsql(): void
    {
        $function = new LengthFunction;

        $sql = $function->toSql(self::COLUMN, 'name', DatabaseDriver::PGSQL);

        $this->assertSame(
            'LENGTH(clusters->>\'name\')',
            $sql
        );
    }

    public function test_json_length_function_to_sql_sqlite(): void
    {
        $function = new JsonLengthFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::SQLITE);

        $this->assertSame(
            'json_array_length(clusters, \'$.addresses\')',
            $sql
        );
    }

    public function test_json_length_function_to_sql_mysql(): void
    {
        $function = new JsonLengthFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::MYSQL);

        $this->assertSame(
            'JSON_LENGTH(clusters, \'$.addresses\')',
            $sql
        );
    }

    public function test_json_length_function_to_sql_pgsql(): void
    {
        $function = new JsonLengthFunction;

        $sql = $function->toSql(self::COLUMN, self::PATH, DatabaseDriver::PGSQL);

        $this->assertSame(
            'jsonb_array_length(clusters->\'addresses\')',
            $sql
        );
    }

    // ==================== VALIDATION TESTS ====================

    public function test_validate_args_with_single_argument(): void
    {
        $function = new CountFunction;

        $this->assertTrue($function->validateArgs(['path']));
    }

    public function test_validate_args_with_multiple_arguments(): void
    {
        $function = new CountFunction;

        $this->assertFalse($function->validateArgs(['path', 'extra']));
    }

    public function test_validate_args_with_empty_arguments(): void
    {
        $function = new CountFunction;

        $this->assertFalse($function->validateArgs([]));
    }

    // ==================== GETTER TESTS ====================

    public function test_function_names(): void
    {
        $functions = [
            'COUNT' => new CountFunction,
            'SUM' => new SumFunction,
            'AVG' => new AvgFunction,
            'MIN' => new MinFunction,
            'MAX' => new MaxFunction,
            'LENGTH' => new LengthFunction,
            'JSON_LENGTH' => new JsonLengthFunction,
        ];

        foreach ($functions as $name => $function) {
            $this->assertSame($name, $function->getName());
        }
    }

    public function test_return_types(): void
    {
        $functions = [
            new CountFunction,
            new LengthFunction,
            new JsonLengthFunction,
        ];

        foreach ($functions as $function) {
            $this->assertSame('int', $function->getReturnType());
        }

        $floatFunctions = [
            new SumFunction,
            new AvgFunction,
            new MinFunction,
            new MaxFunction,
        ];

        foreach ($floatFunctions as $function) {
            $this->assertSame('float', $function->getReturnType());
        }
    }

    public function test_get_default_value(): void
    {
        $function = new CountFunction;

        $this->assertSame(0, $function->getDefaultValue());
    }

    // ==================== EDGE CASES ====================

    public function test_sum_function_with_nested_array_and_non_numeric(): void
    {
        $function = new SumFunction;

        $result = $function->execute([[10, '20'], ['not a number', 30], 40]);

        $this->assertSame(100.0, $result);
    }

    public function test_avg_function_with_nested_array_and_non_numeric(): void
    {
        $function = new AvgFunction;

        $result = $function->execute([[10, '20'], ['not a number', 30], 40]);

        $this->assertSame(25.0, $result);
    }

    public function test_min_function_with_nested_array_and_non_numeric(): void
    {
        $function = new MinFunction;

        $result = $function->execute([[10, '20'], ['not a number', 5], 30]);

        $this->assertEquals(5.0, $result);
    }

    public function test_max_function_with_nested_array_and_non_numeric(): void
    {
        $function = new MaxFunction;

        $result = $function->execute([[10, '20'], ['not a number', 5], 30]);

        $this->assertEquals(30.0, $result);
    }
}
