<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Games;
use App\Models\User;
use App\Events\GameStatusUpdated;

class GamesController extends Controller
{
    public function index()
    {
        return Games::withCount('users as players_count')->get();
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
}
