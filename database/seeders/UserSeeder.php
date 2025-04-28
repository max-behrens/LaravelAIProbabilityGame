<?php


namespace Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Instead of truncating, just create users if they don't exist
        DB::table('users')->updateOrInsert(
            ['email' => 'test_readonly@test.com'],  // Check for existing user by email
            [
                'name' => 'ReadOnly Tester',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('users')->updateOrInsert(
            ['email' => 'test@test.com'],
            [
                'name' => 'Regular Tester',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('users')->updateOrInsert(
            ['email' => 'test_admin@test.com'],
            [
                'name' => 'Admin Tester',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Create a random user if it doesn't exist
        DB::table('users')->updateOrInsert(
            ['email' => 'randomuser' . Str::random(10) . '@gmail.com'],
            [
                'name' => Str::random(10),
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
