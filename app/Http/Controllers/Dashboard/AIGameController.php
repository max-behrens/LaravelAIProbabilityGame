<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Games;
use App\Models\AIScore;
use App\Services\Dashboard\AIGameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIGameController extends Controller
{
    protected $aiGameService;

    public function __construct(AIGameService $aiGameService)
    {
        $this->aiGameService = $aiGameService;
    }

    /**
     * Get AI scores for room.
     */
    public function getAIScores($gameId, Request $request)
    {
        $page = $request->query('page', 1);
        // Get filter parameters from the request
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $excludeAI = $request->get('exclude_ai', 'true') === 'true';
        $difficultyId = $request->get('difficulty');
        $categoryId = $request->get('category');
        
        // Get sorting parameters
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
    
        // Pass difficulty and category IDs to the service method
        $aiScores = $this->aiGameService->getAIGameScores(
            $gameId,
            $page,
            $startDate,
            $endDate,
            $excludeAI,
            $difficultyId,  
            $categoryId,
            5, // perPage
            $sortField,
            $sortDirection
        );

        Log::info('AI Game scores retrieved', ['scores' => $aiScores]);
        return response()->json($aiScores);
    }

    /**
     * Get AI answer for a specific question in a game
     */
    public function getAIAnswer(Request $request)
    {
        $request->validate([
            'gameId' => 'required|integer|exists:games,id',
            'questionIndex' => 'required|integer|min:0'
        ]);

        $gameId = $request->input('gameId');
        $questionIndex = $request->input('questionIndex');
        $difficultyId = $request->input('difficultyId');
        $categoryId = $request->input('categoryId');

        Log::info('AI Answer requested', [
            'gameId' => $gameId,
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

                Log::info('Retrieved questions', ['questions' => $gameQuestions->toArray()]);

            // Validate question index
            if ($questionIndex >= $gameQuestions->count()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid question index'
                ], 400);
            }

            $question = $gameQuestions[$questionIndex];

            // Get AI answer from service
            $aiAnswer = $this->aiGameService->getAIAnswerForQuestion($question->question);

            // Generate session ID
            $sessionId = Str::uuid()->toString();

            // Calculate score (check if AI answer is correct)
            // Clean both answers for comparison
            $aiAnswerCleaned = strtolower(trim($aiAnswer));
            $correctAnswerCleaned = strtolower(trim($question->answer));
            
            // Remove punctuation from both strings for more flexible comparison
            $aiAnswerCleaned = preg_replace('/[^\p{L}\p{N}\s]/u', '', $aiAnswerCleaned);
            $correctAnswerCleaned = preg_replace('/[^\p{L}\p{N}\s]/u', '', $correctAnswerCleaned);
            
            // Check if the correct answer appears anywhere within the AI's answer
            $isCorrect = strpos($aiAnswerCleaned, $correctAnswerCleaned) !== false;
            $scoreAwarded = $isCorrect ? ($question->score_awarded ?? 0) : 0;

            return response()->json([
                'success' => true,
                'answer' => $aiAnswer,
                'score' => $scoreAwarded,
                'isCorrect' => $isCorrect,
                'cached' => false
            ]);

        } catch (\Exception $e) {
            Log::error('AI Answer error', [
                'error' => $e->getMessage(),
                'gameId' => $gameId,
                'questionIndex' => $questionIndex
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get AI answer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all AI answers for a game
     */
    public function getGameAIAnswers($gameId)
    {
        try {
            $aiAnswers = AIScore::where('game_id', $gameId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'answers' => $aiAnswers
            ]);

        } catch (\Exception $e) {
            Log::error('Get game AI answers error', [
                'error' => $e->getMessage(),
                'gameId' => $gameId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get AI answers'
            ], 500);
        }
    }
}