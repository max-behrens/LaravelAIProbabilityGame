<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        DB::table('games_user')->truncate();

        $this->call([
            RolesAndPermissionSeeder::class,
            UserSeeder::class,
            PostSeeder::class,
            GameTypeSeeder::class,
            GameQuestionSeeder::class,
            GameSeeder::class,
            GameScoreSeeder::class,
            GameUserSeeder::class,
        ]);
    }
}
