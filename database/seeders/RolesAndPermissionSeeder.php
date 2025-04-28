<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RolesAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         // Reset cached roles and permissions
         app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

         // Permissions
         Permission::firstOrCreate(['name' => 'create posts']);
         Permission::firstOrCreate(['name' => 'edit posts']);
         Permission::firstOrCreate(['name' => 'delete posts']);
         Permission::firstOrCreate(['name' => 'publish posts']);
         Permission::firstOrCreate(['name' => 'unpublish posts']);
 
         // Create roles only if they don't exist
         if (! Role::where('name', 'user')->exists()) {
             $readonlyRole = Role::create(['name' => 'user']);
         } else {
             $readonlyRole = Role::where('name', 'user')->first();
         }
 
         if (! Role::where('name', 'testUser')->exists()) {
             $testRole = Role::create(['name' => 'testUser']);
         } else {
             $testRole = Role::where('name', 'testUser')->first();
         }
 
         if (! Role::where('name', 'adminUser')->exists()) {
             $adminRole = Role::create(['name' => 'adminUser']);
         } else {
             $adminRole = Role::where('name', 'adminUser')->first();
         }
 
         // Assign permissions to roles
         $readonlyRole->givePermissionTo(Permission::all());
         $testRole->givePermissionTo(['publish posts', 'unpublish posts']);
 
         // Create users and assign roles
         $user = \App\Models\User::firstOrCreate(
             ['email' => 'test_readonly@test.com'],
             ['name' => 'Readonly User', 'password' => bcrypt('password')]
         );
         $user->assignRole($readonlyRole);
 
         $testUser = \App\Models\User::firstOrCreate(
             ['email' => 'test@test.com'],
             ['name' => 'Test User', 'password' => bcrypt('password')]
         );
         $testUser->assignRole($testRole);
 
         $adminUser = \App\Models\User::firstOrCreate(
             ['email' => 'test_admin@test.com'],
             ['name' => 'Admin User', 'password' => bcrypt('password')]
         );
         $adminUser->assignRole($adminRole);
    }
}
