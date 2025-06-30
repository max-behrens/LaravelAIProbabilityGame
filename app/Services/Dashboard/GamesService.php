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
        Log::debug('Fetching paginated game scores', ['gameId' => $gameId, 'page' => $page, 'perPage' => $perPage]);

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

        Log::debug('Fetched scores', ['scores' => $scores->items()]);

        $scores->getCollection()->transform(function ($score) {
            $score->user = [
                'id' => $score->user_id,
                'name' => $score->user_name,
            ];
            Log::debug('Transformed score', ['score' => $score]);
            unset($score->user_name);
            return $score;
        });

        return $scores;
    }

    public function playerAverages(int $gameId)
    {
        Log::debug('Calculating player averages', ['gameId' => $gameId]);

        $averages = DB::table('users')
            ->join('game_scores', 'users.id', '=', 'game_scores.player_id')
            ->where('game_scores.game_id', $gameId)
            ->select('users.name', DB::raw('AVG(game_scores.score) as average_score'))
            ->groupBy('users.id', 'users.name')
            ->get();

        Log::debug('Player averages result', ['averages' => $averages]);

        return $averages;
    }

    public function getGameQuestions(Games $game)
    {
        Log::debug('Fetching game questions', ['gameId' => $game->id]);

        $questions = $game->gameType->gameQuestions;

        Log::debug('Fetched questions', ['questions' => $questions]);

        return $questions;
    }

    public function getGameType(Games $game): ?GameType
    {
        Log::debug('Fetching game type', ['gameId' => $game->id]);

        $type = $game->gameType;

        Log::debug('Fetched game type', ['type' => $type]);

        return $type;
    }

    public function submitAnswers($gameId, $userId, array $answers)
    {
        Log::debug('Submitting answers', ['gameId' => $gameId, 'answers' => $answers]);

        $game = Games::findOrFail($gameId);
        $gameQuestions = $game->gameType->gameQuestions()->get();

        // Generate session ID
        $sessionId = Str::uuid()->toString();

        Log::debug('Session created', ['sessionId' => $sessionId, 'user' => $userId]);

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

            Log::debug('Processed question', [
                'question_id' => $question->id,
                'submittedAnswer' => $submittedAnswer,
                'isCorrect' => $isCorrect,
                'scoreAwarded' => $scoreAwarded
            ]);
        }

        GameScore::create([
            'game_id' => $game->id,
            'player_id' => $userId,
            'answer' => null,
            'answer_json' => json_encode($answerJson),
            'session_id' => $sessionId,
            'score' => $totalScore,
        ]);

        Log::info('Submitted answers for user', [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'total_score' => $totalScore,
            'answers_count' => count($answers),
            'answers' => $answerJson
        ]);

        return $sessionId;
    }

    public function totalScore($gameId)
    {
        Log::debug('Calculating total score for game', ['gameId' => $gameId]);

        $game = Games::findOrFail($gameId);
        $gameQuestions = $game->gameType->gameQuestions()->get();

        $totalScore = 0;

        foreach ($gameQuestions as $question) {
            $totalScore += $question->score_awarded;
            Log::debug('Adding score', [
                'question_id' => $question->id,
                'score_awarded' => $question->score_awarded,
                'running_total' => $totalScore
            ]);
        }

        return $totalScore;
    }

    public function getAllGameScores($gameId)
    {
        Log::debug('Fetching all game scores', ['gameId' => $gameId]);

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
                'game_scores.answer_json',
                'game_scores.created_at'
            )
            ->get()
            ->map(function ($score) {
                $score->user = [
                    'id' => $score->user_id,
                    'name' => $score->user_name,
                ];
                Log::debug('Mapped score', ['score' => $score]);
                unset($score->user_name);
                return $score;
            });

        return $scores;
    }

    public function getScoreTrends($gameId)
    {
        Log::debug('Generating score trends', ['gameId' => $gameId]);

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

        Log::debug('Fetched scores for trends', ['scores' => $scores]);

        $trends = [];
        foreach ($scores as $score) {
            $userName = $score->user_name ?? 'Anonymous';

            if (!isset($trends[$userName])) {
                $trends[$userName] = [];
            }

            $trends[$userName][] = [
                'x' => $score->created_at->timestamp * 1000,
                'y' => $score->score
            ];

            Log::debug('Score trend point added', [
                'user' => $userName,
                'x' => $score->created_at->timestamp * 1000,
                'y' => $score->score
            ]);
        }

        $series = [];
        foreach ($trends as $userName => $data) {
            $series[] = [
                'name' => $userName,
                'data' => $data
            ];
        }

        Log::debug('Score trends generated', ['series' => $series]);

        return $series;
    }
}
