<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Database\Seeder;
use App\Constant\DefaultConstant;
use Illuminate\Support\Facades\DB;
use App\Constant\UserRoleConstant;
use Illuminate\Support\Facades\Schema;

class UserSeeder extends Seeder
{
    public function run()
    {
        $registeredRoleRow = DB::table('user_roles')
            ->where('type', '=', UserRoleConstant::REGISTERED)
            ->first();

        if (!$registeredRoleRow) {
            $this->command->error('Registered user role not found. Please run the user_roles seeder first.');
            return;
        }

        $registeredRole = $registeredRoleRow->id;

        $adminRoleRow = DB::table('user_roles')
            ->where('type', '=', UserRoleConstant::ADMINISTRATOR)
            ->first();

        if (!$adminRoleRow) {
            $this->command->error('Administrator user role not found. Please run the user_roles seeder first.');
            return;
        }

        $adminRole = $adminRoleRow->id;

        $userData = [
            'login' => 'admin',
            'password' => bcrypt('secret'),
            'email' => 'admin@jsonapi.com',
            'created_at' => Carbon::now(),
            'user_role_id' => $adminRole,
        ];

        if (Schema::hasColumn('users', 'name')) {
            $userData['name'] = 'Admin';
        }
        if (Schema::hasColumn('users', 'license')) {
            $userData['license'] = DefaultConstant::TRUE;
        }
        if (Schema::hasColumn('users', 'user_photo')) {
            $userData['user_photo'] = null;
        }
        if (Schema::hasColumn('users', 'online')) {
            $userData['online'] = DefaultConstant::TRUE;
        }
        if (Schema::hasColumn('users', 'confirm_send_email')) {
            $userData['confirm_send_email'] = DefaultConstant::TRUE;
        }

        $existingAdmin = DB::table('users')
            ->where('email', 'admin@jsonapi.com')
            ->exists();

        if (!$existingAdmin) {
            DB::table('users')->insert($userData);
        }

        User::factory(10)->create([
            'user_role_id' => $registeredRole,
        ]);
    }
}
