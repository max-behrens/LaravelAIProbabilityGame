<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameScore;
use App\Models\GameType;
use App\Models\User;
use App\Models\GameTypeDifficulty;
use App\Models\GameTypeCategory;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GameScoreSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::inRandomOrder()->take(5)->get();
        $gameTypes = GameType::with('games.gameQuestions')->get();
        $difficulties = GameTypeDifficulty::all();
        $categories = GameTypeCategory::all();

        // Pre-generate some "popular" days for seeding
        $popularDays = collect();
        for ($i = 0; $i < 5; $i++) {
            $popularDays->push(Carbon::now()->subDays(rand(5, 40))->startOfDay());
        }

        foreach ($gameTypes as $type) {
            foreach ($type->games as $game) {
                if ($game->gameQuestions->isEmpty()) continue;

                foreach ($difficulties as $difficulty) {
                    foreach ($categories as $category) {
                        // Decide randomly whether to seed this combo (to avoid too much data)
                        if (rand(1, 100) > 70) continue; // ~30% chance to skip

                        // Get exactly the 5 matching questions for this combo
                        $questions = $game->gameQuestions()
                            ->where('difficulty_id', $difficulty->id)
                            ->where('category_id', $category->id)
                            ->take(5)
                            ->get();

                        if ($questions->count() < 5) {
                            Log::warning("Not enough questions for game {$game->id}, diff {$difficulty->id}, cat {$category->id}");
                            continue;
                        }

                        // Create a few sessions for this combo
                        $numSessions = rand(2, 4);

                        for ($s = 0; $s < $numSessions; $s++) {
                            $sessionId = Str::uuid()->toString();

                            // Pick 1–2 users for this session
                            $sessionPlayers = $users->random(rand(1, 2));

                            // Pick created_at time
                            $roll = rand(1, 10);
                            if ($roll <= 3) {
                                $createdAt = Carbon::now()->subDays(rand(0, 3))
                                    ->setHour(rand(8, 22))
                                    ->setMinute(rand(0, 59));
                            } elseif ($roll <= 5) {
                                $createdAt = $popularDays->random()->copy()
                                    ->addHours(rand(0, 23))
                                    ->addMinutes(rand(0, 59));
                            } else {
                                $createdAt = Carbon::now()->subDays(rand(0, 60))
                                    ->subHours(rand(0, 23))
                                    ->subMinutes(rand(0, 59));
                            }

                            foreach ($sessionPlayers as $user) {
                                $answerJson = [];
                                $score = 0;

                                foreach ($questions as $index => $question) {
                                    $isCorrect = rand(1, 100) <= 75;
                                    $submitted = $isCorrect ? $question->answer : fake()->word;

                                    $answerJson[$question->id] = [
                                        'question_number' => $index + 1,
                                        'question' => $question->question,
                                        'submitted' => $submitted,
                                        'correct_answer' => $question->answer,
                                        'is_correct' => $isCorrect,
                                        'score_awarded' => $isCorrect ? ($question->score_awarded ?? 0) : 0,
                                    ];
                                    $answerJson['difficulty_id'] = $difficulty->id;
                                    $answerJson['category_id'] = $category->id;

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
        }
    }
}
