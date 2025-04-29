<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GameQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $gameType = \App\Models\GameType::first();
    
        DB::table('game_questions')->insert([
            'game_type_id' => $gameType->id,
            'question' => 'What is 10 + 10?',
            'answer' => 20,
            'score_awarded' => 5
        ]);
    }
    
}
