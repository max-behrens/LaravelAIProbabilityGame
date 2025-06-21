<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\GameType;


/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameType>
 */
class GameTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement(GameType::DEFAULT_TYPES),
        ];
    }
}
