<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameScore;
use App\Models\Games;
use App\Models\GameType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class GameScoreSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::inRandomOrder()->take(5)->get();
        $gameTypes = GameType::with('games.gameQuestions')->get();

        foreach ($gameTypes as $type) {
            // Choose one game per type (you could randomize or take all if needed)
            $game = $type->games->first();

            // Skip if no game or no questions
            if (!$game || $game->gameQuestions->isEmpty()) {
                continue;
            }

            $questions = $game->gameType->gameQuestions;

            foreach ($users as $user) {
                $attempts = 3; // Exactly 3 attempts per user per game

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
                        'created_at' => Carbon::now()->subDays(rand(0, 60))->subHours(rand(0, 23))->subMinutes(rand(0, 59)),
                    ]);
                }
            }
        }
    }
}
