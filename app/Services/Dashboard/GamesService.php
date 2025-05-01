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

    public function getGameScores($gameId, $page = 1, $perPage = 5)
    {
        $scores = GameScore::query()
            ->join('users', 'game_scores.player_id', '=', 'users.id')
            ->where('game_scores.game_id', $gameId)
            ->orderBy('game_scores.created_at', 'desc')
            ->select(
                'game_scores.id',
                'game_scores.session_id',
                'game_scores.player_id as user_id',
                'users.name as user_name',
                'game_scores.game_id',
                'game_scores.score',
                'game_scores.created_at'
            )
            ->paginate($perPage, ['*'], 'page', $page);
    
        // Transform the collection to add "user" object
        $scores->getCollection()->transform(function ($score) {
            $score->user = [
                'id' => $score->user_id,
                'name' => $score->user_name,
            ];
            unset($score->user_name); // optional: clean up
            return $score;
        });
    
        return $scores;
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

    public function getGameQuestion(Games $game): ?GameQuestion
    {
        return $game->gameType->gameQuestions()->first();
    }




    public function submitAnswers($gameId, $answer)
    {
        // Retrieve the game and its players
        $game = Games::findOrFail($gameId);

        $gameTypeQuestion = $this->getGameQuestion($game);

        Log::info('Game ID:', ['gameId' => $gameId]);
        Log::info('Answer:', ['answer' => $answer]);
        Log::info('Game Type:', ['gameType' => $game->gameType]);
        Log::info('Game Type ID:', ['gameTypeId' => $game->game_type_id]);

        $players = $game->users;

        Log::info('$gameTypeQuestion:', ['gameTypeQuestion' => $gameTypeQuestion]);
        Log::info('$answer:', ['answer' => $answer]);

        if ((int) $answer === $gameTypeQuestion->answer) {
            $scoreAwarded = $gameTypeQuestion->score_awarded ?? 0;
        } else {
            $scoreAwarded = 0;
        } 

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