<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\GameScore;
use App\Models\Games;
use App\Models\User;

class GameScoreFactory extends Factory
{
    protected $model = GameScore::class;

    public function definition(): array
    {
        return [
            'game_id' => Games::factory(),
            'player_id' => User::factory(),
            'answer_json' => [], // seeded manually in seeder if needed
            'score' => 0,        // calculated in seeder
            'session_id' => $this->faker->uuid,
            'updated_at' => now(),
        ];
    }
}
