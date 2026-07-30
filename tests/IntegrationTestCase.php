<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests;

use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelCluster\Providers\ClusterServiceProvider;
use Illuminate\Support\Collection;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected string $databasePath;

    protected function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]+m/', '', $text);
    }

    protected function normalize(ClusterVOCollection|Collection $collection): array
    {
        return action_normalizer_chain(true)->normalize($collection);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ClusterServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void {}

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }

    protected function runMigrations(): void
    {
        // 1. Charger les migrations des fixtures (modèles de test)
        $fixtureMigrations = __DIR__.'/Fixtures/migrations';
        if (is_dir($fixtureMigrations)) {
            $this->loadMigrationsFrom($fixtureMigrations);
        }
    }
}
