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

        $popularDays = collect();
        for ($i = 0; $i < 5; $i++) {
            $popularDays->push(Carbon::now()->subDays(rand(5, 40))->startOfDay());
        }

        foreach ($gameTypes as $type) {
            $game = $type->games->first();
            if (!$game || $game->gameQuestions->isEmpty()) continue;

            $questions = $game->gameType->gameQuestions;

            // Create random sessions
            $numSessions = rand(8, 16); // More sessions overall

            for ($i = 0; $i < $numSessions; $i++) {
                $sessionId = Str::uuid()->toString();

                // Randomly pick 1–2 users to be in the same session
                $sessionPlayers = $users->random(rand(1, 2));

                // Randomly decide session time
                $roll = rand(1, 10);
                if ($roll <= 3) {
                    $createdAt = Carbon::now()->subDays(rand(0, 3))->setHour(rand(8, 22))->setMinute(rand(0, 59));
                } elseif ($roll <= 5) {
                    $createdAt = $popularDays->random()->copy()->addHours(rand(0, 23))->addMinutes(rand(0, 59));
                } else {
                    $createdAt = Carbon::now()->subDays(rand(0, 60))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
                }

                foreach ($sessionPlayers as $user) {
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
                        'session_id' => $sessionId,
                        'created_at' => $createdAt,
                    ]);
                }
            }
        }
    }
}
