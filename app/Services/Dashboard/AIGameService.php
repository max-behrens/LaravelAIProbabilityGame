<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AIScore;
use App\Models\Games;
use App\Models\GameType;
use App\Models\GameQuestion;
use App\Services\Dashboard\GamesService;
use Illuminate\Support\Facades\DB;

class AIGameService
{

    protected $gamesService;

    public function __construct(GamesService $gamesService)
    {
        $this->gamesService = $gamesService;
    }


    public function getAIGameScores(
        $gameId, 
        $page = 1, 
        ?string $startDate = null, 
        ?string $endDate = null, 
        bool $excludeAI = true, 
        ?int $difficultyId = null, 
        ?int $categoryId = null, 
        $perPage = 5,
        string $sortField = 'created_at',
        string $sortDirection = 'desc')
    {
        if ($excludeAI) {
            return null;
        }

        Log::debug('Fetching AI scores', [
            'gameId' => $gameId, 
            'page' => $page, 
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection
        ]);

        $query = AIScore::query()
            ->where('ai_scores.game_id', $gameId)
            ->select(
                'ai_scores.id',
                'ai_scores.session_id',
                'ai_scores.game_id',
                'ai_scores.score',
                'ai_scores.created_at',
                'ai_scores.answer_json'
            );

        if ($startDate && $endDate) {
            $query->whereBetween('ai_scores.created_at', [$startDate, $endDate]);
        }

        if ($difficultyId !== null) {
            Log::debug('Applying difficulty filter', ['difficultyId' => $difficultyId]);
            $query->where(function($query) use ($difficultyId) {
                // Try regular JSON extraction first
                $query->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(ai_scores.answer_json, "$.difficulty_id")) = ?', [(string)$difficultyId])
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(ai_scores.answer_json, "$.difficulty_id")) = ?', [(int)$difficultyId])
                    ->orWhereRaw('CAST(JSON_UNQUOTE(JSON_EXTRACT(ai_scores.answer_json, "$.difficulty_id")) AS UNSIGNED) = ?', [(int)$difficultyId])
                    // Try double-encoded JSON extraction
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(ai_scores.answer_json), "$.difficulty_id")) = ?', [(string)$difficultyId])
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(ai_scores.answer_json), "$.difficulty_id")) = ?', [(int)$difficultyId])
                    ->orWhereRaw('CAST(JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(ai_scores.answer_json), "$.difficulty_id")) AS UNSIGNED) = ?', [(int)$difficultyId]);
            });
        }

        if ($categoryId !== null) {
            Log::debug('Applying category filter', ['categoryId' => $categoryId]);
            $query->where(function($query) use ($categoryId) {
                // Try regular JSON extraction first
                $query->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(ai_scores.answer_json, "$.category_id")) = ?', [(string)$categoryId])
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(ai_scores.answer_json, "$.category_id")) = ?', [(int)$categoryId])
                    ->orWhereRaw('CAST(JSON_UNQUOTE(JSON_EXTRACT(ai_scores.answer_json, "$.category_id")) AS UNSIGNED) = ?', [(int)$categoryId])
                    // Try double-encoded JSON extraction
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(ai_scores.answer_json), "$.category_id")) = ?', [(string)$categoryId])
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(ai_scores.answer_json), "$.category_id")) = ?', [(int)$categoryId])
                    ->orWhereRaw('CAST(JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(ai_scores.answer_json), "$.category_id")) AS UNSIGNED) = ?', [(int)$categoryId]);
            });
        }

        // Apply sorting
        $validSortFields = ['score', 'created_at'];
        $validSortDirections = ['asc', 'desc'];
        
        if (in_array($sortField, $validSortFields) && in_array($sortDirection, $validSortDirections)) {
            $query->orderBy('ai_scores.' . $sortField, $sortDirection);
        } else {
            // Default sorting
            $query->orderBy('ai_scores.created_at', 'desc');
        }

        $scores = $query->paginate($perPage, ['*'], 'page', $page);

        Log::debug('Fetched AI scores raw', ['scores' => $scores->items()]);

        // Get all unique difficulty and category IDs from the scores
        $difficultyIds = [];
        $categoryIds = [];
        
        foreach ($scores->items() as $score) {
            $answerData = is_string($score->answer_json) ? json_decode($score->answer_json, true) : $score->answer_json;
            if (isset($answerData['difficulty_id'])) {
                $difficultyIds[] = $answerData['difficulty_id'];
            }
            if (isset($answerData['category_id'])) {
                $categoryIds[] = $answerData['category_id'];
            }
        }

        // Fetch difficulty and category names in bulk
        $difficulties = collect();
        $categories = collect();
        
        if (!empty($difficultyIds)) {
            $difficulties = \DB::table('game_type_difficulties')
                ->whereIn('id', array_unique($difficultyIds))
                ->pluck('name', 'id');
        }
        
        if (!empty($categoryIds)) {
            $categories = \DB::table('game_type_categories')
                ->whereIn('id', array_unique($categoryIds))
                ->pluck('name', 'id');
        }

        Log::debug('AI Difficulties and Categories', [
            'difficulties' => $difficulties->toArray(),
            'categories' => $categories->toArray()
        ]);

        $scores->getCollection()->transform(function ($score) use ($difficulties, $categories, $gameId) {
            if ($score->answer_json) {
                $answerData = is_string($score->answer_json) ? json_decode($score->answer_json, true) : $score->answer_json;

                // Use the fetched names or fall back to ID-based names
                if (isset($answerData['difficulty_id'])) {
                    $answerData['difficulty_name'] = $difficulties->get($answerData['difficulty_id']) 
                        ?? 'Difficulty #' . $answerData['difficulty_id'];
                } else {
                    $answerData['difficulty_name'] = 'N/A';
                }

                if (isset($answerData['category_id'])) {
                    $answerData['category_name'] = $categories->get($answerData['category_id']) 
                        ?? 'Category #' . $answerData['category_id'];
                } else {
                    $answerData['category_name'] = 'N/A';
                }

                 // Add max score based on difficulty
                if (isset($answerData['difficulty_id'])) {
                    $difficultyId = (int)$answerData['difficulty_id'];
                    $totalScores = $this->gamesService->totalScore($gameId, null, 1);
                    switch ($difficultyId) {
                        case 1:
                            $answerData['max_score'] = $totalScores['totalEasy'];
                            break;
                        case 2:
                            $answerData['max_score'] = $totalScores['totalMedium'];
                            break;
                        case 3:
                            $answerData['max_score'] = $totalScores['totalDifficult'];
                            break;
                        default:
                            $answerData['max_score'] = $totalScores['totalEasy']; // fallback
                    }
                } else {
                    $answerData['max_score'] = $totalScores['totalEasy']; // fallback
                }

                $score->answer_json = $answerData;
            }

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



    public function submitAIAnswers($gameId, $userId, array $answers, $sessionId, $difficultyId = null, $categoryId = null, $isTeamLeader = null, $teamGameType = '')
    {
        Log::info('AI Service submitAIAnswers called', [
            'gameId' => $gameId,
            'userId' => $userId,
            'sessionId' => $sessionId,
            'answersCount' => count($answers),
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId
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
    
        // Build query for game questions with difficulty and category filters
        $gameQuestionsQuery = $game->gameType->gameQuestions();
    
        if ($difficultyId !== null) {
            $gameQuestionsQuery->where('difficulty_id', $difficultyId);
        }
        if ($categoryId !== null) {
            $gameQuestionsQuery->where('category_id', $categoryId);
        }
    
        $gameQuestions = $gameQuestionsQuery->get();
        $answerJson = [];

        // Add difficulty_id and category_id to answer_json for reference
        if ($difficultyId !== null) {
            $answerJson['difficulty_id'] = $difficultyId;
        }
        if ($categoryId !== null) {
            $answerJson['category_id'] = $categoryId;
        }

        if ($teamGameType && $teamGameType !== '') {
            $answerJson['team_game_type'] = $teamGameType;
        }

        if ($isTeamLeader !== null) {
            $answerJson['is_team_leader'] = $isTeamLeader;
        }
        
        $totalScore = 0;
        foreach ($gameQuestions as $index => $question) {
            $submittedAnswer = $answers[$index] ?? null;
            $isCorrect = false;
            $submittedAnswer = $answers[$index] ?? null;
            if ($submittedAnswer !== null) {
                // Trim whitespace and convert to lowercase
                $submittedAnswerCleaned = strtolower(trim($submittedAnswer));
                $correctAnswerCleaned = strtolower(trim($question->answer));
                // Remove punctuation from both strings for a more flexible comparison
                $submittedAnswerCleaned = preg_replace('/[^\p{L}\p{N}\s]/u', '', $submittedAnswerCleaned);
                $correctAnswerCleaned = preg_replace('/[^\p{L}\p{N}\s]/u', '', $correctAnswerCleaned);
                
                // Check if the correct answer appears anywhere within the AI's answer
                $isCorrect = strpos($submittedAnswerCleaned, $correctAnswerCleaned) !== false;
                $steal = ($submittedAnswerCleaned === 'STEAL') ? true : false; 
            }
            $scoreAwarded = $isCorrect ? ($question->score_awarded ?? 0) : 0;

            $answerJson[$question->id] = [
                'question_number' => $index + 1,
                'question' => $question->question,
                'submitted' => $submittedAnswer,
                'correct_answer' => $question->answer,
                'is_correct' => $isCorrect,
                'steal' => $steal,
                'score_awarded' => $scoreAwarded,
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
            'answerJson' => $answerJson,
            'sessionId' => $sessionId,
            'aiScoreId' => $aiScore->id,
            'totalScore' => $totalScore,
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId
        ]);
        return $sessionId;
    }
}