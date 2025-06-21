<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameType;
use App\Models\GameQuestion;

class GameQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $gameTypes = [
            1 => 'number',
            2 => 'history',
            3 => 'geography',
        ];

        foreach ($gameTypes as $id => $slug) {
            GameType::updateOrInsert(['id' => $id], [
                'name' => ucfirst($slug) . ' Game',
            ]);
        }

        $difficulties = ['easy', 'medium', 'hard'];

        foreach ($gameTypes as $id => $slug) {
            foreach ($difficulties as $difficulty) {
                GameQuestion::factory()
                    ->forGameTypeAndDifficulty($slug, $difficulty)
                    ->create(['game_type_id' => $id]);
            }
        }
    }
}
