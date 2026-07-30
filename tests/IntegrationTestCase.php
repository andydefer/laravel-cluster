<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests;

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Providers\ClusterServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for integration tests.
 *
 * Provides common functionality for integration tests including:
 * - Database migration loading
 * - Service provider registration
 * - Data normalization helpers
 * - ANSI color stripping for test output
 */
abstract class IntegrationTestCase extends Orchestra
{
    protected string $databasePath;

    /**
     * Strips ANSI color codes from a string.
     *
     * @param  string  $text  The text to clean
     * @return string The text without ANSI color codes
     */
    protected function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]+m/', '', $text);
    }

    /**
     * Normalizes a collection using the global normalizer chain.
     *
     * @param  ClusterVOCollection|Collection  $collection  The collection to normalize
     * @return array The normalized data as an array
     */
    protected function normalize(ClusterVOCollection|Collection $collection): array
    {
        return action_normalizer_chain(true)->normalize($collection);
    }

    /**
     * Returns the package service providers to register.
     *
     * @param  Application  $app  The application instance
     * @return array<string> The service providers to register
     */
    protected function getPackageProviders($app): array
    {
        return [
            ClusterServiceProvider::class,
        ];
    }

    /**
     * Defines the environment configuration.
     *
     * @param  Application  $app  The application instance
     */
    protected function defineEnvironment($app): void {}

    /**
     * Sets up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    /**
     * Cleans up the test environment.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }

    /**
     * Runs database migrations for test fixtures.
     */
    protected function runMigrations(): void
    {
        $fixtureMigrations = __DIR__.'/Fixtures/migrations';
        if (is_dir($fixtureMigrations)) {
            $this->loadMigrationsFrom($fixtureMigrations);
        }
    }
}
