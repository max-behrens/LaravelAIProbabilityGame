<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {    

        DB::table('game_questions')->insert([
            'id' => 1,
            'game_type_id' => 1,
            'question' => 'What is 10 + 10?',
            'answer' => 20,
            'score_awarded' => 5
        ]);
    }
    
}
