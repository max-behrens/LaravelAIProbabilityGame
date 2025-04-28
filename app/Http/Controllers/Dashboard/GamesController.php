<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Games;
use App\Models\User;
use App\Events\GameStatusUpdated;
use Inertia\Inertia;

class GamesController extends Controller
{
    public function index()
    {
        $games = Games::with(['users' => function($query) {
            $query->select('users.id', 'name'); // Only select necessary fields
        }])->withCount('users as players_count')->get();
        
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

    public function showRoom($game, $user)
    {
        // Fetch the game details and user info
        $gameDetails = Games::findOrFail($game);
        $userDetails = User::findOrFail($user);
    
        // Return using Inertia
        return Inertia::render('Dashboard/AIGame/Room/Index', [
            'gameId' => $game,
            'userId' => $user,
            'gameDetails' => $gameDetails,
            'userDetails' => $userDetails
        ]);
    }

}
