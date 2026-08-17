<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\SqlFunctions;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\PhpVo\Enums\SpaceTimeUnit;
use AndyDefer\PhpVo\ValueObjects\CoordinatesVO;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;

/**
 * Distance function for calculating geographic distance between coordinates.
 *
 * Calculates the distance between two points using the Haversine formula.
 * Supports both meters and kilometers.
 *
 * @example
 * // Distance in meters (default)
 * DISTANCE(coordinates, 48.8566, 2.3522) < 5000
 *
 * // Distance in kilometers
 * DISTANCE(coordinates, 48.8566, 2.3522, km) < 5
 *
 * // With other conditions
 * DISTANCE(coordinates, 48.8566, 2.3522, km) < 5 & status=active
 */
final class DistanceFunction extends AbstractSqlFunction
{
    /** @var float Earth radius in kilometers */
    private const EARTH_RADIUS_KM = 6371.0;

    /** @var float Degrees to radians conversion factor */
    private const DEGREES_TO_RADIANS = M_PI / 180;

    /**
     * Gets the function name.
     *
     * @return string The function name 'DISTANCE'
     */
    public function getName(): string
    {
        return 'DISTANCE';
    }

    /**
     * Gets the return type of the function.
     *
     * @return string The return type 'float'
     */
    public function getReturnType(): string
    {
        return 'float';
    }

    /**
     * Gets the default value when function fails.
     *
     * @return string Default value '0'
     */
    public function getDefaultValue(): mixed
    {
        return '0';
    }

    /**
     * Gets the minimum number of arguments required.
     *
     * @return int Minimum 3 arguments (path, lat, lon)
     */
    public function getMinArgs(): int
    {
        return 3;
    }

    /**
     * Gets the maximum number of arguments allowed.
     *
     * @return int Maximum 4 arguments (path, lat, lon, unit)
     */
    public function getMaxArgs(): int
    {
        return 4;
    }

