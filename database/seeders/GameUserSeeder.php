<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Games;
use App\Models\User;

class GameUserSeeder extends Seeder
{
    public function run(): void
    {
        // Clear pivot table before seeding
        DB::table('games_user')->truncate();

        $gameTypes = \App\Models\GameType::all();

        foreach ($gameTypes as $gameType) {
            // Only the first 3 games per game type get players
            $gamesWithPlayers = Games::where('game_type_id', $gameType->id)
                ->orderBy('id')
                ->take(3)
                ->get();

            foreach ($gamesWithPlayers as $game) {
                $maxPlayers = $game->max_players ?? 1;

                $users = User::inRandomOrder()->take($maxPlayers)->get();

                foreach ($users as $user) {
                    DB::table('games_user')->insert([
                        'user_id' => $user->id,
                        'game_id' => $game->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
