<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AIScore;
use App\Models\GameScore;
use App\Models\GameType;

class AIScoreSeeder extends Seeder
{
    public function run(): void
    {
        $gameTypes = GameType::with('games.gameQuestions')->get();

        foreach ($gameTypes as $type) {
            $game = $type->games->first();
            if (!$game || $game->gameQuestions->isEmpty()) continue;

            $sessions = GameScore::where('game_id', $game->id)->get()->groupBy('session_id');

            foreach ($sessions as $sessionId => $sessionScores) {
                // Use first game score to get created_at
                $sampleScore = $sessionScores->first();
                $createdAt = $sampleScore->created_at ?? now();

                // AI should only appear in some sessions:
                $daysAgo = now()->diffInDays($createdAt);
                $includeAI = $daysAgo <= 4
                    ? rand(1, 100) <= 40 // ~40% for recent sessions
                    : rand(1, 100) <= 70; // ~70% for older ones

                if (!$includeAI) continue;

                // Build perfect answer JSON
                $questions = $game->gameQuestions;
                $answerJson = [];
                $totalScore = 0;

                foreach ($questions as $index => $question) {
                    $scoreAwarded = $question->score_awarded ?? 0;

                    $answerJson[$question->id] = [
                        'question_number' => $index + 1,
                        'question' => $question->question,
                        'submitted' => $question->answer,
                        'correct_answer' => $question->answer,
                        'is_correct' => true,
                        'score_awarded' => $scoreAwarded,
                    ];

                    $totalScore += $scoreAwarded;
                }

                AIScore::factory()->create([
                    'game_id' => $game->id,
                    'session_id' => $sessionId,
                    'answer_json' => json_encode($answerJson),
                    'score' => $totalScore,
                    'created_at' => $createdAt,
                ]);
            }
        }
    }
}
