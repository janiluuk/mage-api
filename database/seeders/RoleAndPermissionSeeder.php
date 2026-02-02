<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
class RoleAndPermissionSeeder extends Seeder
{
    public function run()
    {
            // Reset cached roles and permissions
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        
    
            
    
            // Assign roles to demo users when present
            $superadmin = User::where('id', 1)->first();
            if ($superadmin && Role::where('name', 'super-admin')->exists()) {
                $superadmin->assignRole('super-admin');
            }
    
            $admin = User::where('id', 1)->first();
            if ($admin && Role::where('name', 'admin')->exists()) {
                $admin->assignRole('admin');
            }

    }
}
