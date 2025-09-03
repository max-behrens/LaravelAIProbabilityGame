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
            'isTeamLeader' => 'sometimes|boolean',
            // New team management fields
            'lobbyTeamLeader' => 'sometimes|boolean',
            'isLobbyTeamLeader' => 'sometimes|boolean',
            'selectedTeam' => 'sometimes|integer|in:1,2',
            'isTeam1Leader' => 'sometimes|boolean',
            'isTeam2Leader' => 'sometimes|boolean',
            'shouldProgressTeamSelection' => 'sometimes|boolean',
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

        // New team management flags
        $lobbyTeamLeader = $request->boolean('lobbyTeamLeader', false);
        $isLobbyTeamLeader = $request->boolean('isLobbyTeamLeader', false);
        $selectedTeam = $request->get('selectedTeam');
        $isTeam1Leader = $request->boolean('isTeam1Leader', false);
        $isTeam2Leader = $request->boolean('isTeam2Leader', false);
        $shouldProgressTeamSelection = $request->boolean('shouldProgressTeamSelection', false);


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

        // ENHANCED TEAM GAME LOGIC
        $finalPlayerAnswers = $request->answers;
        $finalAIAnswers = $request->aiAnswers ?? [];
        $finalBespokeAIAnswers = $request->bespokeAIAnswers ?? [];

        // 1. LOBBY TEAM LEADER MODE (2+ players with AI, no team modes)
        if ($lobbyTeamLeader && !$teamPlayerGame && !$teamAIGame) {
            if ($isLobbyTeamLeader) {
                // Store lobby team leader's answers
                $lobbyAnswersKey = "game:{$gameId}:lobby_team_answers";
                Cache::put($lobbyAnswersKey, $request->answers, now()->addHours(1));
                
                Log::info("Lobby team leader stored answers", [
                    'gameId' => $gameId,
                    'userId' => $user->id,
                    'answersCount' => count($request->answers)
                ]);
            } else {
                // Non-leader: Use lobby team leader's answers
                $lobbyAnswersKey = "game:{$gameId}:lobby_team_answers";
                $lobbyLeaderAnswers = Cache::get($lobbyAnswersKey);
                
                if ($lobbyLeaderAnswers) {
                    $finalPlayerAnswers = $lobbyLeaderAnswers;
                    Log::info("Non-lobby-leader using lobby leader's answers", [
                        'gameId' => $gameId,
                        'userId' => $user->id,
                        'originalCount' => count($request->answers),
                        'finalCount' => count($finalPlayerAnswers)
                    ]);
                }
            }
        }
        
        // 2. TEAM SELECTION MODE (3+ players without AI)
        elseif (!$teamPlayerGame && !$teamAIGame && !$lobbyTeamLeader) {
            $teamAnswersKey = "game:{$gameId}:team_{$selectedTeam}_answers";

            if ($isTeamLeader === true) {
                // Team leader: Store answers for team
                Cache::put($teamAnswersKey, $request->answers, now()->addHours(1));

                Log::info("TEAM LEADER - STORING ANSWERS", [
                    'team' => $selectedTeam,
                    'gameId' => $gameId,
                    'userId' => $user->id,
                    'answersCount' => count($request->answers)
                ]);

            } else if ($isTeamLeader === false) {
                // Simple deduplication: Check if this user already processed this team's answers
                $userProcessedKey = "game:{$gameId}:user_{$user->id}_processed_team_{$selectedTeam}";

                if (Cache::has($userProcessedKey)) {
                    // Clear the processed flag to allow future submissions if needed
                    Cache::forget($userProcessedKey);

                    Log::info("NON TEAM LEADER - ALREADY PROCESSED", [
                        'team' => $selectedTeam,
                        'gameId' => $gameId,
                        'userId' => $user->id,
                        'message' => 'Duplicate submission blocked'
                    ]);
                    return;
                }

                // Non-leader: Use team leader's answers
                $teamLeaderAnswers = Cache::get($teamAnswersKey);
                
                Log::info("NON TEAM LEADER - FETCHING LEADER ANSWERS", [
                    'team' => $selectedTeam,
                    'teamLeaderAnswers' => $teamLeaderAnswers,
                    'gameId' => $gameId,
                    'userId' => $user->id
                ]);
                
                if ($teamLeaderAnswers) {
                    // Set a flag that this user has processed this team's submission
                    Cache::put($userProcessedKey, true, now()->addHours(1));
                    
                    $finalPlayerAnswers = $teamLeaderAnswers;
                    
                    Log::info("Team {$selectedTeam} member using leader's answers", [
                        'gameId' => $gameId,
                        'userId' => $user->id,
                        'team' => $selectedTeam,
                        'originalCount' => count($request->answers),
                        'finalCount' => count($finalPlayerAnswers)
                    ]);
                }
            } else if ($shouldProgressTeamSelection === true) {
                return;
            }
        }
        
        // 3. TEAM AI GAME MODE (enhanced for lobby team leader)
        elseif ($teamAIGame) {
            if ($isTeamLeader) {
                // Host player in teamAI mode: use consolidation logic (existing)
                $answerSets = [
                    'human' => $request->answers
                ];
                
                if ($request->boolean('playWithAI', false) && !empty($request->aiAnswers)) {
                    $answerSets['ai'] = $request->aiAnswers;
                }
                
                if ($request->boolean('playWithBespokeAI', false) && !empty($request->bespokeAIAnswers)) {
                    $answerSets['bespokeAI'] = $request->bespokeAIAnswers;
                }

                $consolidatedAnswers = $this->consolidateTeamAIAnswers($answerSets, $difficultyId, $categoryId);
                $finalPlayerAnswers = $consolidatedAnswers;
                $finalAIAnswers = $consolidatedAnswers;
                $finalBespokeAIAnswers = $consolidatedAnswers;
            } else {
                // Non-host players in teamAI mode
                if ($isLobbyTeamLeader) {
                    // Lobby team leader among non-host players: store answers
                    $lobbyAnswersKey = "game:{$gameId}:teamai_lobby_answers";
                    Cache::put($lobbyAnswersKey, $request->answers, now()->addHours(1));
                    
                    Log::info("TeamAI lobby team leader stored answers", [
                        'gameId' => $gameId,
                        'userId' => $user->id,
                        'answersCount' => count($request->answers)
                    ]);
                } else {
                    // Regular non-host player: use lobby team leader's answers if available
                    $lobbyAnswersKey = "game:{$gameId}:teamai_lobby_answers";
                    $lobbyLeaderAnswers = Cache::get($lobbyAnswersKey);
                    
                    if ($lobbyLeaderAnswers) {
                        $finalPlayerAnswers = $lobbyLeaderAnswers;
                        Log::info("TeamAI non-leader using lobby leader's answers", [
                            'gameId' => $gameId,
                            'userId' => $user->id,
                            'originalCount' => count($request->answers),
                            'finalCount' => count($finalPlayerAnswers)
                        ]);
                    }
                }
            }
        } elseif ($teamPlayerGame) {
            if ($isTeamLeader) {
                $teamAnswersKey = "game:{$gameId}:team_player_answers";
                Cache::put($teamAnswersKey, $request->answers, now()->addHours(1));
            } else {
                $teamAnswersKey = "game:{$gameId}:team_player_answers";
                $teamLeaderAnswers = Cache::get($teamAnswersKey);
                
                if ($teamLeaderAnswers) {
                    $finalPlayerAnswers = $teamLeaderAnswers;
                }
            }
        }


        // Submit player answers (using final consolidated answers)
        $this->gamesService->submitAnswers($gameId, $user->id, $finalPlayerAnswers, $sessionId, $difficultyId, $categoryId, $isTeamLeader, $teamGameType);

        // Submit regular AI answers if enabled (using final consolidated answers)
        if ($request->boolean('playWithAI', false) && !empty($finalAIAnswers)) {

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
        
        // Get enhanced team management settings
        $lobbyTeamLeader = $request->boolean('lobbyTeamLeader', false);
        $isLobbyTeamLeader = $request->boolean('isLobbyTeamLeader', false);
        $selectedTeam = $request->get('selectedTeam');
        $isTeam1Leader = $request->boolean('isTeam1Leader', false);
        $isTeam2Leader = $request->boolean('isTeam2Leader', false);

        // NEW: Get current team assignments from request
        $currentTeamAssignments = $request->get('currentTeamAssignments', []);

        // CHECK IF THIS IS A SINGLE-PLAYER GAME
        if ($requiredCount === 1) {
            return response()->json([
                'status' => 'waiting',
                'gameSettings' => [
                    'difficulty_id' => $difficultyId,
                    'category_id' => $categoryId,
                    'play_with_ai' => $playWithAI,
                    'play_with_bespoke_ai' => $playWithBespokeAI,
                    'join_team_with_players' => $joinTeamWithPlayers,
                    'join_team_with_ai' => $joinTeamWithAI,
                    'starter_name' => $userName,
                    // New team settings
                    'lobby_team_leader' => $lobbyTeamLeader,
                    'is_lobby_team_leader' => $isLobbyTeamLeader,
                    'selected_team' => $selectedTeam,
                    'is_team1_leader' => $isTeam1Leader,
                    'is_team2_leader' => $isTeam2Leader,
                    // Include team assignments
                    'team_assignments' => $currentTeamAssignments
                ]
            ]);
        }

        $cacheKey = "game:{$game->id}:readyPlayers";
        $settingsKey = "game:{$game->id}:gameSettings";
        $teamAssignmentsKey = "game:{$game->id}:teamAssignments";
        
        // CRITICAL FIX: Use cache locks to prevent race conditions
        $lockKey = "game:{$game->id}:ready_lock";
        
        return Cache::lock($lockKey, 10)->get(function () use (
            $game, $userId, $userName, $requiredCount, $difficultyId, $categoryId, 
            $playWithAI, $playWithBespokeAI, $joinTeamWithPlayers, $joinTeamWithAI, 
            $isTeamLeader, $cacheKey, $settingsKey, $teamAssignmentsKey, $lobbyTeamLeader, $isLobbyTeamLeader,
            $selectedTeam, $isTeam1Leader, $isTeam2Leader, $currentTeamAssignments
        ) {
            
            // Get current ready players with atomic read
            $readyPlayers = Cache::get($cacheKey, []);
            
            // Ensure it's an array
            if (!is_array($readyPlayers)) {
                $readyPlayers = [];
            }
            
            // Get existing settings to preserve original starter
            $currentSettings = Cache::get($settingsKey);
            
            // NEW: Get and update team assignments
            $existingTeamAssignments = Cache::get($teamAssignmentsKey, []);
            
            // Merge current player's team assignments with existing ones
            if (!empty($currentTeamAssignments)) {
                $existingTeamAssignments = array_merge($existingTeamAssignments, $currentTeamAssignments);
                
                // Store updated team assignments in cache
                Cache::put($teamAssignmentsKey, $existingTeamAssignments, now()->addMinutes(30));
                
                Log::info('Team assignments updated in cache', [
                    'gameId' => $game->id,
                    'userId' => $userId,
                    'newAssignments' => $currentTeamAssignments,
                    'allAssignments' => $existingTeamAssignments
                ]);
            }
            
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
                'starter_name' => $starterName,
                // Enhanced team settings
                'lobby_team_leader' => $lobbyTeamLeader,
                'is_lobby_team_leader' => $isLobbyTeamLeader,
                'selected_team' => $selectedTeam,
                'is_team1_leader' => $isTeam1Leader,
                'is_team2_leader' => $isTeam2Leader,
                // Include team assignments in settings
                'team_assignments' => $existingTeamAssignments,
            ];
            
            // Only update cache if this is the first player or settings have changed
            if (!$currentSettings || count($readyPlayers) === 0) {
                Cache::put($settingsKey, $gameSettings, now()->addMinutes(30));
                $currentSettings = $gameSettings;
            } else {
                // Use existing settings but update questions and team assignments if needed
                $currentSettings['questions'] = $gameQuestions;
                $currentSettings['team_assignments'] = $existingTeamAssignments;
            }

            // Add player to ready list if not already there
            if (!in_array($userId, $readyPlayers)) {
                $readyPlayers[] = $userId;
                Cache::put($cacheKey, $readyPlayers, now()->addMinutes(30));
            }

            $readyCount = count($readyPlayers);
            
            // Enhanced broadcast data with team assignments
            $broadcastData = [
                'gameId' => $game->id,
                'userId' => $userId,
                'userName' => $userName,
                'readyCount' => $readyCount,
                'requiredCount' => $requiredCount,
                'gameSettings' => $currentSettings,
                'isTeamLeader' => $isTeamLeader,
                'isLobbyTeamLeader' => $isLobbyTeamLeader,
                'isTeam1Leader' => $isTeam1Leader,
                'isTeam2Leader' => $isTeam2Leader,
                'selectedTeam' => $selectedTeam,
                // Include current team assignments in broadcast
                'currentTeamAssignments' => $existingTeamAssignments,
                'timestamp' => now()->toISOString()
            ];
            
            broadcast(new PlayerReady(
                $game->id, 
                $userId, 
                $userName, 
                $readyCount, 
                $requiredCount, 
                $currentSettings, // This now includes team assignments
                $broadcastData // Pass enhanced data to event
            ))->toOthers();

            if ($readyCount >= $requiredCount) {
                $game->status = 'in_progress';
                $game->save();
                
                // Clean up cache
                Cache::forget($cacheKey);
                Cache::forget($settingsKey);
                // Keep team assignments for the duration of the game
                // Cache::forget($teamAssignmentsKey); // Don't delete team assignments yet
                
                $this->triggerGameUpdate($game->id, 'game.started.all.ready', [
                    'gameId' => $game->id,
                    'playerCount' => $requiredCount,
                    'readyCount' => $readyCount,
                    'gameSettings' => $currentSettings,
                    'isTeamLeader' => $isTeamLeader,
                    'teamAssignments' => $existingTeamAssignments, // Include in all.ready event
                    'team1Leader' => $isTeam1Leader ? $userName : null,
                    'team2Leader' => $isTeam2Leader ? $userName : null,
                    'lobbyTeamLeader' => $isLobbyTeamLeader ? $userName : null,
                    'timestamp' => now()->toISOString()
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
