<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create or get admin role
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@mage.test'],
            [
                'login' => 'admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'license' => 1,
                'online' => 1,
                'confirm_send_email' => 1,
                'user_role_id' => 1, // Assuming 1 is admin role ID
            ]
        );

        // Assign roles
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
        if (!$admin->hasRole('super-admin')) {
            $admin->assignRole('super-admin');
        }

        $this->command->info('Admin user created: admin@mage.test / password');
    }
}
