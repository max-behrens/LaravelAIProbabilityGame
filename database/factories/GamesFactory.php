<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\GameType;

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
            'title' => 'Game',
            'game_type_id' => GameType::inRandomOrder()->first()->id ?? GameType::factory(),
            'max_players' => 2, // Or any default number
        ];
    }
}
