<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        // phpunit.xml sets $_ENV, but env() uses getenv() which may read from .env file
        // We must ensure putenv() is set BEFORE bootstrapping so env() returns the correct value
        $appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: env('APP_ENV');
        
        if ($appEnv === 'testing') {
            $envPath = __DIR__ . '/../.env';
            if (!file_exists($envPath)) {
                $examplePath = __DIR__ . '/../.env.example';
                if (file_exists($examplePath)) {
                    copy($examplePath, $envPath);
                } else {
                    file_put_contents($envPath, "APP_ENV=testing\n");
                }
            }

            // phpunit.xml sets these in $_ENV, ensure they're also in putenv() for env() helper
            $dbConnection = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: 'sqlite';
            $dbDatabase = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: ':memory:';
            
            // Set in both $_ENV and putenv so both env() and direct $_ENV access work
            $_ENV['APP_ENV'] = 'testing';
            $_ENV['DB_CONNECTION'] = $dbConnection;
            $_ENV['DB_DATABASE'] = $dbDatabase;
            putenv("APP_ENV=testing");
            putenv("DB_CONNECTION={$dbConnection}");
            putenv("DB_DATABASE={$dbDatabase}");
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();
        
        // Double-check database config after bootstrap
        if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing') {
            $dbConnection = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: 'sqlite';
            $dbDatabase = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: ':memory:';
            
            config(['database.default' => $dbConnection]);
            if ($dbConnection === 'sqlite') {
                config(['database.connections.sqlite.database' => $dbDatabase]);
            }
        }
        
        return $app;
    }
}
