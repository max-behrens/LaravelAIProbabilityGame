<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Games;


class GameUserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'   => User::factory(),
            'game_id'  => Games::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}