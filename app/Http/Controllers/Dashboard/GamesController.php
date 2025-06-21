<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Games;
use App\Models\User;
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

        Log::info('Game Questions:', ['gameQuestions' => $gameQuestions]);

        return Inertia::render('Dashboard/AIGame/Room/Index', [
            'gameId' => $gameId,
            'userId' => $userId,
            'gameTitle' => $gameDetails->title,
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
    public function getPlayerAverages(int $gameId)
    {
        $players = $this->gamesService->playerAverages($gameId);

        return response()->json($players);
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


}
