<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GameQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {    
        DB::table('game_questions')->updateOrInsert(
            ['id' => 1],
            [
                'game_type_id' => 1,
                'question' => 'What is 10 + 10?',
                'answer' => 20,
                'score_awarded' => 5,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );
    }
}
