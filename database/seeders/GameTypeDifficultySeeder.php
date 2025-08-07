<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameTypeDifficulty;

class GameTypeDifficultySeeder extends Seeder
{
    public function run(): void
    {
        $difficulties = [
            ['id' => 1, 'name' => 'Easy', 'slug' => 'easy'],
            ['id' => 2, 'name' => 'Medium', 'slug' => 'medium'],
            ['id' => 3, 'name' => 'Hard', 'slug' => 'hard'],
        ];

        foreach ($difficulties as $difficulty) {
            GameTypeDifficulty::updateOrCreate(
                ['id' => $difficulty['id']],
                $difficulty
            );
        }
    }
}