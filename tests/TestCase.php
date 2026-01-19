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
        // Use config() instead of env() since app is already bootstrapped
        if (app()->environment('testing')) {
            $dbConnection = config('database.default', 'sqlite');
            $dbDatabase = config(
                'database.connections.sqlite.database',
                ':memory:'
            );
            if ($dbConnection === 'sqlite' 
                && !file_exists($dbDatabase) 
                && $dbDatabase !== ':memory:'
            ) {
                // Ensure SQLite database file exists if using file-based database
                touch($dbDatabase);
            }
        }
    }
}
