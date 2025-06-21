<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameScore;
use App\Models\Games;
use App\Models\User;
use Illuminate\Support\Str;

class GameScoreSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::inRandomOrder()->take(5)->get();
        $games = Games::with('gameType.gameQuestions')->take(3)->get();

        foreach ($games as $game) {
            $questions = $game->gameType->gameQuestions;

            foreach ($users as $user) {
                // Random number of attempts for this user/game, from 1 to 3 (or 1 to 5, adjust as you want)
                $attempts = rand(1, 3);

                for ($attempt = 0; $attempt < $attempts; $attempt++) {
                    $answerJson = [];
                    $score = 0;

                    foreach ($questions as $index => $question) {
                        $isCorrect = rand(0, 1) === 1;
                        $submitted = $isCorrect ? $question->answer : fake()->word;

                        $answerJson[$question->id] = [
                            'question_number' => $index + 1,
                            'question' => $question->question,
                            'submitted' => $submitted,
                            'correct_answer' => $question->answer,
                            'is_correct' => $isCorrect,
                            'score_awarded' => $isCorrect ? ($question->score_awarded ?? 0) : 0,
                        ];

                        $score += $isCorrect ? ($question->score_awarded ?? 0) : 0;
                    }

                    GameScore::factory()->create([
                        'game_id' => $game->id,
                        'player_id' => $user->id,
                        'answer_json' => $answerJson,
                        'score' => $score,
                        'session_id' => Str::uuid()->toString(),
                    ]);
                }
            }
        }
    }
}
