<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\GameType;
use App\Models\Games;
use App\Models\User; 

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Games>
 */
class GamesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'game_type_id' => GameType::inRandomOrder()->first()->id ?? GameType::factory(),
            'max_players' => 4,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Games $game) {
            // Remove existing users (if any)
            $game->users()->detach();

            $maxPlayers = $game->max_players ?? 1;
            $users = User::inRandomOrder()->take($maxPlayers)->get();

            $game->users()->attach($users->pluck('id')->toArray());
        });
    }


}
