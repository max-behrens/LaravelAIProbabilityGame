<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Games;
use App\Models\User;
use App\Models\GameScore;
use App\Models\GameType;
use App\Models\GameTypeDifficulty;
use App\Models\GameTypeCategory;
use App\Events\PlayerReady;
use Illuminate\Support\Facades\Cache;
use App\Events\GameStatusUpdated;
use App\Services\Dashboard\GamesService;
use App\Services\Dashboard\AIGameService;
use App\Services\Dashboard\BespokeAIGameService;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;



class GamesController extends Controller
{
    protected $gamesService;
    protected $bespokeAIGameService;

    public function __construct(GamesService $gamesService, AIGameService $aiGameService, BespokeAIGameService $bespokeAIGameService)
    {
        $this->gamesService = $gamesService;
        $this->aiGameService = $aiGameService;
        $this->bespokeAIGameService = $bespokeAIGameService;
    }

    public function index(Request $request)
    {
        Log::info('Fetching filtered and paginated games list...', $request->all());

        $page = $request->query('page', 1);
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $userIds = $request->get('user_ids');
        $andUsers = $request->get('and_users', 'false') === 'true';
        $gameType = $request->get('game_type');
        $perPage = $request->get('per_page', 5); // Allow customizable per page

        // Convert user IDs from comma-separated string to array
        $userIds = $userIds ? explode(',', $userIds) : null;

        // Get paginated games directly from service
        $games = $this->gamesService->getIndexGames($page, $startDate, $endDate, $userIds, $andUsers, $gameType, $perPage);


        return response()->json($games);
    }


    public function show($gameId)
    {
        Log::info('Fetching game details', ['gameId' => $gameId]);
        $game = Games::with('users')->find($gameId);

        if (!$game) {
            Log::warning('Game not found');
            return response()->json(['message' => 'Game not found'], 404);
        }

        return response()->json($game);
    }

    public function getScores($gameId, Request $request)
    {
        $page = $request->query('page', 1);
        // Get filter parameters from the request
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $userIds = $request->get('user_ids');
        $andUsers = $request->get('and_users', 'false') === 'true';
        $difficultyId = $request->get('difficulty');
        $categoryId = $request->get('category');
        
        // Get sorting parameters
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // The user IDs come as a comma-separated string, convert them to an array
        $userIds = $userIds ? explode(',', $userIds) : null;
        
        // Pass difficulty and category IDs to the service method
        $gameScores = $this->gamesService->getGameScores(
            $gameId,
            $page,
            $startDate,
            $endDate,
            $userIds,
            $andUsers,
            $difficultyId,
            $categoryId,
            5, // perPage
            $sortField,
            $sortDirection
        );
        
        Log::info('Game scores retrieved', ['scores' => $gameScores]);
        return response()->json($gameScores);
    }


    public function getGameTypes()
    {
        $gameTypes = GameType::orderBy('id')->get(['id', 'name']);

        return response()->json([
            'data' => $gameTypes
        ]);
    }



    public function showRoom($gameId, $userId)
    {
        Log::info('Loading game room', ['gameId' => $gameId, 'userId' => $userId]);
        
        $gameDetails = Games::findOrFail($gameId);
        $gameType = $this->gamesService->getGameType($gameDetails);
        $userDetails = User::findOrFail($userId);
        
        // Get all difficulties and categories for dropdowns
        $difficulties = GameTypeDifficulty::orderBy('id')->get(['id', 'name'])->toArray();
        $categories = GameTypeCategory::orderBy('id')->get(['id', 'name'])->toArray();

        Log::info('SHOW ROOM DATA', ['difficulties' => $difficulties,'categories' => $categories]);


        return Inertia::render('Dashboard/AIGame/Room/Index', [
            'gameId' => (int) $gameId,
            'userId' => $userId,
            'game' => $gameDetails,
            'maxPlayers' => $gameDetails->max_players,
            'userDetails' => $userDetails,
            'gameType' => $gameType,
            'difficulties' => $difficulties,
            'categories' => $categories,
            'auth' => ['user' => auth()->user()],
        ]);
    }

