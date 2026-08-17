<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Functions;

use AndyDefer\PhpVo\Enums\SpaceTimeUnit;
use AndyDefer\PhpVo\ValueObjects\CoordinatesVO;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;

/**
 * DistanceFunction - Calculates geographic distance between coordinates in memory.
 *
 * This function calculates the distance between two points using the Haversine formula.
 * It supports both meters and kilometers and can accept either CoordinatesVO objects
 * or arrays with latitude/longitude keys.
 *
 * @example
 * // Filter clusters by distance
 * $collection->whereAggregate('{DISTANCE(coordinates, 48.8566, 2.3522, km) < 5}');
 * @example
 * // Distance in meters (default)
 * $collection->whereAggregate('{DISTANCE(coordinates, 48.8566, 2.3522) < 5000}');
 */
final class DistanceFunction extends AbstractAggregateFunction
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
     * @return float Default value 0.0
     */
    public function getDefaultValue(): mixed
    {
        return 0.0;
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
     * Determines if this function returns a boolean value.
     *
     * @return bool False - this function returns a float
     */
    public function returnsBoolean(): bool
    {
        return false;
    }

    /**
     * Executes the function in memory.
     * Accepts either CoordinatesVO or an array with latitude/longitude.
     *
     * @param  array<string, mixed>  $data  The data to extract coordinates from
     * @param  array<mixed>  $args  Arguments: [path, lat, lon, unit?]
     * @return float The distance in the specified unit
     */
    public function execute(array $data, array $args): float
    {
        if (count($args) < 3) {
            return 0.0;
        }

        $path = $args[0];
        $lat = (float) ($args[1] ?? 0);
        $lon = (float) ($args[2] ?? 0);
        $unit = isset($args[3]) ? strtoupper((string) $args[3]) : 'M';

        $coordinates = $this->extractCoordinates($data, $path);

        if ($coordinates === null) {
            return 0.0;
        }

        $target = new CoordinatesVO(
            FloatVO::from($lat),
            FloatVO::from($lon)
        );

        $distanceUnit = $unit === 'KM' ? SpaceTimeUnit::KILOMETRE : SpaceTimeUnit::METRE;

        $distance = $coordinates->distanceTo($target, $distanceUnit)->getValue();

        if ($distanceUnit === SpaceTimeUnit::METRE) {
            return (float) round($distance);
        }

        return (float) round($distance, 2);
    }

    /**
     * Extracts coordinates from the data array using a dot-notation path.
     *
     * @param  array<string, mixed>  $data  The data array
     * @param  string  $path  The dot-notation path to the coordinates
     * @return CoordinatesVO|null The extracted coordinates, or null if not found
     */
    private function extractCoordinates(array $data, string $path): ?CoordinatesVO
    {
        $value = $this->resolveArg($data, $path);

        if ($value instanceof CoordinatesVO) {
            return $value;
        }

        if (is_array($value) && isset($value['latitude'], $value['longitude'])) {
            return CoordinatesVO::from($value);
        }

        return null;
    }
}
