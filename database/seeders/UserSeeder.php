<?php


namespace Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        // Truncate (delete) all rows in case they need to be redefined, but posts with their ids still exist.
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Truncate the users table
        DB::table('users')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');


        // Keep the first 3 manually added users by their emails
        $manualEmails = [
            'test_readonly@test.com', // Example manually added user email
            'test@test.com',
            'test_admin@test.com',
        ];

        // Delete users who do not match the manually added ones
        DB::table('users')
            ->whereNotIn('email', $manualEmails)  // Exclude the manually added users
            ->delete();

        // Seed only 1 new user (as per your limit)
        $limit = 1;
        $data = [];

        for ($i = 0; $i < $limit; ++$i) {
            $data[] = [
                'name' => Str::random(10),
                'email' => Str::random(10) . '@gmail.com',
                'password' => Hash::make('password'),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        DB::table('users')->insert($data);
    }
}
