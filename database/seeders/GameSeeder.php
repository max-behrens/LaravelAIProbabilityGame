<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Games;
use App\Models\GameType;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        $gameTypes = GameType::all();

        if ($gameTypes->count() < 3) {
            throw new \Exception('At least 3 game types are required to seed games.');
        }

        // Create 5 games per game type
        foreach ($gameTypes as $type) {
            foreach (range(1, 5) as $i) {
                Games::factory()->create([
                    'game_type_id' => $type->id,
                    'max_players'  => 2,  // or whatever default max
                ]);
            }
        }
    }
}