    /**
     * Validates the function arguments.
     *
     * @param  array<mixed>  $args  The arguments to validate
     * @return bool True if arguments are valid
     */
    public function validateArgs(array $args): bool
    {
        $count = count($args);

        if ($count < $this->getMinArgs() || $count > $this->getMaxArgs()) {
            return false;
        }

        if ($count === 4) {
            $unit = strtoupper((string) $args[3]);
            if (! in_array($unit, ['M', 'KM'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generates SQL for the function.
     *
     * @param  string  $column  The column containing JSON data
     * @param  string  $path  The JSON path to coordinates
     * @param  DatabaseDriver  $driver  The database driver
     * @param  array<mixed>  $args  Arguments: [path, lat, lon, unit?]
     * @return string The SQL expression
     */
    public function toSql(string $column, string $path, DatabaseDriver $driver, array $args = []): string
    {
        $lat = (float) ($args[1] ?? 0);
        $lon = (float) ($args[2] ?? 0);
        $unit = isset($args[3]) ? strtoupper((string) $args[3]) : 'M';

        return match ($driver) {
            DatabaseDriver::SQLITE => $this->toSqlSQLite($column, $path, $lat, $lon, $unit),
            DatabaseDriver::MYSQL => $this->toSqlMySQL($column, $path, $lat, $lon, $unit),
            DatabaseDriver::PGSQL => $this->toSqlPostgreSQL($column, $path, $lat, $lon, $unit),
        };
    }

    /**
     * Generates SQL for SQLite driver.
     *
     * @param  string  $column  The column name
     * @param  string  $path  The JSON path
     * @param  float  $lat  Target latitude
     * @param  float  $lon  Target longitude
     * @param  string  $unit  Unit of measurement (M or KM)
     * @return string SQLite SQL expression
     */
    private function toSqlSQLite(string $column, string $path, float $lat, float $lon, string $unit): string
    {
        $latRad = $lat * self::DEGREES_TO_RADIANS;
        $lonRad = $lon * self::DEGREES_TO_RADIANS;
        $radius = $unit === 'KM' ? self::EARTH_RADIUS_KM : self::EARTH_RADIUS_KM * 1000;

        return "(
            SELECT {$radius} * 2 * ATAN2(
                SQRT(
                    POW(SIN((RADIANS(json_extract({$column}, '$.\"{$path}\".latitude')) - {$latRad}) / 2), 2) +
                    COS(RADIANS(json_extract({$column}, '$.\"{$path}\".latitude'))) *
                    COS({$latRad}) *
                    POW(SIN((RADIANS(json_extract({$column}, '$.\"{$path}\".longitude')) - {$lonRad}) / 2), 2)
                ),
                SQRT(1 - (
                    POW(SIN((RADIANS(json_extract({$column}, '$.\"{$path}\".latitude')) - {$latRad}) / 2), 2) +
                    COS(RADIANS(json_extract({$column}, '$.\"{$path}\".latitude'))) *
                    COS({$latRad}) *
                    POW(SIN((RADIANS(json_extract({$column}, '$.\"{$path}\".longitude')) - {$lonRad}) / 2), 2)
                ))
            )
        )";
    }

    /**
     * Generates SQL for MySQL driver.
     *
     * @param  string  $column  The column name
     * @param  string  $path  The JSON path
     * @param  float  $lat  Target latitude
     * @param  float  $lon  Target longitude
     * @param  string  $unit  Unit of measurement (M or KM)
     * @return string MySQL SQL expression
     */
    private function toSqlMySQL(string $column, string $path, float $lat, float $lon, string $unit): string
    {
        $latRad = $lat * self::DEGREES_TO_RADIANS;
        $lonRad = $lon * self::DEGREES_TO_RADIANS;
        $radius = $unit === 'KM' ? self::EARTH_RADIUS_KM : self::EARTH_RADIUS_KM * 1000;

        return "(
            SELECT {$radius} * 2 * ATAN2(
                SQRT(
                    POW(SIN((RADIANS(JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.\"{$path}\".latitude'))) - {$latRad}) / 2), 2) +
                    COS(RADIANS(JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.\"{$path}\".latitude')))) *
                    COS({$latRad}) *
                    POW(SIN((RADIANS(JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.\"{$path}\".longitude'))) - {$lonRad}) / 2), 2)
                ),
                SQRT(1 - (
                    POW(SIN((RADIANS(JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.\"{$path}\".latitude'))) - {$latRad}) / 2), 2) +
                    COS(RADIANS(JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.\"{$path}\".latitude')))) *
                    COS({$latRad}) *
                    POW(SIN((RADIANS(JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.\"{$path}\".longitude'))) - {$lonRad}) / 2), 2)
                ))
            )
        )";
    }

    /**
     * Generates SQL for PostgreSQL driver.
     *
     * @param  string  $column  The column name
     * @param  string  $path  The JSON path
     * @param  float  $lat  Target latitude
     * @param  float  $lon  Target longitude
     * @param  string  $unit  Unit of measurement (M or KM)
     * @return string PostgreSQL SQL expression
     */
    private function toSqlPostgreSQL(string $column, string $path, float $lat, float $lon, string $unit): string
    {
        $latRad = $lat * self::DEGREES_TO_RADIANS;
        $lonRad = $lon * self::DEGREES_TO_RADIANS;
        $radius = $unit === 'KM' ? self::EARTH_RADIUS_KM : self::EARTH_RADIUS_KM * 1000;

        return "(
            SELECT {$radius} * 2 * ATAN2(
                SQRT(
                    POW(SIN((RADIANS(({$column}->'{$path}'->>'latitude')::float)) - {$latRad}) / 2), 2) +
                    COS(RADIANS(({$column}->'{$path}'->>'latitude')::float)) *
                    COS({$latRad}) *
                    POW(SIN((RADIANS(({$column}->'{$path}'->>'longitude')::float)) - {$lonRad}) / 2), 2)
                ),
                SQRT(1 - (
                    POW(SIN((RADIANS(({$column}->'{$path}'->>'latitude')::float)) - {$latRad}) / 2), 2) +
                    COS(RADIANS(({$column}->'{$path}'->>'latitude')::float)) *
                    COS({$latRad}) *
                    POW(SIN((RADIANS(({$column}->'{$path}'->>'longitude')::float)) - {$lonRad}) / 2), 2)
                ))
            )
        )";
    }

    /**
     * Executes the function in memory.
     * Accepte soit CoordinatesVO soit un array avec latitude/longitude.
     *
     * @param  mixed  $value  The value to process (CoordinatesVO or array with latitude/longitude)
     * @param  array<mixed>  $args  Arguments: [path, lat, lon, unit?]
     * @return string The distance as a string
     */
    public function execute(mixed $value, array $args = []): string
    {
        // Si c'est un tableau, le convertir en CoordinatesVO
        if (is_array($value) && isset($value['latitude'], $value['longitude'])) {
            $value = CoordinatesVO::from($value);
        }

        if (! $value instanceof CoordinatesVO) {
            return '0';
        }

        $lat = (float) ($args[1] ?? 0);
        $lon = (float) ($args[2] ?? 0);
        $unit = isset($args[3]) ? strtoupper((string) $args[3]) : 'M';

        $target = new CoordinatesVO(
            FloatVO::from($lat),
            FloatVO::from($lon)
        );

        $distanceUnit = $unit === 'KM' ? SpaceTimeUnit::KILOMETRE : SpaceTimeUnit::METRE;

        $distance = $value->distanceTo($target, $distanceUnit)->getValue();

        if ($distanceUnit === SpaceTimeUnit::METRE) {
            return (string) round($distance);
        }

        return (string) round($distance, 2);
    }
}
