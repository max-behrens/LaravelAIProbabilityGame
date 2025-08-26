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
            'categoryId' => $request->get('category_id')
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
            'category_id' => 'sometimes|nullable|integer'
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get difficulty and category IDs
        $difficultyId = $request->get('difficulty_id');
        $categoryId = $request->get('category_id');

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

        // Submit player answers
        $this->gamesService->submitAnswers($gameId, $user->id, $request->answers, $sessionId, $difficultyId, $categoryId);

        // Submit regular AI answers if enabled
        if ($request->boolean('playWithAI', false) && $request->has('aiAnswers') && is_array($request->aiAnswers)) {
            Log::info('Submitting regular AI answers');

            try {
                $this->aiGameService->submitAIAnswers(
                    $gameId,
                    $user->id,
                    $request->aiAnswers,
                    $sessionId,
                    $difficultyId,
                    $categoryId
                );
                Log::info('Regular AI answers submitted successfully');
            } catch (\Exception $e) {
                Log::error('Failed to submit regular AI answers', ['error' => $e->getMessage()]);
            }
        }

        // Submit bespoke AI answers if enabled
        if ($request->boolean('playWithBespokeAI', false) && 
            $request->has('bespokeAIAnswers') && 
            is_array($request->bespokeAIAnswers))
            {
            
            Log::info('Submitting bespoke AI answers', [
                'modelId' => $request->get('bespokeAIModelId'),
                'answersCount' => count($request->bespokeAIAnswers)
            ]);

            try {
                $this->bespokeAIGameService->submitBespokeAIAnswers(
                    $gameId,
                    1,
                    $user->id,
                    $request->bespokeAIAnswers,
                    $sessionId,
                    $difficultyId,
                    $categoryId
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
            'withRegularAI' => $request->boolean('playWithAI', false) && $request->has('aiAnswers'),
            'withBespokeAI' => $request->boolean('playWithBespokeAI', false) && $request->has('bespokeAIAnswers'),
            'bespokeAIModelId' => $request->get('bespokeAIModelId'),
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Game completed successfully!',
            'session_id' => $sessionId,
            'ai_submitted' => $request->boolean('playWithAI', false) && $request->has('aiAnswers'),
            'bespoke_ai_submitted' => $request->boolean('playWithBespokeAI', false) && $request->has('bespokeAIAnswers'),
            'difficulty_id' => $difficultyId,
            'category_id' => $categoryId
        ]);
    }

    public function getBespokeAIAnswerForQuestion(Request $request, $gameId)
    {
        $request->validate([
            'modelId' => 'required|integer|exists:bespoke_ai_models,id',
            'questionIndex' => 'required|integer|min:0',
            'questionText' => 'required|string',
            'playerAnswer' => 'sometimes|string',
            'difficultyId' => 'sometimes|integer',
            'categoryId' => 'sometimes|integer'
        ]);

        try {
            $result = $this->bespokeAIGameService->getBespokeAIAnswerForQuestion(
                $gameId,
                $request->input('modelId'),
                $request->input('questionIndex'),
                $request->input('questionText'),
                $request->input('playerAnswer', ''),
                $request->input('difficultyId'),
                $request->input('categoryId')
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to get bespoke AI answer for question', [
                'gameId' => $gameId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get bespoke AI answer',
                'answer' => 'I need more training data',
                'score' => 0,
                'isCorrect' => false
            ], 500);
        }
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
        
        // FIXED: Check for OTHER players with playerCount > 1 (not just == 2)
        $otherPlayersWithMultiplayerCount = 0;
        
        foreach ($playersInGame as $player) {
            // FIXED: Only check OTHER players (exclude current user who is clicking start)
            if ($player && $player->id !== $currentUser->id) {
                // Check if this player has their player count set to > 1 (multiplayer)
                $playerCountKey = "game:{$gameId}:player:{$player->id}:playerCount";
                $playerCount = Cache::get($playerCountKey, 1); // Default to 1 if not set
                
                Log::info('Checking other player count', [
                    'playerId' => $player->id,
                    'playerName' => $player->name,
                    'playerCount' => $playerCount,
                    'currentUserId' => $currentUser->id
                ]);
                
                // FIXED: Check for > 1 instead of == 2
                if ($playerCount > 1) {
                    $otherPlayersWithMultiplayerCount++;
                }
            }
        }
        
        // FIXED: Only allow multiplayer start if OTHER players have multiplayer count
        $canStartMultiplayer = $otherPlayersWithMultiplayerCount > 0;
        
        Log::info('Multiplayer validation result', [
            'gameId' => $gameId,
            'userId' => $currentUser->id,
            'userName' => $currentUser->name,
            'otherPlayersWithMultiplayerCount' => $otherPlayersWithMultiplayerCount,
            'canStartMultiplayer' => $canStartMultiplayer,
            'totalPlayersInGame' => $playersInGame->count()
        ]);
        
        return response()->json([
            'canStartMultiplayer' => $canStartMultiplayer,
            'otherPlayersWithMultiplayerCount' => $otherPlayersWithMultiplayerCount,
            'totalPlayersInGame' => $playersInGame->count()
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
        
        // Store player count preference in cache (expires in 1 hour)
        $playerCountKey = "game:{$gameId}:player:{$userId}:playerCount";
        Cache::put($playerCountKey, $playerCount, now()->addHour());
        
        Log::info('Player count preference stored', [
            'gameId' => $gameId,
            'userId' => $userId,
            'playerCount' => $playerCount
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Player count preference stored'
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
        $difficultyId = $request->get('difficulty_id', 1);
        $categoryId = $request->get('category_id', 1);
        $playWithAI = $request->boolean('play_with_ai', false);
        $playWithBespokeAI = $request->boolean('play_with_bespoke_ai', false);

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
                    'starter_name' => $userName
                ]
            ]);
        }

        // MULTIPLAYER LOGIC
        Log::info('Multiplayer game detected - using ready system', [
            'gameId' => $game->id,
            'userId' => $userId,
            'requiredCount' => $requiredCount
        ]);

        $cacheKey = "game:{$game->id}:readyPlayers";
        $readyPlayers = Cache::get($cacheKey, []);

        $settingsKey = "game:{$game->id}:gameSettings";
        $currentSettings = Cache::get($settingsKey);
        
        if (!$currentSettings) {
            $gameQuestions = $this->gamesService->getGameQuestionsByDifficultyAndCategory(
                $game, 
                $difficultyId, 
                $categoryId
            );
            
            $gameSettings = [
                'difficulty_id' => $difficultyId,
                'category_id' => $categoryId,
                'play_with_ai' => $playWithAI,
                'play_with_bespoke_ai' => $playWithBespokeAI,
                'questions' => $gameQuestions,
                'starter_name' => $userName
            ];
            
            Cache::put($settingsKey, $gameSettings, now()->addMinutes(30));
            $currentSettings = $gameSettings;
            
            Log::info('Game settings stored by first ready player', [
                'gameId' => $game->id,
                'userId' => $userId,
                'settings' => $gameSettings
            ]);
        }

        if (!in_array($userId, $readyPlayers)) {
            $readyPlayers[] = $userId;
            Cache::put($cacheKey, $readyPlayers, now()->addMinutes(30));
        }

        $readyCount = count($readyPlayers);
        
        Log::info('Player marked ready for multiplayer', [
            'gameId' => $game->id,
            'userId' => $userId,
            'readyCount' => $readyCount,
            'requiredCount' => $requiredCount,
            'allReadyPlayers' => $readyPlayers
        ]);

        broadcast(new PlayerReady(
            $game->id, 
            $userId, 
            $userName, 
            $readyCount, 
            $requiredCount, 
            $currentSettings
        ))->toOthers();

        if ($readyCount >= $requiredCount) {
            $game->status = 'in_progress';
            $game->save();
            
            Cache::forget($cacheKey);
            Cache::forget($settingsKey);
            
            $this->triggerGameUpdate($game->id, 'game.started.all.ready', [
                'gameId' => $game->id,
                'playerCount' => $requiredCount,
                'readyCount' => $readyCount,
                'gameSettings' => $currentSettings,
                'timestamp' => now()->toISOString()
            ]);
            
            Log::info('All players ready - multiplayer game starting', [
                'gameId' => $game->id,
                'playerCount' => $requiredCount,
                'finalReadyPlayers' => $readyPlayers,
                'finalSettings' => $currentSettings
            ]);
            
            return response()->json(['status' => 'started', 'gameSettings' => $currentSettings]);
        }

        return response()->json(['status' => 'waiting', 'gameSettings' => $currentSettings]);
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
