<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}");

        // Runs before RefreshDatabase can migrate or erase any tables.
        if ($connection !== 'mysql'
            || ($database['host'] ?? null) !== 'mysql-test'
            || ($database['database'] ?? null) !== 'maxmollcrm_test'
            || ! empty($database['url'])) {
            throw new RuntimeException('Tests require the isolated mysql-test / maxmollcrm_test database.');
        }

        return $app;
    }
}
