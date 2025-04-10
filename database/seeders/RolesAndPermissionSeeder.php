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

        Permission::firstOrCreate(['name' => 'create posts']);
        Permission::firstOrCreate(['name' => 'edit posts']);
        Permission::firstOrCreate(['name' => 'delete posts']);
        Permission::firstOrCreate(['name' => 'publish posts']);
        Permission::firstOrCreate(['name' => 'unpublish posts']);

        $readonlyRole = Role::create(['name' => 'user'])
            ->givePermissionTo(Permission::all());

        $testRole = Role::create(['name' => 'testUser'])
            ->givePermissionTo(['publish posts', 'unpublish posts']);

        $adminRole = Role::create(['name' => 'adminUser']);

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
