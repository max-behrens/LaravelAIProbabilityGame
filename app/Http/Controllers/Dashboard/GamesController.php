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

    public function showRoom($gameId, $userId)
    {
        // Fetch the game details and user info
        $gameDetails = Games::findOrFail($gameId);
        $gameType = $this->gamesService->getGameType($gameDetails);
        $userDetails = User::findOrFail($userId);

        $gameQuestion = $this->gamesService->getGameQuestion($gameDetails);

        // Return using Inertia
        return Inertia::render('Dashboard/AIGame/Room/Index', [
            'gameId' => $gameId,
            'userId' => $userId,
            'gameTitle' => $gameDetails->title,
            'userDetails' => $userDetails,
            'gameQuestion' => $gameQuestion,
            'gameType' => $gameType,
        ]);
    }

        /**
     * Get the average scores for players in a specific game.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPlayerAverages(Request $request)
    {
        $gameId = $request->query('gameId');

        $players = $this->gamesService->playerAverages($gameId);

        return response()->json($players);
    }

    public function submitAnswer(Request $request)
    {
        $request->validate([
            'gameId' => 'required|exists:games,id',
            'answer' => 'required|string',
        ]);

        // Call GamesService to handle the score submission
        $sessionId = $this->gamesService->submitAnswers($request->gameId, $request->answer);

        return response()->json([
            'success' => true,
            'message' => 'Game completed successfully!',
            'session_id' => $sessionId,
        ]);
    }

}