    public function showLeaderboard(Request $request)
    {
        $page = $request->query('page', 1);
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $userIds = $request->get('user_ids');
        $andUsers = $request->get('and_users', 'false') === 'true';
        $gameType = $request->get('game_type');
        $perPage = $request->get('per_page', 15);
        $difficultyId = $request->get('difficulty');
        $categoryId = $request->get('category');
        $includeAI = $request->get('include_ai', 'false') === 'true';
        $searchQuery = $request->get('search');
        $sortField = $request->get('sort_field', 'score');
        $sortDirection = $request->get('sort_direction', 'desc');

        // Convert user IDs from comma-separated string to array
        $userIds = $userIds ? explode(',', $userIds) : null;

        // Get paginated leaderboard data from service
        $leaderboardData = $this->gamesService->getLeaderboardGames(
            $page,
            $startDate,
            $endDate,
            $userIds,
            $andUsers,
            $gameType,
            $perPage,
            $difficultyId,
            $categoryId,
            $includeAI,
            $searchQuery,
            $sortField,
            $sortDirection
        );

        // Get all difficulties and categories for dropdowns
        $difficulties = GameTypeDifficulty::orderBy('id')->get(['id', 'name'])->toArray();
        $categories = GameTypeCategory::orderBy('id')->get(['id', 'name'])->toArray();

        Log::info('Leaderboard Data', [
            'data' => $leaderboardData,
            'includeAI' => $includeAI,
            'filters' => [
                'difficulty' => $difficultyId,
                'category' => $categoryId,
                'search' => $searchQuery
            ]
        ]);

        return Inertia::render('Dashboard/AIGame/Leaderboard/Index', [
            'leaderboardData' => $leaderboardData,
            'difficulties' => $difficulties,
            'categories' => $categories,
            'auth' => ['user' => auth()->user()],
            'currentFilters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'user_ids' => $userIds,
                'and_users' => $andUsers,
                'game_type' => $gameType,
                'difficulty' => $difficultyId,
                'category' => $categoryId,
                'include_ai' => $includeAI,
                'search' => $searchQuery,
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection
            ]
        ]);
    }

    // New method to get questions based on difficulty and category
    public function getQuestions($gameId, $difficultyId, $categoryId, Request $request)
    {
        $difficultyId = (int) $difficultyId;
        $categoryId = (int) $categoryId;

        Log::info('Fetching game questions', [
            'gameId' => $gameId,
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId
        ]);
        
        $gameDetails = Games::findOrFail($gameId);
        $gameQuestions = $this->gamesService->getGameQuestionsByDifficultyAndCategory(
            $gameDetails, 
            $difficultyId, 
            $categoryId
        );

        return response()->json([
            'questions' => $gameQuestions
        ]);
    }


    public function submitAnswer(Request $request, $gameId)
    {
        Log::info('Submitting answers', [
            'gameId' => $gameId,
            'userId' => $request->user()?->id,
            'hasAIAnswers' => $request->has('aiAnswers'),
            'hasBespokeAIAnswers' => $request->has('bespokeAIAnswers'),
            'playWithAI' => $request->boolean('playWithAI', false),
            'playWithBespokeAI' => $request->boolean('playWithBespokeAI', false),
            'bespokeAIModelId' => $request->get('bespokeAIModelId'),
            'difficultyId' => $request->get('difficulty_id'),
            'categoryId' => $request->get('category_id'),
            'teamPlayerGame' => $request->boolean('teamPlayerGame', false),
            'teamAIGame' => $request->boolean('teamAIGame', false),
            'isTeamLeader' => $request->boolean('isTeamLeader', false)
        ]);

        $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'nullable|string',
            'aiAnswers' => 'sometimes|array',
            'aiAnswers.*' => 'nullable|string',
            'bespokeAIAnswers' => 'sometimes|array',
            'bespokeAIAnswers.*' => 'nullable|string',
            'playWithAI' => 'sometimes|boolean',
            'playWithBespokeAI' => 'sometimes|boolean',
            'bespokeAIModelId' => 'sometimes|nullable|integer|exists:bespoke_ai_models,id',
            'difficulty_id' => 'sometimes|nullable|integer',
            'category_id' => 'sometimes|nullable|integer',
            'teamPlayerGame' => 'sometimes|boolean',
            'teamAIGame' => 'sometimes|boolean',
            'isTeamLeader' => 'sometimes|boolean'
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get difficulty and category IDs
        $difficultyId = $request->get('difficulty_id');
        $categoryId = $request->get('category_id');

        $teamPlayerGame = $request->boolean('teamPlayerGame', false);
        $teamAIGame = $request->boolean('teamAIGame', false);
        $isTeamLeader = $request->boolean('isTeamLeader', false);

        $teamGameType = '';
        if ($teamPlayerGame) {
            $teamGameType = 'teamPlayerGame';
        } else if ($teamAIGame) {
            $teamGameType = 'teamAIGame';
        }

        if (is_null($difficultyId) || is_null($categoryId)) {
            $settingsKey = "game:{$gameId}:gameSettings";
            $gameSettings = Cache::get($settingsKey);
            
            if ($gameSettings) {
                $difficultyId = $difficultyId ?? $gameSettings['difficulty_id'] ?? 1;
                $categoryId = $categoryId ?? $gameSettings['category_id'] ?? 1;
            } else {
                $difficultyId = $difficultyId ?? 1;
                $categoryId = $categoryId ?? 1;
            }
        }

        // Create session ID
        $gameStartKey = "game:{$gameId}:start_time";
        $gameStartTime = Cache::remember($gameStartKey, now()->addHours(1), function() {
            return now()->timestamp;
        });

        $sessionKey = "game:{$gameId}:session_id:{$gameStartTime}";
        $sessionId = Cache::rememberForever($sessionKey, fn () => Str::uuid()->toString());

        // TEAM GAME LOGIC - Store or retrieve consolidated answers
        $finalPlayerAnswers = $request->answers;
        $finalAIAnswers = $request->aiAnswers ?? [];
        $finalBespokeAIAnswers = $request->bespokeAIAnswers ?? [];

       if ($teamPlayerGame) {
            Log::info('Processing team player game logic', [
                'gameId' => $gameId,
                'userId' => $user->id,
                'isTeamLeader' => $isTeamLeader,
                'originalAnswersCount' => count($request->answers)
            ]);

            // Team Player Game: Only team leader stores answers, non-leaders use team leader's answers
            if ($isTeamLeader) {
                Log::info('Processing as team leader for team player game', [
                    'gameId' => $gameId,
                    'userId' => $user->id,
                    'answersToStore' => $request->answers
                ]);

                // Store team leader's answers in cache for other team members
                $teamAnswersKey = "game:{$gameId}:team_player_answers";
                
                try {
                    Cache::put($teamAnswersKey, $request->answers, now()->addHours(1));
                    
                    // Verify cache storage
                    $verifyStored = Cache::get($teamAnswersKey);
                    $storageSuccess = !empty($verifyStored) && count($verifyStored) === count($request->answers);
                    
                    Log::info('Team leader answers cache storage result', [
                        'cacheKey' => $teamAnswersKey,
                        'storageSuccess' => $storageSuccess,
                        'storedCount' => $verifyStored ? count($verifyStored) : 0,
                        'originalCount' => count($request->answers),
                        'storedAnswers' => $verifyStored
                    ]);

                    if (!$storageSuccess) {
                        Log::error('Cache storage verification failed for team leader answers', [
                            'cacheKey' => $teamAnswersKey,
                            'expected' => $request->answers,
                            'actual' => $verifyStored
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to store team leader answers in cache', [
                        'cacheKey' => $teamAnswersKey,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            } else {
                Log::info('Processing as non-leader for team player game', [
                    'gameId' => $gameId,
                    'userId' => $user->id,
                    'originalAnswersCount' => count($request->answers)
                ]);

                // Non-leader: Use team leader's answers
                $teamAnswersKey = "game:{$gameId}:team_player_answers";
                
                try {
                    $teamLeaderAnswers = Cache::get($teamAnswersKey);
                    
                    Log::info('Cache retrieval result for team leader answers', [
                        'cacheKey' => $teamAnswersKey,
                        'found' => !is_null($teamLeaderAnswers),
                        'answersCount' => $teamLeaderAnswers ? count($teamLeaderAnswers) : 0,
                        'retrievedAnswers' => $teamLeaderAnswers
                    ]);
                    
                    if ($teamLeaderAnswers) {
                        $originalAnswersCount = count($finalPlayerAnswers);
                        $finalPlayerAnswers = $teamLeaderAnswers;
                        
                        Log::info('Successfully applied team leader answers for non-leader player', [
                            'gameId' => $gameId,
                            'userId' => $user->id,
                            'originalAnswersCount' => $originalAnswersCount,
                            'teamLeaderAnswersCount' => count($teamLeaderAnswers),
                            'finalAnswersCount' => count($finalPlayerAnswers),
                            'appliedAnswers' => $teamLeaderAnswers
                        ]);
                    } else {
                        Log::warning('Team leader answers not found in cache for non-leader player', [
                            'gameId' => $gameId,
                            'userId' => $user->id,
                            'cacheKey' => $teamAnswersKey,
                            'willUseOriginalAnswers' => true,
                            'originalAnswersCount' => count($request->answers)
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to retrieve team leader answers from cache', [
                        'cacheKey' => $teamAnswersKey,
                        'userId' => $user->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }
        } elseif ($teamAIGame) {
            Log::info('Processing team AI game logic', [
                'gameId' => $gameId,
                'userId' => $user->id,
                'isTeamLeader' => $isTeamLeader,
                'playWithAI' => $request->boolean('playWithAI', false),
                'playWithBespokeAI' => $request->boolean('playWithBespokeAI', false),
                'hasAIAnswers' => !empty($request->aiAnswers),
                'hasBespokeAIAnswers' => !empty($request->bespokeAIAnswers)
            ]);

            // Team AI Game: Only team leader gets consolidated answers, others submit individually
            if ($isTeamLeader) {
                Log::info('Processing as team leader for team AI game', [
                    'gameId' => $gameId,
                    'userId' => $user->id,
                    'difficultyId' => $difficultyId,
                    'categoryId' => $categoryId
                ]);

                // Build answer sets for comparison and consolidation
                $answerSets = [
                    'human' => $request->answers
                ];
                
                if ($request->boolean('playWithAI', false) && !empty($request->aiAnswers)) {
                    $answerSets['ai'] = $request->aiAnswers;
                }
                
                if ($request->boolean('playWithBespokeAI', false) && !empty($request->bespokeAIAnswers)) {
                    $answerSets['bespokeAI'] = $request->bespokeAIAnswers;
                }

                try {
                    $consolidatedAnswers = $this->consolidateTeamAIAnswers($answerSets, $difficultyId, $categoryId);
                    
                    // Use consolidated answers only for team leader
                    $finalPlayerAnswers = $consolidatedAnswers;
                    $finalAIAnswers = $consolidatedAnswers;
                    $finalBespokeAIAnswers = $consolidatedAnswers;
                    
                    Log::info('Team leader processing completed for team AI game', [
                        'gameId' => $gameId,
                        'userId' => $user->id,
                        'finalPlayerAnswersCount' => count($finalPlayerAnswers),
                        'finalAIAnswersCount' => count($finalAIAnswers),
                        'finalBespokeAIAnswersCount' => count($finalBespokeAIAnswers)
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to consolidate team AI answers', [
                        'gameId' => $gameId,
                        'error' => $e->getMessage()
                    ]);
                    throw $e;
                }
            } else {
                Log::info('Processing as non-leader for team AI game - using individual answers', [
                    'gameId' => $gameId,
                    'userId' => $user->id,
                    'keepingOriginalAnswers' => true,
                    'originalPlayerAnswersCount' => count($finalPlayerAnswers),
                    'originalAIAnswersCount' => count($finalAIAnswers),
                    'originalBespokeAnswersCount' => count($finalBespokeAIAnswers)
                ]);
                
                // Non-leaders in team AI game submit their own answers individually
                // No changes needed - keep original answers
            }
        }

        // Submit player answers (using final consolidated answers)
        $this->gamesService->submitAnswers($gameId, $user->id, $finalPlayerAnswers, $sessionId, $difficultyId, $categoryId, $isTeamLeader, $teamGameType);

        // Submit regular AI answers if enabled (using final consolidated answers)
        if ($request->boolean('playWithAI', false) && !empty($finalAIAnswers)) {
            Log::info('Submitting regular AI answers (potentially consolidated)');

            try {
                $this->aiGameService->submitAIAnswers(
                    $gameId,
                    $user->id,
                    $finalAIAnswers,
                    $sessionId,
                    $difficultyId,
                    $categoryId,
                    $isTeamLeader,
                    $teamGameType,
                );
                Log::info('Regular AI answers submitted successfully');
            } catch (\Exception $e) {
                Log::error('Failed to submit regular AI answers', ['error' => $e->getMessage()]);
            }
        }

        // Submit bespoke AI answers if enabled (using final consolidated answers)
        if ($request->boolean('playWithBespokeAI', false) && !empty($finalBespokeAIAnswers)) {
            Log::info('Submitting bespoke AI answers (potentially consolidated)', [
                'modelId' => $request->get('bespokeAIModelId'),
                'answersCount' => count($finalBespokeAIAnswers)
            ]);

            try {
                $this->bespokeAIGameService->submitBespokeAIAnswers(
                    $gameId,
                    1,
                    $user->id,
                    $finalBespokeAIAnswers,
                    $sessionId,
                    $difficultyId,
                    $categoryId,
                    $isTeamLeader,
                    $teamGameType,
                );
                Log::info('Bespoke AI answers submitted successfully');
            } catch (\Exception $e) {
                Log::error('Failed to submit bespoke AI answers', ['error' => $e->getMessage()]);
            }
        }

        // Broadcast completion event
        $this->triggerGameUpdate($gameId, 'game.answers_submitted', [
            'userId' => $user->id,
            'userName' => $user->name,
            'timestamp' => now()->toISOString(),
            'withRegularAI' => $request->boolean('playWithAI', false) && !empty($finalAIAnswers),
            'withBespokeAI' => $request->boolean('playWithBespokeAI', false) && !empty($finalBespokeAIAnswers),
            'bespokeAIModelId' => $request->get('bespokeAIModelId'),
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId,
            'teamPlayerGame' => $teamPlayerGame,
            'teamAIGame' => $teamAIGame,
            'isTeamLeader' => $isTeamLeader
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Game completed successfully!',
            'session_id' => $sessionId,
            'ai_submitted' => $request->boolean('playWithAI', false) && !empty($finalAIAnswers),
            'bespoke_ai_submitted' => $request->boolean('playWithBespokeAI', false) && !empty($finalBespokeAIAnswers),
            'difficulty_id' => $difficultyId,
            'category_id' => $categoryId,
            'team_player_game' => $teamPlayerGame,
            'team_ai_game' => $teamAIGame,
            'used_consolidated_answers' => $teamPlayerGame || $teamAIGame
        ]);

    }

    /**
     * Consolidate answers from multiple sources (human + AI) by selecting highest scoring answer for each question
     */
    private function consolidateTeamAIAnswers(array $answerSets, int $difficultyId, int $categoryId): array
    {
        if (empty($answerSets)) {
            return [];
        }

        // Get the first set to determine the number of questions
        $firstSet = reset($answerSets);
        $questionCount = count($firstSet);
        $consolidatedAnswers = [];

        // For each question position
        for ($i = 0; $i < $questionCount; $i++) {
            $candidateAnswers = [];
            
            // Collect all non-empty answers for this question
            foreach ($answerSets as $source => $answers) {
                if (isset($answers[$i]) && !empty(trim($answers[$i]))) {
                    $candidateAnswers[$source] = trim($answers[$i]);
                }
            }

            if (empty($candidateAnswers)) {
                $consolidatedAnswers[$i] = '';
                continue;
            }

            if (count($candidateAnswers) === 1) {
                // Only one answer available, use it
                $consolidatedAnswers[$i] = reset($candidateAnswers);
            } else {
                // Multiple answers available, score them and pick the best
                $bestAnswer = $this->selectBestAnswerByScore($candidateAnswers, $i, $difficultyId, $categoryId);
                $consolidatedAnswers[$i] = $bestAnswer;
            }
        }

        return $consolidatedAnswers;
    }

    /**
     * Select the best answer from candidates based on scoring
     */
    private function selectBestAnswerByScore(array $candidateAnswers, int $questionIndex, int $difficultyId, int $categoryId): string
    {
        // If you have access to a scoring service, use it here
        // For now, implementing a simple length-based + keyword scoring heuristic
        
        $scores = [];
        
        foreach ($candidateAnswers as $source => $answer) {
            $score = 0;
            
            // Length scoring (reasonable length gets higher score)
            $length = strlen($answer);
            if ($length >= 10 && $length <= 100) {
                $score += 10;
            } elseif ($length > 5) {
                $score += 5;
            }
            
            // Keyword/content quality scoring
            // Check for complete sentences
            if (preg_match('/[.!?]$/', $answer)) {
                $score += 5;
            }
            
            // Check for proper capitalization
            if (ctype_upper($answer[0])) {
                $score += 3;
            }
            
            // Avoid very short or very long answers
            if ($length < 3) {
                $score -= 10;
            }
            if ($length > 200) {
                $score -= 5;
            }
            
            // Prefer human answers slightly in case of ties
            if ($source === 'human') {
                $score += 2;
            }
            
            $scores[$source] = $score;
        }
        
        // Return the answer with the highest score
        $bestSource = array_keys($scores, max($scores))[0];
        
        Log::info('Answer scoring results', [
            'questionIndex' => $questionIndex,
            'candidates' => $candidateAnswers,
            'scores' => $scores,
            'selected' => $bestSource,
            'selectedAnswer' => $candidateAnswers[$bestSource]
        ]);
        
        return $candidateAnswers[$bestSource];
    }


    public function start(Games $game)
    {

        // Reset game start time for new session
        $gameStartKey = "game:{$gameId}:start_time";
        Cache::forget($gameStartKey);

        Log::info('Starting game', ['gameId' => $game->id, 'userId' => auth()->id()]);
        $game->start();

        // $this->triggerGameUpdate($game->id, 'game.started', [
        //     'userId' => auth()->id(),
        //     'userName' => auth()->user()->name,
        //     'timestamp' => now()->toISOString()
        // ]);

        return response()->json(['success' => true, 'status' => $game->status]);
    }



    public function getQuestionAverages($gameId)
    {
        try {
            Log::info('Fetching question averages', ['gameId' => $gameId]);
            $scores = GameScore::where('game_id', $gameId)
                ->with('user')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json($scores);
        } catch (\Exception $e) {
            Log::error('Failed to fetch question averages', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch question averages'], 500);
        }
    }







    // Game Room Stats Methods:

    public function getHeatmapScores(Request $request, $gameId)
    {
        Log::info('Fetching all game scores', ['gameId' => $gameId, 'filters' => $request->all()]);
        // Get filter parameters from the request
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $userIds = $request->get('user_ids');
        $andUsers = $request->get('and_users', 'false') === 'true';
        $excludeAI = $request->get('exclude_ai', 'true') === 'true';
        $difficultyId = $request->get('difficulty'); 
        $categoryId = $request->get('category');  
        
        // The user IDs come as a comma-separated string, convert them to an array
        $userIds = $userIds ? explode(',', $userIds) : null;

        // Pass all filter parameters to the service method
        $allScores = $this->gamesService->getGameHeatmapScores(
            $gameId, 
            $startDate, 
            $endDate, 
            $userIds, 
            $andUsers, 
            $excludeAI,
            $difficultyId, 
            $categoryId   
        );
        $totalGameScore = $this->gamesService->totalScore($gameId, $difficultyId, $categoryId);
        return response()->json([
            'allScores' => $allScores,
            'totalScore' => $totalGameScore,
        ]);
    }

    public function getScoreTrendStats(Request $request, int $gameId)
    {
        Log::info('Fetching score trend stats', ['gameId' => $gameId, 'filters' => $request->all()]);
        // Get filter parameters from the request
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $userIds = $request->get('user_ids');
        $andUsers = $request->get('and_users', 'false') === 'true';
        $excludeAI = $request->get('exclude_ai', 'true') === 'true';
        $difficultyId = $request->get('difficulty'); 
        $categoryId = $request->get('category'); 
        
        // The user IDs come as a comma-separated string, convert them to an array
        $userIds = $userIds ? explode(',', $userIds) : null;
        
        // Pass all filter parameters to the service methods
        $players = $this->gamesService->playerAverages(
            $gameId, 
            $startDate, 
            $endDate, 
            $userIds, 
            $andUsers, 
            $excludeAI,
            $difficultyId, 
            $categoryId   
        );
        $totalGameScore = $this->gamesService->totalScore($gameId, $difficultyId, $categoryId);
        return response()->json([
            'players' => $players,
            'totalScore' => $totalGameScore,
        ]);
    }






    // Player Interaction Methods:


    public function join($gameId)
    {
        Log::info("User attempting to join game", ['gameId' => $gameId, 'userId' => auth()->id()]);
        $game = Games::findOrFail($gameId);
        $user = auth()->user();

        if ($game->users()->where('user_id', $user->id)->exists()) {
            Log::warning('User already joined game');
            return response()->json(['message' => 'Already joined'], 400);
        }

        if ($game->users()->count() >= $game->max_players) {
            Log::warning('Game is full');
            return response()->json(['message' => 'Game is full'], 400);
        }

        $game->users()->attach($user->id);
        event(new GameStatusUpdated($game));

        $this->triggerGameUpdate($gameId, 'player.joined', [
            'userId' => auth()->id(),
            'userName' => auth()->user()->name,
            'timestamp' => now()->toISOString()
        ]);

        Log::info('User joined game successfully');
        return response()->json(['success' => true]);
    }

    public function leave($gameId)
    {
        Log::info("User attempting to leave game", ['gameId' => $gameId, 'userId' => auth()->id()]);
        $game = Games::findOrFail($gameId);
        $user = auth()->user();

        if (!$game->users()->where('user_id', $user->id)->exists()) {
            Log::warning('User not in game');
            return response()->json(['message' => 'You are not in this game'], 400);
        }

        $game->users()->detach($user->id);
        event(new GameStatusUpdated($game));

        $this->triggerGameUpdate($gameId, 'player.left', [
            'userId' => auth()->id(),
            'userName' => auth()->user()->name,
            'timestamp' => now()->toISOString()
        ]);

        Log::info('User left game successfully');
        return response()->json(['success' => true]);
    }


    public function validateMultiplayerStart(Request $request, $gameId)
    {
        Log::info('Validating multiplayer start', ['gameId' => $gameId, 'userId' => auth()->id()]);
        
        $game = Games::findOrFail($gameId);
        $currentUser = auth()->user();
        
        if (!$currentUser) {
            Log::error('User not authenticated for multiplayer validation');
            return response()->json([
                'error' => 'User not authenticated',
                'canStartMultiplayer' => false
            ], 401);
        }
        
        // Get all players currently in the game
        $playersInGame = $game->users;
        
        $otherPlayersWithMultiplayerCount = 0;
        $playerCountDetails = []; // For debugging
        
        foreach ($playersInGame as $player) {
            if ($player && $player->id !== $currentUser->id) {
                $playerCountKey = "game:{$gameId}:player:{$player->id}:playerCount";


                
                // CRITICAL FIX: Check if cache exists first
                $cachedPlayerCount = Cache::get($playerCountKey);

                Log::info('PLAYER COUNT KEY', [
                    'playerCountKey' => $playerCountKey,
                    'cachedPlayerCount' => $cachedPlayerCount,
                    ]);
                
                if ($cachedPlayerCount === null) {
                    // No cache entry exists - this player hasn't changed their count
                    // Default to 1 (single player) - this is the correct assumption
                    $playerCount = 1;
                    $cacheStatus = 'not_set_defaults_to_1';
                } else {
                    $playerCount = (int) $cachedPlayerCount;
                    $cacheStatus = 'cached_value';
                }
                
                $playerCountDetails[] = [
                    'playerId' => $player->id,
                    'playerName' => $player->name,
                    'playerCount' => $playerCount,
                    'cacheStatus' => $cacheStatus
                ];
                
                Log::info('Checking other player count', [
                    'playerId' => $player->id,
                    'playerName' => $player->name,
                    'playerCount' => $playerCount,
                    'cacheStatus' => $cacheStatus,
                    'currentUserId' => $currentUser->id
                ]);
                
                if ($playerCount > 1) {
                    $otherPlayersWithMultiplayerCount++;
                }
            }
        }
        
        $canStartMultiplayer = $otherPlayersWithMultiplayerCount > 0;
        
        Log::info('Multiplayer validation result', [
            'gameId' => $gameId,
            'userId' => $currentUser->id,
            'userName' => $currentUser->name,
            'otherPlayersWithMultiplayerCount' => $otherPlayersWithMultiplayerCount,
            'canStartMultiplayer' => $canStartMultiplayer,
            'totalPlayersInGame' => $playersInGame->count(),
            'playerCountDetails' => $playerCountDetails
        ]);
        
        return response()->json([
            'canStartMultiplayer' => $canStartMultiplayer,
            'otherPlayersWithMultiplayerCount' => $otherPlayersWithMultiplayerCount,
            'totalPlayersInGame' => $playersInGame->count(),
            'debug' => [
                'playerCountDetails' => $playerCountDetails
            ]
        ]);
    }

    public function storePlayerCount(Request $request, $gameId)
    {
        $request->validate([
            'playerCount' => 'required|integer|min:1|max:10',
            'userId' => 'required|integer'
        ]);
        
        $userId = $request->input('userId');
        $playerCount = $request->input('playerCount');
        
        // Verify the user is actually in the game
        $game = Games::findOrFail($gameId);
        if (!$game->users()->where('user_id', $userId)->exists()) {
            return response()->json(['error' => 'User not in game'], 400);
        }
        
        // Store player count preference in cache with longer expiry
        $playerCountKey = "game:{$gameId}:player:{$userId}:playerCount";
        
        // CRITICAL FIX: Use longer cache duration and add verification
        Cache::put($playerCountKey, $playerCount, now()->addHours(2)); // Increased from 1 hour

                        Log::info('PLAYER COUNT KEY - STORE', [
                    'playerCountKey' => $playerCountKey,
                    'playerCount' => $playerCount,
                    ]);
        
        // Verify the cache was written correctly
        $verifyCache = Cache::get($playerCountKey);
        
        Log::info('Player count preference stored', [
            'gameId' => $gameId,
            'userId' => $userId,
            'playerCount' => $playerCount,
            'verifyCache' => $verifyCache,
            'cacheWriteSuccess' => ($verifyCache == $playerCount)
        ]);
        
        if ($verifyCache != $playerCount) {
            Log::error('Cache write verification failed', [
                'expected' => $playerCount,
                'actual' => $verifyCache,
                'key' => $playerCountKey
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Player count preference stored',
            'verified' => ($verifyCache == $playerCount)
        ]);
    }

    public function getPlayers($gameId)
    {
        Log::info('Fetching players for game', ['gameId' => $gameId]);
        $game = Games::with('users')->findOrFail($gameId);
        return response()->json($game->users);
    }

    private function triggerGameUpdate($gameId, $eventType, $data = [])
    {
        try {
            $pusher = new \Pusher\Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                [
                    'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                    'encrypted' => true,
                ]
            );

            $pusher->trigger("game.{$gameId}", $eventType, $data);

            Log::info('Pusher event triggered successfully', [
                'channel' => "game.{$gameId}",
                'event' => $eventType,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Pusher trigger error', [
                'channel' => "game.{$gameId}",
                'event' => $eventType,
                'error' => $e->getMessage()
            ]);
        }
    }

public function playerReady(Request $request, Games $game)
{
    $user = $request->user();
    if (!$user) {
        Log::warning('Unauthorized ready status');
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $userId = $user->id;
    $userName = $user->name;
    $requiredCount = (int) $request->requiredCount;
    
    // Get game settings from the request
    $difficultyId = $request->get('difficulty_id');
    $categoryId = $request->get('category_id');
    $playWithAI = $request->boolean('play_with_ai', false);
    $playWithBespokeAI = $request->boolean('play_with_bespoke_ai', false);

    
    // Get team settings
    $joinTeamWithPlayers = $request->boolean('join_team_with_players', false);
    $joinTeamWithAI = $request->boolean('join_team_with_ai', false);
    $isTeamLeader = $request->boolean('isTeamLeader', false);


    // CHECK IF THIS IS A SINGLE-PLAYER GAME
    if ($requiredCount === 1) {
        Log::info('Single-player game detected - bypassing ready system', [
            'gameId' => $game->id,
            'userId' => $userId,
            'userName' => $userName
        ]);
        
        return response()->json([
            'status' => 'waiting',
            'gameSettings' => [
                'difficulty_id' => $difficultyId,
                'category_id' => $categoryId,
                'play_with_ai' => $playWithAI,
                'play_with_bespoke_ai' => $playWithBespokeAI,
                'join_team_with_players' => $joinTeamWithPlayers,
                'join_team_with_ai' => $joinTeamWithAI,
                'starter_name' => $userName
            ]
        ]);
    }

    // MULTIPLAYER LOGIC - CRITICAL FIX: Use atomic operations
    Log::info('Multiplayer game detected - using ready system', [
        'gameId' => $game->id,
        'userId' => $userId,
        'requiredCount' => $requiredCount,
        'teamSettings' => [
            'joinTeamWithPlayers' => $joinTeamWithPlayers,
            'joinTeamWithAI' => $joinTeamWithAI
        ]
    ]);

    $cacheKey = "game:{$game->id}:readyPlayers";
    $settingsKey = "game:{$game->id}:gameSettings";
    
    // CRITICAL FIX: Use cache locks to prevent race conditions
    $lockKey = "game:{$game->id}:ready_lock";
    
    return Cache::lock($lockKey, 10)->get(function () use ($game, $userId, $userName, $requiredCount, $difficultyId, $categoryId, $playWithAI, $playWithBespokeAI, $joinTeamWithPlayers, $joinTeamWithAI, $isTeamLeader, $cacheKey, $settingsKey) {
        
        // Get current ready players with atomic read
        $readyPlayers = Cache::get($cacheKey, []);
        
        // Ensure it's an array
        if (!is_array($readyPlayers)) {
            $readyPlayers = [];
        }
        
        // CRITICAL FIX: Get existing settings to preserve original starter
        $currentSettings = Cache::get($settingsKey);
        
        $gameQuestions = $this->gamesService->getGameQuestionsByDifficultyAndCategory(
            $game, 
            $difficultyId, 
            $categoryId
        );
        
        // CRITICAL FIX: Only set starter_name if it hasn't been set yet
        $starterName = $currentSettings['starter_name'] ?? $userName;
        
        $gameSettings = [
            'difficulty_id' => $difficultyId,
            'category_id' => $categoryId,
            'play_with_ai' => $playWithAI,
            'play_with_bespoke_ai' => $playWithBespokeAI,
            'join_team_with_players' => $joinTeamWithPlayers,
            'join_team_with_ai' => $joinTeamWithAI,
            'questions' => $gameQuestions,
            'starter_name' => $starterName // Use preserved starter name
        ];
        
        // Only update cache if this is the first player or settings have changed
        if (!$currentSettings || count($readyPlayers) === 0) {
            Cache::put($settingsKey, $gameSettings, now()->addMinutes(30));
            $currentSettings = $gameSettings;
            
            Log::info('Game settings stored by first ready player', [
                'gameId' => $game->id,
                'userId' => $userId,
                'originalStarter' => $starterName,
                'settings' => $gameSettings
            ]);
        } else {
            // Use existing settings but update questions if needed
            $currentSettings['questions'] = $gameQuestions;
        }

        // Add player to ready list if not already there
        if (!in_array($userId, $readyPlayers)) {
            $readyPlayers[] = $userId;
            // CRITICAL FIX: Use put instead of increment operations
            Cache::put($cacheKey, $readyPlayers, now()->addMinutes(30));
        }

        $readyCount = count($readyPlayers);
        
        Log::info('Player marked ready for multiplayer', [
            'gameId' => $game->id,
            'userId' => $userId,
            'userName' => $userName,
            'readyCount' => $readyCount,
            'requiredCount' => $requiredCount,
            'originalStarter' => $currentSettings['starter_name'],
            'allReadyPlayers' => $readyPlayers,
            'cacheKey' => $cacheKey
        ]);

        // CRITICAL FIX: Verify ready players array before broadcasting
        if (empty($readyPlayers)) {
            Log::error('Ready players array is empty after adding player', [
                'gameId' => $game->id,
                'userId' => $userId,
                'cacheKey' => $cacheKey,
                'attemptedReadyPlayers' => $readyPlayers
            ]);
        }

        broadcast(new PlayerReady(
            $game->id, 
            $userId, 
            $userName, 
            $readyCount, 
            $requiredCount, 
            $currentSettings // This now preserves the original starter
        ))->toOthers();

        if ($readyCount >= $requiredCount) {
            $game->status = 'in_progress';
            $game->save();
            
            // Clean up cache
            Cache::forget($cacheKey);
            Cache::forget($settingsKey);
            
            $this->triggerGameUpdate($game->id, 'game.started.all.ready', [
                'gameId' => $game->id,
                'playerCount' => $requiredCount,
                'readyCount' => $readyCount,
                'gameSettings' => $currentSettings, // Original starter preserved
                'isTeamLeader' => $isTeamLeader,
                'timestamp' => now()->toISOString()
            ]);
            
            Log::info('All players ready - multiplayer game starting', [
                'gameId' => $game->id,
                'playerCount' => $requiredCount,
                'finalReadyPlayers' => $readyPlayers,
                'originalStarter' => $currentSettings['starter_name'],
                'finalSettings' => $currentSettings
            ]);
            
            return response()->json(['status' => 'started', 'gameSettings' => $currentSettings]);
        }

        return response()->json(['status' => 'waiting', 'gameSettings' => $currentSettings]);
    });
}


    public function broadcast(Request $request, $gameId)
    {
        $event = $request->input('event');
        $data = $request->input('data');

        if (!$event || !$data) {
            Log::warning('Broadcast event or data missing');
            return response()->json(['error' => 'Event and data are required'], 400);
        }

        Log::info('Broadcasting custom event', ['gameId' => $gameId, 'event' => $event]);
        $this->triggerGameUpdate($gameId, $event, $data);

        return response()->json(['success' => true]);
    }

    public function resetGameSession(Request $request, $gameId)
    {
        $gameStartKey = "game:{$gameId}:start_time";
        $sessionKey = "game:{$gameId}:session_id";
        // DON'T clear ready players here - they should persist until game actually starts
        // $readyPlayersKey = "game:{$gameId}:readyPlayers"; // REMOVE THIS LINE
        
        // Clear only session-related keys, not ready players
        Cache::forget($gameStartKey);
        Cache::forget($sessionKey);
        // Cache::forget($readyPlayersKey); // REMOVE THIS LINE
        
        Log::info('Game session reset', [
            'gameId' => $gameId, 
            'userId' => $request->user()?->id,
            'clearedKeys' => [$gameStartKey, $sessionKey] // Update logging
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Game session reset successfully'
        ]);
    }

    // Add a separate method to clear ready players when needed
    public function clearReadyPlayers(Request $request, $gameId)
    {
        $readyPlayersKey = "game:{$gameId}:readyPlayers";
        Cache::forget($readyPlayersKey);
        
        Log::info('Ready players cleared', ['gameId' => $gameId]);
        
        return response()->json([
            'success' => true,
            'message' => 'Ready players cleared'
        ]);
    }
}
