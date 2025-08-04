<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AIScore;
use App\Models\Games;
use App\Models\GameType;
use App\Models\GameQuestion;
use Illuminate\Support\Facades\DB;

class AIGameService
{

    public function getAIGameScores($gameId, $page = 1, ?string $startDate = null, ?string $endDate = null, $perPage = 5)
    {
        Log::debug('Fetching paginated AI game scores', ['gameId' => $gameId, 'page' => $page, 'perPage' => $perPage]);

        $query = AIScore::query()
            ->where('ai_scores.game_id', $gameId)
            ->orderBy('ai_scores.created_at', 'desc')
            ->select(
                'ai_scores.id',
                'ai_scores.session_id',
                'ai_scores.game_id',
                'ai_scores.score',
                'ai_scores.created_at'
            );

        // Apply date range filter BEFORE pagination
        if ($startDate && $endDate) {
            $query->whereBetween('ai_scores.created_at', [$startDate, $endDate]); // Fixed: was game_scores.created_at
        }

        // NOW paginate after all filters are applied
        $scores = $query->paginate($perPage, ['*'], 'page', $page);

        Log::debug('Fetched AI scores', ['scores' => $scores->items()]);

        $scores->getCollection()->transform(function ($score) {
            // Remove this user transformation for AI scores since there's no user_id
            Log::debug('Transformed AI score', ['score' => $score]);
            return $score;
        });

        return $scores;
    }


    /**
     * Get AI answer for a specific question
     */
    public function getAIAnswerForQuestion($question)
    {
        Log::info('Getting AI answer for question', ['question' => $question]);

        try {
            $response = Http::withoutVerifying()
                ->withToken(config('services.openai.secret'))
                ->withHeaders([
                    'OpenAI-Organization' => config('services.openai.organisation'),
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    "model" => "gpt-3.5-turbo-0125",
                    "messages" => [
                        [
                            "role" => "system", 
                            "content" => "You are an AI participating in a trivia game. You must provide concise, accurate answers to questions. Give only the answer without additional explanation or context."
                        ],
                        [
                            "role" => "user", 
                            "content" => "Question: " . $question
                        ],
                        [
                            "role" => "assistant", 
                            "content" => "Please provide a direct, concise answer to this trivia question. Do not include explanations, just the answer."
                        ]
                    ],
                    "max_tokens" => 100,
                    "temperature" => 0.3 // Lower temperature for more consistent answers
                ]);

            if ($response->failed()) {
                Log::error('OpenAI API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                throw new \Exception('OpenAI API request failed');
            }

            $aiResponse = $response->json('choices.0.message.content');
            
            if (!$aiResponse) {
                Log::error('No AI response received');
                throw new \Exception('No AI response received');
            }

            // Clean up the response (remove quotes, trim whitespace)
            $aiResponse = trim($aiResponse, '"\'');
            $aiResponse = trim($aiResponse);

            Log::info('AI answer generated', [
                'question' => $question,
                'answer' => $aiResponse
            ]);

            return $aiResponse;

        } catch (\Exception $e) {
            Log::error('AI Game Service error', [
                'error' => $e->getMessage(),
                'question' => $question
            ]);
            
            // Return a fallback answer
            return "I don't know";
        }
    }



    public function submitAIAnswers($gameId, $userId, array $answers, $sessionId)
    {
        Log::info('AI Service submitAIAnswers called', [
            'gameId' => $gameId,
            'userId' => $userId,
            'sessionId' => $sessionId,
            'answersCount' => count($answers)
        ]);

        // Check if AI answers for this session already exist
        $existingAIScore = AIScore::where('game_id', $gameId)
            ->where('session_id', $sessionId)
            ->first();

        if ($existingAIScore) {
            Log::info('AI answers already saved for this session', [
                'gameId' => $gameId,
                'sessionId' => $sessionId,
                'existingScoreId' => $existingAIScore->id
            ]);
            return $sessionId; // Return early, don't save duplicate
        }

        $game = Games::findOrFail($gameId);
        $gameQuestions = $game->gameType->gameQuestions()->get();

        $answerJson = [];
        $totalScore = 0;

        foreach ($gameQuestions as $index => $question) {
            $submittedAnswer = $answers[$index] ?? null;
            $isCorrect = $submittedAnswer !== null && strtolower(trim($submittedAnswer)) === strtolower(trim($question->answer));
            $scoreAwarded = $isCorrect ? ($question->score_awarded ?? 0) : 0;

            $answerJson[$question->id] = [
                'question_number' => $index + 1,
                'question' => $question->question,
                'submitted' => $submittedAnswer,
                'correct_answer' => $question->answer,
                'is_correct' => $isCorrect,
                'score_awarded' => $scoreAwarded
            ];

            $totalScore += $scoreAwarded;
        }

        $aiScore = AIScore::create([
            'game_id' => $game->id,
            'answer_json' => json_encode($answerJson),
            'session_id' => $sessionId,
            'score' => $totalScore,
        ]);

        Log::info('AI answers saved successfully', [
            'gameId' => $gameId,
            'sessionId' => $sessionId,
            'aiScoreId' => $aiScore->id,
            'totalScore' => $totalScore
        ]);

        return $sessionId;
    }
}