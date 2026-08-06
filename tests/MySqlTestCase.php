<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests;

abstract class MySqlTestCase extends IntegrationTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Forcer MySQL avec la base de test
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'cluster_test'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', 'Hello@0405'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        // Désactiver SQLite
        $app['config']->set('database.connections.sqlite', []);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Vérifier qu'on est bien sur MySQL
        if (! $this->isMySQL()) {
            $this->markTestSkipped('Ce test nécessite MySQL');
        }
    }
}
