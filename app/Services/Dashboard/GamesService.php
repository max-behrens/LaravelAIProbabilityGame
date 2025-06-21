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

    public function getGameQuestions(Games $game)
    {
        return $game->gameType->gameQuestions;
    }


    public function getGameType(Games $game): ?GameType
    {
        return $game->gameType;
    }



    public function submitAnswers($gameId, array $answers)
{
    $game = Games::findOrFail($gameId);
    $gameQuestions = $game->gameType->gameQuestions()->get();
    $currentUser = auth()->user();
    $sessionId = Str::uuid()->toString();

    $answerJson = [];
    $totalScore = 0;

    foreach ($gameQuestions as $index => $question) {
        $submittedAnswer = $answers[$index] ?? null;
        $isCorrect = $submittedAnswer !== null && strtolower(trim($submittedAnswer)) === strtolower(trim($question->answer));
        $scoreAwarded = $isCorrect ? ($question->score_awarded ?? 0) : 0;

        $answerJson[$question->id] = [
            'question_number' => $index + 1,
            'question' => $question->question,
            'submitted' => $submittedAnswer,
            'correct_answer' => $question->answer,
            'is_correct' => $isCorrect,
            'score_awarded' => $scoreAwarded
        ];

        $totalScore += $scoreAwarded;
    }

    // Now create ONE GameScore entry per user/game/session
    GameScore::create([
        'game_id' => $game->id,
        'player_id' => $currentUser->id,
        'answer' => null, // optional legacy field
        'answer_json' => json_encode($answerJson),
        'session_id' => $sessionId,
        'score' => $totalScore,
    ]);

    Log::info('Submitted answers for user', [
        'user_id' => $currentUser->id,
        'session_id' => $sessionId,
        'total_score' => $totalScore,
        'answers_count' => count($answers)
    ]);

    return $sessionId;
}


    // Add this method to your GamesService class
    public function getAllGameScores($gameId)
    {
        return GameScore::query()
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
                'game_scores.answer_json',
                'game_scores.created_at'
            )
            ->get()
            ->map(function ($score) {
                $score->user = [
                    'id' => $score->user_id,
                    'name' => $score->user_name,
                ];
                unset($score->user_name);
                return $score;
            });
    }

    /**
     * Get score trends for chart visualization
     *
     * @param int $gameId
     * @return array
     */
    public function getScoreTrends($gameId)
    {
        $scores = GameScore::query()
            ->join('users', 'game_scores.player_id', '=', 'users.id')
            ->where('game_scores.game_id', $gameId)
            ->orderBy('game_scores.created_at', 'asc')
            ->select(
                'game_scores.score',
                'game_scores.created_at',
                'users.name as user_name'
            )
            ->get();

        // Group by user and create trend data
        $trends = [];
        foreach ($scores as $score) {
            $userName = $score->user_name ?? 'Anonymous';
            
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

        return $series;
    }

}