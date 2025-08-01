<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\AIScore;
use App\Models\Games;

class AIScoreFactory extends Factory
{
    protected $model = AiScore::class;

    public function definition(): array
    {
        return [
            'game_id' => Games::factory(),
            'session_id' => $this->faker->uuid,
            'answer_json' => [],
            'score' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
