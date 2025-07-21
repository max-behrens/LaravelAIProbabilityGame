<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Games;
use App\Models\User;
use App\Models\GameScore;
use App\Events\PlayerReady;
use Illuminate\Support\Facades\Cache;
use App\Events\GameStatusUpdated;
use App\Services\Dashboard\GamesService;
use App\Services\Dashboard\AIGameService;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;



class GamesController extends Controller
{
    protected $gamesService;

    public function __construct(GamesService $gamesService, AIGameService $aiGameService)
    {
        $this->gamesService = $gamesService;
        $this->aiGameService = $aiGameService;
    }

    public function index()
    {
        Log::info('Fetching paginated games list...');
        $games = Games::with(['users', 'gameType'])
            ->withCount('users as players_count')
            ->paginate(10);

        $games->getCollection()->transform(function ($game) {
            return [
                'id' => $game->id,
                'title' => $game->title,
                'players_count' => $game->players_count,
                'max_players' => $game->max_players,
                'users' => $game->users,
                'game_type_name' => $game->gameType?->name ?? null,
            ];
        });

        Log::info('Games fetched successfully');
        return $games;
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

    public function getScores(Request $request)
    {
        $gameId = $request->gameId;
        $page = $request->query('page', 1);

        Log::info('Fetching game scores', ['gameId' => $gameId, 'page' => $page]);
        $gameScores = $this->gamesService->getGameScores($gameId, $page);
        Log::info('Game scores retrieved', ['scores' => $gameScores]);

        return response()->json($gameScores);
    }


    public function showRoom($gameId, $userId)
    {
        Log::info('Loading game room', ['gameId' => $gameId, 'userId' => $userId]);
        $gameDetails = Games::findOrFail($gameId);
        $gameType = $this->gamesService->getGameType($gameDetails);
        $userDetails = User::findOrFail($userId);
        $gameQuestions = $this->gamesService->getGameQuestions($gameDetails);

        return Inertia::render('Dashboard/AIGame/Room/Index', [
            'gameId' => (int) $gameId,
            'userId' => $userId,
            'game' => $gameDetails,
            'maxPlayers' => $gameDetails->max_players,
            'userDetails' => $userDetails,
            'gameQuestions' => $gameQuestions,
            'gameType' => $gameType,
            'auth' => ['user' => auth()->user()],
        ]);
    }


    public function submitAnswer(Request $request, $gameId)
    {
        Log::info('Submitting answers', [
            'gameId' => $gameId, 
            'userId' => $request->user()?->id,
            'hasAIAnswers' => $request->has('aiAnswers'),
            'playWithAI' => $request->boolean('playWithAI', false)
        ]);
    
        $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'nullable|string',
            'aiAnswers' => 'sometimes|array',
            'aiAnswers.*' => 'nullable|string', 
            'playWithAI' => 'sometimes|boolean'
        ]);
    
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    
        // Use game start time to create unique session per game round
        $gameStartKey = "game:{$gameId}:start_time";
        $gameStartTime = Cache::remember($gameStartKey, now()->addHours(1), function() {
            return now()->timestamp;
        });
    
        $sessionKey = "game:{$gameId}:session_id:{$gameStartTime}";
        $sessionId = Cache::rememberForever($sessionKey, fn () => Str::uuid()->toString());
    
        // Submit player answers with shared session_id
        $this->gamesService->submitAnswers($gameId, $user->id, $request->answers, $sessionId);
    
        // Submit AI answers if playing with AI
        if ($request->boolean('playWithAI', false) && $request->has('aiAnswers')) {
            Log::info('Submitting AI answers', [
                'gameId' => $gameId,
                'sessionId' => $sessionId,
                'aiAnswersCount' => count($request->aiAnswers ?? [])
            ]);
            
            try {
                // Submit AI answers with the same session ID
                $this->aiGameService->submitAIAnswers(
                    $gameId, 
                    $user->id, // Use the current user's ID for consistency
                    $request->aiAnswers, 
                    $sessionId
                );
                
                Log::info('AI answers submitted successfully', [
                    'gameId' => $gameId,
                    'sessionId' => $sessionId
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to submit AI answers', [
                    'gameId' => $gameId,
                    'sessionId' => $sessionId,
                    'error' => $e->getMessage()
                ]);
                
                // Don't fail the entire request if AI submission fails
                // But log the error for debugging
            }
        }
    
