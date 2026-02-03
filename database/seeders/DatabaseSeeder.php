<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (Storage::directories() as $directory) {
            Storage::deleteDirectory($directory);
        }

        $seeders = [
            'Database\\Seeders\\LocationSeeder',
            'Database\\Seeders\\UserRoleSeeder',
            'Database\\Seeders\\UserSeeder',
            'Database\\Seeders\\PermissionSeeder',
            'Database\\Seeders\\CategorySeeder',
            'Database\\Seeders\\ProductSeeder',
            'Database\\Seeders\\PromoCodeSeeder',
            'Database\\Seeders\\OrderSeeder',
            'Database\\Seeders\\QuestionSeeder',
            'Database\\Seeders\\RoleAndPermissionSeeder',
            'Database\\Seeders\\WalletTypeSeeder',
            'Database\\Seeders\\FilmProjectSeeder',
        ];

        $this->call(array_values(array_filter($seeders, static function (string $seeder): bool {
            return class_exists($seeder);
        })));
    }
}