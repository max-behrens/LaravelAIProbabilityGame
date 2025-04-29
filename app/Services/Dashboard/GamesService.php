<?php

namespace App\Services\Dashboard;

use App\Models\GameScore;
use App\Models\Games;
use App\Models\GameType;
use App\Models\GameQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;


class GamesService
{

    public function getGameScores($gameId)
    {
        return GameScore::with('user:id,name') // Only fetch user id and name
            ->where('game_id', $gameId)
            ->orderBy('created_at', 'desc') // Most recent first
            ->get(['id', 'session_id', 'player_id', 'game_id', 'score', 'created_at']); // Limit fields returned
    }
    

        /**
     * Get the average scores of players for a specific game.
     *
     * @param  int  $gameId
     * @return \Illuminate\Support\Collection
     */
    public function playerAverages(int $gameId)
    {
        return DB::table('users')
            ->join('game_scores', 'users.id', '=', 'game_scores.player_id')
            ->where('game_scores.game_id', $gameId)
            ->select('users.name', DB::raw('AVG(game_scores.score) as average_score'))
            ->groupBy('users.id', 'users.name')
            ->get();
    }

    public function submitAnswers($gameId, $answer)
    {
        // Retrieve the game and its players
        $game = Games::findOrFail($gameId);

        Log::info('Game ID:', ['gameId' => $gameId]);
        Log::info('Answer:', ['answer' => $answer]);
        Log::info('Game Type:', ['gameType' => $game->gameType]);
        Log::info('Game Type ID:', ['gameTypeId' => $game->game_type_id]);
        Log::info('Game Type Score Awarded:', ['scoreAwarded' => $game->gameType->score_awarded]);


        $scoreAwarded = $game->gameType->score_awarded ?? 0;

        $players = $game->users; // assuming the players are related via a 'users' relationship

        // Generate a new session ID
        $sessionId = Str::uuid()->toString();

        // Loop through players and insert a row for each
        foreach ($players as $player) {
            GameScore::create([
                'game_id' => $game->id,
                'player_id' => $player->id,
                'answer' => $answer,
                'session_id' => $sessionId,
                'score' => $scoreAwarded,
            ]);
        }

        return $sessionId;
    }
}