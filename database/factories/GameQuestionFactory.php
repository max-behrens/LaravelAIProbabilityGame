<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\GameType;

class GameQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question' => $this->faker->sentence() . '?',
            'answer' => $this->faker->word(),
            'game_type_id' => GameType::factory(),
            'difficulty_id' => $this->faker->numberBetween(1, 3),
            'category_id' => $this->faker->numberBetween(1, 3),
            'score_awarded' => $this->faker->randomElement([5, 10, 15]),
        ];
    }
}
