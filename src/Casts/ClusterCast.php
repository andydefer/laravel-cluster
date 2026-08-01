<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Casts;

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast for Eloquent models to automatically convert between JSON and ClusterVO.
 *
 * This cast allows you to use ClusterVO directly in your Eloquent models:
 *
 * @example
 * class User extends Model
 * {
 *     protected $casts = [
 *         'metadata' => ClusterCast::class,
 *     ];
 * }
 *
 * // When retrieving:
 * $cluster = $user->metadata; // ClusterVO instance
 * $cluster->get('status'); // 'active'
 *
 * // When setting:
 * $user->metadata = ['status' => 'active', 'role' => 'admin'];
 * // Automatically converted to ClusterVO
 */
final class ClusterCast implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying database values.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?ClusterVO
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE || empty($value)) {
                return null;
            }
        }

        if (! is_array($value) || empty($value)) {
            return null;
        }

        return new ClusterVO($value);
    }

    /**
     * Transform the attribute to its underlying database values.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ClusterVO) {
            return json_encode($value->toArray());
        }

        if (is_array($value)) {
            if (empty($value)) {
                return null;
            }
            new ClusterVO($value);

            return json_encode($value);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && ! empty($decoded)) {
                // ClusterVO constructor will validate the data
                new ClusterVO($decoded);

                return $value;
            }
        }

        // If we get here, the value is invalid
        return null;
    }
}
