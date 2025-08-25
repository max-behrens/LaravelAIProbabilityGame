<?php
// app/Http/Controllers/Dashboard/BespokeAIGameController.php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BespokeAIModel;
use App\Models\BespokeAIScore;
use App\Services\Dashboard\BespokeAIGameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BespokeAIGameController extends Controller
{
    protected $bespokeAIGameService;

    public function __construct(BespokeAIGameService $bespokeAIGameService)
    {
        $this->bespokeAIGameService = $bespokeAIGameService;
    }

    /**
     * Get available bespoke AI models
     */
    public function getAvailableModels()
    {
        try {
            $models = $this->bespokeAIGameService->getAvailableModels();
            
            return response()->json([
                'success' => true,
                'models' => $models
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get available models', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load available models'
            ], 500);
        }
    }

    /**
     * Get bespoke AI scores for a game room
     */
    public function getBespokeAIScores($gameId, Request $request)
    {
        $page = $request->query('page', 1);
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $excludeBespokeAI = $request->get('exclude_bespoke_ai', 'false') === 'true';
        $difficultyId = $request->get('difficulty');
        $categoryId = $request->get('category');
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        try {
            $bespokeAIScores = $this->bespokeAIGameService->getBespokeAIGameScores(
                $gameId,
                $page,
                $startDate,
                $endDate,
                $excludeBespokeAI,
                $difficultyId,
                $categoryId,
                5, // perPage
                $sortField,
                $sortDirection
            );

            Log::info('Bespoke AI scores retrieved', ['scores' => $bespokeAIScores]);
            return response()->json($bespokeAIScores);
        } catch (\Exception $e) {
            Log::error('Failed to get bespoke AI scores', [
                'error' => $e->getMessage(),
                'gameId' => $gameId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load bespoke AI scores'
            ], 500);
        }
    }

    /**
     * Get bespoke AI answer for a specific question
     */
    public function getBespokeAIAnswer(Request $request)
    {
        $request->validate([
            'gameId' => 'required|integer|exists:games,id',
            'modelId' => 'required|integer|exists:bespoke_ai_models,id',
            'questionIndex' => 'required|integer|min:0',
            'questionText' => 'required|string',
            'playerAnswer' => 'sometimes|string',
            'difficultyId' => 'sometimes|integer',
            'categoryId' => 'sometimes|integer'
        ]);

        $gameId = $request->input('gameId');
        $modelId = $request->input('modelId');
        $questionIndex = $request->input('questionIndex');
        $questionText = $request->input('questionText');
        $playerAnswer = $request->input('playerAnswer', '');
        $difficultyId = $request->input('difficultyId');
        $categoryId = $request->input('categoryId');

        Log::info('Bespoke AI Answer requested', [
            'gameId' => $gameId,
            'modelId' => $modelId,
            'questionIndex' => $questionIndex,
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId
        ]);

        try {
            $result = $this->bespokeAIGameService->getBespokeAIAnswerForQuestion(
                $gameId,
                $modelId,
                $questionIndex,
                $questionText,
                $playerAnswer,
                $difficultyId,
                $categoryId
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Bespoke AI Answer error', [
                'error' => $e->getMessage(),
                'gameId' => $gameId,
                'modelId' => $modelId,
                'questionIndex' => $questionIndex
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get bespoke AI answer: ' . $e->getMessage(),
                'answer' => 'I need more training data',
                'score' => 0,
                'isCorrect' => false
            ], 500);
        }
    }

    /**
     * Handle steal functionality
     */
    public function handleSteal(Request $request, $gameId)
    {
        $request->validate([
            'targetPlayerId' => 'required|integer',
            'questionIndex' => 'required|integer|min:0'
        ]);

        $userId = $request->user()->id;
        $targetPlayerId = $request->input('targetPlayerId');
        $questionIndex = $request->input('questionIndex');

        try {
            $result = $this->bespokeAIGameService->handleSteal(
                $gameId,
                $userId,
                $targetPlayerId,
                $questionIndex
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Steal handling error', [
                'error' => $e->getMessage(),
                'gameId' => $gameId,
                'userId' => $userId,
                'targetPlayerId' => $targetPlayerId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process steal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bespoke AI performance statistics
     */
    public function getPerformanceStats($gameId, $modelId)
    {
        try {
            $stats = BespokeAIScore::where('game_id', $gameId)
                ->where('model_id', $modelId)
                ->selectRaw('
                    COUNT(*) as total_games,
                    AVG(score) as average_score,
                    MAX(score) as best_score,
                    MIN(score) as worst_score
                ')
                ->first();

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get performance stats', [
                'error' => $e->getMessage(),
                'gameId' => $gameId,
                'modelId' => $modelId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load performance statistics'
            ], 500);
        }
    }
}