<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Games;

class GamesTableSeeder extends Seeder
{
    public function run()
    {
        // This will now automatically set the title to 'Game {id}'
        Games::factory()->count(10)->create();
    }
}
