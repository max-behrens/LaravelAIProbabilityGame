<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Games;
use App\Models\User;
use App\Events\PlayerReady;
use Illuminate\Support\Facades\Cache;
use App\Events\GameStatusUpdated;
use App\Services\Dashboard\GamesService;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class GamesController extends Controller
{
    protected $gamesService;
    
    public function __construct(GamesService $gamesService)
    {
        $this->gamesService = $gamesService;
    }
    public function index()
    {
        $games = Games::with(['users', 'gameType'])
            ->withCount('users as players_count')
            ->paginate(10);
    
        // Map games to include game_type_name
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
    
        return $games;
    }

    public function join($gameId)
    {
        $game = Games::findOrFail($gameId);
        $user = auth()->user();

        if ($game->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Already joined'], 400);
        }

        if ($game->users()->count() >= $game->max_players) {
            return response()->json(['message' => 'Game is full'], 400);
        }

        $game->users()->attach($user->id);
        event(new GameStatusUpdated($game));

        $this->triggerGameUpdate($gameId, 'player.joined', [
            'userId' => auth()->id(),
            'userName' => auth()->user()->name,
            'timestamp' => now()->toISOString()
        ]);

        return response()->json(['success' => true]);
    }

    public function leave($gameId)
    {
        $game = Games::findOrFail($gameId);
        $user = auth()->user();

        if (!$game->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are not in this game'], 400);
        }

        $game->users()->detach($user->id);
        event(new GameStatusUpdated($game));

        $this->triggerGameUpdate($gameId, 'player.left', [
            'userId' => auth()->id(),
            'userName' => auth()->user()->name,
            'timestamp' => now()->toISOString()
        ]);

        return response()->json(['success' => true]);
    }

    public function getScores(Request $request)
    {
        $gameId = $request->gameId;
        $page = $request->query('page', 1); // Get page number from request
        
        $gameScores = $this->gamesService->getGameScores($gameId, $page);
    
        Log::info('Game Scores:', ['gameScores' => $gameScores]);
    
        return response()->json($gameScores);
    }

    public function show($gameId)
    {
        $game = Games::with('users')->find($gameId);

        if (!$game) {
            return response()->json(['message' => 'Game not found'], 404);
        }

        return response()->json($game);
    }


    public function showRoom($gameId, $userId)
    {
        // Fetch the game details and user info
        $gameDetails = Games::findOrFail($gameId);
        $gameType = $this->gamesService->getGameType($gameDetails);
        $userDetails = User::findOrFail($userId);

        $gameQuestions = $this->gamesService->getGameQuestions($gameDetails);
        return Inertia::render('Dashboard/AIGame/Room/Index', [
            'gameId' => $gameId,
            'userId' => $userId,
            'game' => $gameDetails,
            'maxPlayers' => $gameDetails->max_players,
            'userDetails' => $userDetails,
            'gameQuestions' => $gameQuestions, // plural now
            'gameType' => $gameType,
            'auth' => ['user' => auth()->user()],
        ]);
    }

        /**
     * Get the average scores for players in a specific game.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getScoreTrendStats(int $gameId)
    {
        $players = $this->gamesService->playerAverages($gameId);
        $totalGameScore = $this->gamesService->totalScore($gameId);

        return response()->json([
            'players' => $players,
            'totalScore' => $totalGameScore,
        ]);
    }


    public function submitAnswer(Request $request)
    {
        $request->validate([
            'answers' => 'required|array',        // Expect an array
            'answers.*' => 'required|string',     // Each answer must be a string
        ]);

        // Call GamesService with the answers array
        $sessionId = $this->gamesService->submitAnswers($request->gameId, $request->answers);

        return response()->json([
            'success' => true,
            'message' => 'Game completed successfully!',
            'session_id' => $sessionId,
        ]);
    }

    public function start(Games $game)
    {
        $game->start(); // The method you already added in the model

        $this->triggerGameUpdate($game->id, 'game.started', [
            'userId' => auth()->id(),
            'userName' => auth()->user()->name,
            'timestamp' => now()->toISOString()
        ]);

        return response()->json(['success' => true, 'status' => $game->status]);
    }

    public function getAllScores($gameId)
    {
        $allScores = $this->gamesService->getAllGameScores($gameId);
        
        return response()->json($allScores);
    }

    
    public function getQuestionAverages($gameId)
    {
        try {
            // Get all scores for this game with user data, ordered by creation time
            $scores = GameScore::where('game_id', $gameId)
                ->with('user')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json($scores);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch question averages'], 500);
        }
    }

    // Alternative method if you want to return processed trend data
    public function getScoreTrends($gameId)
    {
        try {
            $scores = GameScore::where('game_id', $gameId)
                ->with('user')
                ->orderBy('created_at', 'asc')
                ->get();

            // Group by user and create trend data
            $trends = [];
            foreach ($scores as $score) {
                $userName = $score->user->name ?? 'Anonymous';
                
                if (!isset($trends[$userName])) {
                    $trends[$userName] = [];
                }
                
                $trends[$userName][] = [
                    'x' => $score->created_at->timestamp * 1000, // Convert to JS timestamp
                    'y' => $score->score
                ];
            }

            // Convert to chart series format
            $series = [];
            foreach ($trends as $userName => $data) {
                $series[] = [
                    'name' => $userName,
                    'data' => $data
                ];
            }

            return response()->json($series);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch score trends'], 500);
        }
    }

    public function getPlayers($gameId)
    {
        $game = Games::with('users')->findOrFail($gameId);
        return response()->json($game->users);
    }


    /**
     * MULTIPLAYER
     */


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
            
            \Log::info('Pusher event triggered successfully', [
                'channel' => "game.{$gameId}",
                'event' => $eventType,
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Pusher trigger error: ' . $e->getMessage(), [
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
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $userId = $request->user()->id;
        $userName = $request->user()->name;
        $requiredCount = (int) $request->requiredCount;

        // Track ready players in cache (or use DB if preferred)
        $cacheKey = "game:{$game->id}:readyPlayers";
        $readyPlayers = Cache::get($cacheKey, []);

        // Add the player if not already marked ready
        if (!in_array($userId, $readyPlayers)) {
            $readyPlayers[] = $userId;
            Cache::put($cacheKey, $readyPlayers, now()->addMinutes(30));
        }

        $readyCount = count($readyPlayers);

        // Broadcast to others
        broadcast(new PlayerReady($game->id, $userId, $userName, $readyCount, $requiredCount))->toOthers();

        // Check if all players are ready
        if (count($readyPlayers) >= $requiredCount) {
            $game->status = 'in_progress'; // update DB game status
            $game->save();

            return response()->json(['status' => 'started']);
        }

        return response()->json(['status' => 'waiting']);
    }

    public function broadcast(Request $request, $gameId)
    {
        $event = $request->input('event');
        $data = $request->input('data');
        
        // Validate the event and data
        if (!$event || !$data) {
            return response()->json(['error' => 'Event and data are required'], 400);
        }
        
        // Trigger the Pusher event
        $this->triggerGameUpdate($gameId, $event, $data);
        
        return response()->json(['success' => true]);
    }



}
