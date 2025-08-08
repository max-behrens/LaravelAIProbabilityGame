<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Games;
use App\Models\AIScore;
use App\Models\GameScore;
use App\Models\GameQuestion;
use Illuminate\Support\Facades\Log;

class AIScoreSeeder extends Seeder
{
    public function run(): void
    {
        $insertedCount = 0; // Track how many AI scores have been inserted

        // Get all existing game_scores grouped by session_id
        $sessions = GameScore::with('game') // eager load the game
            ->get()
            ->groupBy('session_id');

        foreach ($sessions as $sessionId => $sessionScores) {
            if ($insertedCount >= 20) {
                break; // Stop once we've inserted 10 AI scores
            }

            // Skip if an AI score already exists for this session
            if (AIScore::where('session_id', $sessionId)->exists()) {
                continue;
            }

            $sampleScore = $sessionScores->first();
            $gameId = $sampleScore->game_id;

            $answerData = $sampleScore->answer_json;
            if (is_string($answerData)) {
                $answerData = json_decode($answerData, true);
            }
            $questionIds = array_keys($answerData ?? []);

            if (empty($questionIds)) {
                Log::warning("No question IDs found for session {$sessionId}");
                continue;
            }

            $firstQuestion = GameQuestion::find($questionIds[0]);
            if (!$firstQuestion) {
                continue;
            }

            $difficultyId = $firstQuestion->difficulty_id;
            $categoryId   = $firstQuestion->category_id;

            // Ensure difficulty/category/game combo exists in game_scores
            $matchingHuman = $sessionScores->filter(function ($score) use ($difficultyId, $categoryId) {
                $answers = is_string($score->answer_json) 
                    ? json_decode($score->answer_json, true) 
                    : $score->answer_json;
                if (!$answers) return false;

                $firstQId = array_key_first($answers);
                $question = GameQuestion::find($firstQId);
                return $question 
                    && $question->difficulty_id === $difficultyId 
                    && $question->category_id === $categoryId;
            });

            if ($matchingHuman->isEmpty()) {
                continue;
            }

            $questions = GameQuestion::whereIn('id', $questionIds)->get();

            if ($questions->count() < 5) {
                continue;
            }

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
                $answerJson['difficulty_id'] = $difficultyId;
                $answerJson['category_id'] = $categoryId;
                $totalScore += $scoreAwarded;
            }

            AIScore::factory()->create([
                'game_id' => $gameId,
                'session_id' => $sessionId,
                'answer_json' => $answerJson,
                'score' => $totalScore,
                'created_at' => $sampleScore->created_at,
            ]);

            $insertedCount++; // Increment count
        }
    }
}
