<?php

namespace Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Check if the users table already contains users
        if (DB::table('users')->count() > 0) {
            // If there are users, skip the seeding and log it
            \Log::info('Users table already contains data. Skipping user seeding.');
            return;
        }

        // Disable foreign key checks to allow truncating the table if needed
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Insert predefined users
        DB::table('users')->insert([
            [
                'name' => 'ReadOnly Tester',
                'email' => 'test_readonly@test.com',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Regular Tester',
                'email' => 'test@test.com',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Admin Tester',
                'email' => 'test_admin@test.com',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Add 1 random user
        DB::table('users')->insert([
            [
                'name' => Str::random(10),
                'email' => Str::random(10) . '@gmail.com',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // Enable foreign key checks again
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
