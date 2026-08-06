<?php

declare(strict_types=1);

namespace AndyDefer\LaravelCluster\Tests;

abstract class SqliteTestCase extends IntegrationTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Forcer SQLite en mémoire
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // S'assurer que MySQL n'est pas utilisé
        $app['config']->set('database.connections.mysql', []);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Vérifier qu'on est bien sur SQLite
        if (! $this->isSQLite()) {
            $this->markTestSkipped('Ce test nécessite SQLite');
        }
    }
}
