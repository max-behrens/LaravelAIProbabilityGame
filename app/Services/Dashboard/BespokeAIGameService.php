<?php
// app/Services/Dashboard/BespokeAIGameService.php

namespace App\Services\Dashboard;

use App\Models\BespokeAIModel;
use App\Models\BespokeAIScore;
use App\Models\BespokeAITrainingData;
use App\Models\BespokeAIPerformance;
use App\Models\Games;
use App\Models\GameQuestion;
use App\Services\Dashboard\GamesService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class BespokeAIGameService
{
    protected $gamesService;
    
    public function __construct(GamesService $gamesService)
    {
        $this->gamesService = $gamesService;
    }

    /**
     * Get available AI models
     */
    public function getAvailableModels()
    {
        return BespokeAIModel::getActiveModels();
    }

    public function getBespokeAIGameScores(
        $gameId, 
        $page = 1, 
        ?string $startDate = null, 
        ?string $endDate = null, 
        bool $excludeAI = true, 
        ?int $difficultyId = null, 
        ?int $categoryId = null, 
        string $sortField = 'created_at',
        string $sortDirection = 'desc')
    {
        if ($excludeAI) {
            return collect(); // Return empty collection instead of null
        }

        Log::debug('Fetching Bespoke AI scores', [
            'gameId' => $gameId, 
            'page' => $page, 
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection
        ]);

        $query = BespokeAIScore::query()
            ->where('bespoke_ai_scores.game_id', $gameId)
            ->select(
                'bespoke_ai_scores.id',
                'bespoke_ai_scores.session_id',
                'bespoke_ai_scores.game_id',
                'bespoke_ai_scores.score',
                'bespoke_ai_scores.created_at',
                'bespoke_ai_scores.answer_json'
            );

        if ($startDate && $endDate) {
            $query->whereBetween('bespoke_ai_scores.created_at', [$startDate, $endDate]);
        }

        if ($difficultyId !== null) {
            Log::debug('Applying difficulty filter', ['difficultyId' => $difficultyId]);
            $query->where(function($query) use ($difficultyId) {
                $query->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(bespoke_ai_scores.answer_json, "$.difficulty_id")) = ?', [(string)$difficultyId])
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(bespoke_ai_scores.answer_json, "$.difficulty_id")) = ?', [(int)$difficultyId])
                    ->orWhereRaw('CAST(JSON_UNQUOTE(JSON_EXTRACT(bespoke_ai_scores.answer_json, "$.difficulty_id")) AS UNSIGNED) = ?', [(int)$difficultyId])
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(bespoke_ai_scores.answer_json), "$.difficulty_id")) = ?', [(string)$difficultyId])
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(bespoke_ai_scores.answer_json), "$.difficulty_id")) = ?', [(int)$difficultyId])
                    ->orWhereRaw('CAST(JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(bespoke_ai_scores.answer_json), "$.difficulty_id")) AS UNSIGNED) = ?', [(int)$difficultyId]);
            });
        }

        if ($categoryId !== null) {
            Log::debug('Applying category filter', ['categoryId' => $categoryId]);
            $query->where(function($query) use ($categoryId) {
                $query->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(bespoke_ai_scores.answer_json, "$.category_id")) = ?', [(string)$categoryId])
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(bespoke_ai_scores.answer_json, "$.category_id")) = ?', [(int)$categoryId])
                    ->orWhereRaw('CAST(JSON_UNQUOTE(JSON_EXTRACT(bespoke_ai_scores.answer_json, "$.category_id")) AS UNSIGNED) = ?', [(int)$categoryId])
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(bespoke_ai_scores.answer_json), "$.category_id")) = ?', [(string)$categoryId])
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(bespoke_ai_scores.answer_json), "$.category_id")) = ?', [(int)$categoryId])
                    ->orWhereRaw('CAST(JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(bespoke_ai_scores.answer_json), "$.category_id")) AS UNSIGNED) = ?', [(int)$categoryId]);
            });
        }

        // Get all results (no pagination at this level)
        $scores = $query
            ->orderBy($sortField, $sortDirection)
            ->get();

        return $this->transformAIScoresToCollection($scores, $gameId);
    }

    private function transformAIScoresToCollection($scores, $gameId)
    {
        $difficultyIds = [];
        $categoryIds = [];

        foreach ($scores as $score) {
            $answerData = is_string($score->answer_json) ? json_decode($score->answer_json, true) : $score->answer_json;
            if (isset($answerData['difficulty_id'])) {
                $difficultyIds[] = $answerData['difficulty_id'];
            }
            if (isset($answerData['category_id'])) {
                $categoryIds[] = $answerData['category_id'];
            }
        }

        $difficulties = !empty($difficultyIds)
            ? \DB::table('game_type_difficulties')->whereIn('id', array_unique($difficultyIds))->pluck('name', 'id')
            : collect();

        $categories = !empty($categoryIds)
            ? \DB::table('game_type_categories')->whereIn('id', array_unique($categoryIds))->pluck('name', 'id')
            : collect();

        // Transform the collection directly without pagination wrapper
        return $scores->filter(function($score) {
            return $score !== null && isset($score->id);
        })->map(function ($score) use ($difficulties, $categories, $gameId) {
            $answerData = $score->answer_json;

            if (is_string($answerData)) {
                $decoded = json_decode($answerData, true);
                $answerData = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
            } elseif (is_object($answerData)) {
                $answerData = (array) $answerData;
            } elseif (!is_array($answerData)) {
                $answerData = [];
            }

            $answerData['difficulty_name'] = isset($answerData['difficulty_id'])
                ? $difficulties->get($answerData['difficulty_id'], 'Difficulty #'.$answerData['difficulty_id'])
                : 'N/A';

            $answerData['category_name'] = isset($answerData['category_id'])
                ? $categories->get($answerData['category_id'], 'Category #'.$answerData['category_id'])
                : 'N/A';

            $totalScores = $this->gamesService->totalScore($gameId, null, 1);
            if (isset($answerData['difficulty_id'])) {
                switch ((int) $answerData['difficulty_id']) {
                    case 1: $answerData['max_score'] = $totalScores['totalEasy']; break;
                    case 2: $answerData['max_score'] = $totalScores['totalMedium']; break;
                    case 3: $answerData['max_score'] = $totalScores['totalDifficult']; break;
                    default: $answerData['max_score'] = $totalScores['totalEasy'];
                }
            } else {
                $answerData['max_score'] = $totalScores['totalEasy'];
            }

            $score->answer_json = $answerData;
            $score->model_id = 1;
            return $score;
        });
    }


    /**
     * Get AI answer for a specific question using bespoke AI model
     */
    public function getBespokeAIAnswerForQuestion($gameId, $modelId, $questionIndex, $questionText, $playerAnswer = '', $difficultyId = null, $categoryId = null)
    {
        Log::info('Bespoke AI Answer requested', [
            'gameId' => $gameId,
            'modelId' => $modelId,
            'questionIndex' => $questionIndex,
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId
        ]);

        try {
            // Get game and questions
            $game = Games::findOrFail($gameId);
            $gameQuestions = $game->gameType->gameQuestions()
                ->where('difficulty_id', $difficultyId)
                ->where('category_id', $categoryId)
                ->get();

            // Validate question index
            if ($questionIndex >= $gameQuestions->count()) {
                throw new \Exception('Invalid question index');
            }

            $question = $gameQuestions[$questionIndex];

            // Get difficulty and category names
            $difficultyName = '';
            $categoryName = '';
            
            if ($difficultyId) {
                $difficulty = \DB::table('game_type_difficulties')->where('id', $difficultyId)->first();
                $difficultyName = $difficulty ? $difficulty->name : '';
            }
            
            if ($categoryId) {
                $category = \DB::table('game_type_categories')->where('id', $categoryId)->first();
                $categoryName = $category ? $category->name : '';
            }

            // Call Python AI model
            $pythonScript = base_path('python/bespoke_ai_model.py');
            $maxScore = $question->score_awarded ?? 1;

            $command = [
                'python3',
                $pythonScript,
                'predict',
                '--model-id', (string)$modelId,
                '--question', $questionText,
                '--player-answer', $playerAnswer,
                '--difficulty', $difficultyName,
                '--category', $categoryName,
                '--max-score', (string)$maxScore
            ];

            $result = Process::run($command);

            if (!$result->successful()) {
                Log::error('Python AI process failed', [
                    'command' => implode(' ', $command),
                    'output' => $result->output(),
                    'errorOutput' => $result->errorOutput()
                ]);
                throw new \Exception('AI model execution failed: ' . $result->errorOutput());
            }

            $aiResponse = json_decode($result->output(), true);

            if (!$aiResponse || !isset($aiResponse['answer'])) {
                throw new \Exception('Invalid AI response format');
            }

            $aiAnswer = $aiResponse['answer'];
            $predictedScore = $aiResponse['predicted_score'] ?? $maxScore * 0.5;

            // Calculate actual score by checking if answer is correct
            $aiAnswerCleaned = strtolower(trim($aiAnswer));
            $correctAnswerCleaned = strtolower(trim($question->answer));
            
            $aiAnswerCleaned = preg_replace('/[^\p{L}\p{N}\s]/u', '', $aiAnswerCleaned);
            $correctAnswerCleaned = preg_replace('/[^\p{L}\p{N}\s]/u', '', $correctAnswerCleaned);
            
            $isCorrect = strpos($aiAnswerCleaned, $correctAnswerCleaned) !== false;
            $scoreAwarded = $isCorrect ? $maxScore : 0;

            // Save training data for future learning
            $this->saveTrainingData(
                $modelId,
                $gameId,
                $question->id,
                $questionText,
                $question->answer,
                $playerAnswer,
                $aiAnswer,
                $scoreAwarded,
                $maxScore,
                $difficultyId,
                $categoryId
            );

            return [
                'success' => true,
                'answer' => $aiAnswer,
                'score' => $scoreAwarded,
                'predicted_score' => $predictedScore,
                'isCorrect' => $isCorrect,
                'cached' => false,
                'model_id' => $modelId
            ];

        } catch (\Exception $e) {
            Log::error('Bespoke AI Answer error', [
                'error' => $e->getMessage(),
                'gameId' => $gameId,
                'modelId' => $modelId,
                'questionIndex' => $questionIndex
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get bespoke AI answer: ' . $e->getMessage(),
                'answer' => 'I need more training data',
                'score' => 0,
                'isCorrect' => false
            ];
        }
    }

    /**
     * Submit bespoke AI answers for a complete game
     */
    public function submitBespokeAIAnswers($gameId, $modelId, $userId, array $answers, $sessionId, $difficultyId = null, $categoryId = null)
    {
        Log::info('Bespoke AI Service submitBespokeAIAnswers called', [
            'gameId' => $gameId,
            'modelId' => $modelId,
            'userId' => $userId,
            'sessionId' => $sessionId,
            'answersCount' => count($answers),
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId
        ]);

        // Check if answers already exist for this session
        $existingScore = BespokeAIScore::where('game_id', $gameId)
            ->where('model_id', $modelId)
            ->where('session_id', $sessionId)
            ->first();

        if ($existingScore) {
            Log::info('Bespoke AI answers already saved for this session', [
                'gameId' => $gameId,
                'modelId' => $modelId,
                'sessionId' => $sessionId,
                'existingScoreId' => $existingScore->id
            ]);
            return $sessionId;
        }

        $game = Games::findOrFail($gameId);

        // Build query for game questions
        $gameQuestionsQuery = $game->gameType->gameQuestions();
        
        if ($difficultyId !== null) {
            $gameQuestionsQuery->where('difficulty_id', $difficultyId);
        }
        if ($categoryId !== null) {
            $gameQuestionsQuery->where('category_id', $categoryId);
        }

        $gameQuestions = $gameQuestionsQuery->get();
        $answerJson = [];

        // Add metadata
        if ($difficultyId !== null) {
            $answerJson['difficulty_id'] = $difficultyId;
        }
        if ($categoryId !== null) {
            $answerJson['category_id'] = $categoryId;
        }
        
        $answerJson['model_id'] = $modelId;

        $totalScore = 0;
        $correctAnswers = 0;

        foreach ($gameQuestions as $index => $question) {
            $submittedAnswer = $answers[$index] ?? null;
            $isCorrect = false;

            if ($submittedAnswer !== null) {
                $submittedAnswerCleaned = strtolower(trim($submittedAnswer));
                $correctAnswerCleaned = strtolower(trim($question->answer));
                
                $submittedAnswerCleaned = preg_replace('/[^\p{L}\p{N}\s]/u', '', $submittedAnswerCleaned);
                $correctAnswerCleaned = preg_replace('/[^\p{L}\p{N}\s]/u', '', $correctAnswerCleaned);
                
                $isCorrect = strpos($submittedAnswerCleaned, $correctAnswerCleaned) !== false;
            }

            $scoreAwarded = $isCorrect ? ($question->score_awarded ?? 0) : 0;

            if ($isCorrect) {
                $correctAnswers++;
            }

            $answerJson[$question->id] = [
                'question_number' => $index + 1,
                'question' => $question->question,
                'submitted' => $submittedAnswer,
                'correct_answer' => $question->answer,
                'is_correct' => $isCorrect,
                'score_awarded' => $scoreAwarded,
            ];

            $totalScore += $scoreAwarded;
        }

        // Calculate max possible score
        $maxPossibleScore = $gameQuestions->sum('score_awarded');

        // Save bespoke AI score
        $bespokeAIScore = BespokeAIScore::create([
            'game_id' => $game->id,
            'model_id' => $modelId,
            'answer_json' => $answerJson,
            'session_id' => $sessionId,
            'score' => $totalScore,
        ]);

        // // Save performance data
        // $accuracyPercentage = $gameQuestions->count() > 0 ? ($correctAnswers / $gameQuestions->count()) * 100 : 0;
        
        // BespokeAIPerformance::create([
        //     'model_id' => $modelId,
        //     'game_id' => $game->id,
        //     'session_id' => $sessionId,
        //     'total_questions' => $gameQuestions->count(),
        //     'correct_answers' => $correctAnswers,
        //     'total_score' => $totalScore,
        //     'max_possible_score' => $maxPossibleScore,
        //     'accuracy_percentage' => $accuracyPercentage,
        //     'improvement_from_baseline' => 0 // TODO: Calculate improvement
        // ]);

        // // Trigger model retraining (async)
        // $this->triggerModelRetraining($modelId);

        // Log::info('Bespoke AI answers saved successfully', [
        //     'gameId' => $gameId,
        //     'modelId' => $modelId,
        //     'sessionId' => $sessionId,
        //     'bespokeAIScoreId' => $bespokeAIScore->id,
        //     'totalScore' => $totalScore,
        //     'maxPossibleScore' => $maxPossibleScore,
        //     'accuracy' => $accuracyPercentage
        // ]);

        return $sessionId;
    }

    /**
     * Save training data for future model improvement
     */
    protected function saveTrainingData($modelId, $gameId, $questionId, $questionText, $correctAnswer, $playerAnswer, $aiAnswer, $scoreAchieved, $maxPossibleScore, $difficultyId = null, $categoryId = null)
    {
        BespokeAITrainingData::create([
            'model_id' => $modelId,
            'game_id' => $gameId,
            'question_id' => $questionId,
            'question_text' => $questionText,
            'correct_answer' => $correctAnswer,
            'player_answer' => $playerAnswer,
            'ai_answer' => $aiAnswer,
            'score_achieved' => $scoreAchieved,
            'max_possible_score' => $maxPossibleScore,
            'difficulty_id' => $difficultyId,
            'category_id' => $categoryId,
            'context_data' => [
                'timestamp' => now()->toISOString(),
                'player_had_answer' => !empty($playerAnswer)
            ]
        ]);
    }

    /**
     * Trigger model retraining (can be made async with queues)
     */
    protected function triggerModelRetraining($modelId)
    {
        try {
            $pythonScript = base_path('python/bespoke_ai_model.py');
            
            $command = [
                'python3',
                $pythonScript,
                'train',
                '--model-id', (string)$modelId
            ];

            // Run in background (you might want to use Laravel queues for this)
            Process::run($command);

            Log::info("Model retraining triggered for model {$modelId}");
        } catch (\Exception $e) {
            Log::error("Failed to trigger model retraining for model {$modelId}", [
                'error' => $e->getMessage()
            ]);
        }
    }

}