        $this->triggerGameUpdate($gameId, 'game.answers_submitted', [
            'userId' => $user->id,
            'userName' => $user->name,
            'timestamp' => now()->toISOString(),
            'withAI' => $request->boolean('playWithAI', false)
        ]);
    
        return response()->json([
            'success' => true,
            'message' => 'Game completed successfully!',
            'session_id' => $sessionId,
            'ai_submitted' => $request->boolean('playWithAI', false) && $request->has('aiAnswers')
        ]);
    }

    public function start(Games $game)
    {

        // Reset game start time for new session
        $gameStartKey = "game:{$gameId}:start_time";
        Cache::forget($gameStartKey);

        Log::info('Starting game', ['gameId' => $game->id, 'userId' => auth()->id()]);
        $game->start();

        $this->triggerGameUpdate($game->id, 'game.started', [
            'userId' => auth()->id(),
            'userName' => auth()->user()->name,
            'timestamp' => now()->toISOString()
        ]);

        return response()->json(['success' => true, 'status' => $game->status]);
    }



    public function getScoreTrends($gameId)
    {
        try {
            Log::info('Fetching score trends', ['gameId' => $gameId]);
            $scores = GameScore::where('game_id', $gameId)
                ->with('user')
                ->orderBy('created_at', 'asc')
                ->get();

            $trends = [];
            foreach ($scores as $score) {
                $userName = $score->user->name ?? 'Anonymous';
                $trends[$userName][] = [
                    'x' => $score->created_at->timestamp * 1000,
                    'y' => $score->score
                ];
            }

            $series = [];
            foreach ($trends as $userName => $data) {
                $series[] = [
                    'name' => $userName,
                    'data' => $data
                ];
            }

            return response()->json($series);
        } catch (\Exception $e) {
            Log::error('Failed to fetch score trends', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch score trends'], 500);
        }
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

    public function getAllScores($gameId)
    {
        Log::info('Fetching all game scores', ['gameId' => $gameId]);
        $allScores = $this->gamesService->getAllGameScores($gameId);
        return response()->json($allScores);
    }

    public function getScoreTrendStats(int $gameId)
    {
        Log::info('Fetching score trend stats', ['gameId' => $gameId]);
        $players = $this->gamesService->playerAverages($gameId);
        $totalGameScore = $this->gamesService->totalScore($gameId);

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

        $cacheKey = "game:{$game->id}:readyPlayers";
        $readyPlayers = Cache::get($cacheKey, []);

        if (!in_array($userId, $readyPlayers)) {
            $readyPlayers[] = $userId;
            Cache::put($cacheKey, $readyPlayers, now()->addMinutes(30));
        }

        $readyCount = count($readyPlayers);
        Log::info('Player marked ready', [
            'gameId' => $game->id,
            'userId' => $userId,
            'readyCount' => $readyCount,
            'requiredCount' => $requiredCount
        ]);

        broadcast(new PlayerReady($game->id, $userId, $userName, $readyCount, $requiredCount))->toOthers();

        if ($readyCount >= $requiredCount) {
            $game->status = 'in_progress';
            $game->save();
            return response()->json(['status' => 'started']);
        }

        return response()->json(['status' => 'waiting']);
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
        
        // Clear both keys to force new session creation
        Cache::forget($gameStartKey);
        Cache::forget($sessionKey);
        
        Log::info('Game session reset', ['gameId' => $gameId, 'userId' => $request->user()?->id]);
        
        return response()->json([
            'success' => true,
            'message' => 'Game session reset successfully'
        ]);
    }
}
