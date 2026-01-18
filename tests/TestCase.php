<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure test environment uses correct database configuration
        // This is set in CreatesApplication, but we ensure it's correct here too
        if (($_ENV['APP_ENV'] ?? env('APP_ENV')) === 'testing') {
            $dbConnection = $_ENV['DB_CONNECTION'] ?? env('DB_CONNECTION', 'sqlite');
            $dbDatabase = $_ENV['DB_DATABASE'] ?? env('DB_DATABASE', ':memory:');
            
            config(['database.default' => $dbConnection]);
            if ($dbConnection === 'sqlite') {
                config(['database.connections.sqlite.database' => $dbDatabase]);
            }
        }
    }
}
