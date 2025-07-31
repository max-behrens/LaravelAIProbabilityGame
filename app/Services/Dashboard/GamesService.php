<?php

namespace App\Services\Dashboard;

use App\Models\GameScore;
use App\Models\AIScore;
use App\Models\Games;
use App\Models\GameType;
use App\Models\GameQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
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

    public function submitAnswers($gameId, $userId, array $answers, $sessionId)
    {
        Log::debug('Submitting answers', ['gameId' => $gameId, 'answers' => $answers]);

        $game = Games::findOrFail($gameId);
        $gameQuestions = $game->gameType->gameQuestions()->get();

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

        }

        return $totalScore;
    }

    public function totalScoreByGameType($gameTypeId)
    {
        $gameType = GameType::findOrFail($gameTypeId);
        $gameQuestions = $gameType->gameQuestions()->get();

        $totalScore = 0;

        foreach ($gameQuestions as $question) {
            $totalScore += $question->score_awarded;

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

    /**
     * Methods to retrieve cumulative scores for each player across all games to the dashboard.
     */
    public function getCumulativeLineGraphData(?int $gameTypeId = null, ?string $startDate = null, ?string $endDate = null, ?int $userId = null)
    {
        $query = GameScore::query()
            ->select(
                'game_scores.created_at',
                'game_scores.player_id',
                'game_scores.score',
                'game_scores.session_id',
                'ai_scores.created_at as ai_created_at',
                'ai_scores.score as ai_score',
                'users.name as player_name',
                'games.id as game_id',
                'game_types.id as game_type_id',
                'game_types.name as game_name',

            )
            ->join('users', 'game_scores.player_id', '=', 'users.id')
            ->join('games', 'game_scores.game_id', '=', 'games.id')
            ->join('game_types', 'games.game_type_id', '=', 'game_types.id')
            ->leftJoin('ai_scores', function($join) { // Use leftJoin to include game scores without AI data
                $join->on('game_scores.session_id', '=', 'ai_scores.session_id')
                     ->on('game_scores.game_id', '=', 'ai_scores.game_id'); // Join on game_id too if applicable
            })
            ->orderBy('users.name')
            ->orderBy('game_scores.created_at');

        // Filter by game type instead of individual game
        if ($gameTypeId !== null) {
            $query->where('games.game_type_id', $gameTypeId);
        }

        if ($startDate) {
            $query->whereDate('game_scores.created_at', '>=', Carbon::parse($startDate));
        }

        if ($endDate) {
            $query->whereDate('game_scores.created_at', '<=', Carbon::parse($endDate));
        }

        if ($userId !== null) {
            $query->where('game_scores.player_id', $userId);
        }

        $results = $query->get();

        $series = [];
        $playerData = [];

        foreach ($results as $row) {
            $playerId = $row->player_id;
            $playerName = $row->player_name;

            if (!isset($playerData[$playerId])) {
                $playerData[$playerId] = [
                    'name' => $playerName,
                    'scores' => []
                ];
            }

            $playerData[$playerId]['scores'][] = [
                'date' => $row->created_at,
                'score' => $row->score,
                'game_id' => $row->game_id,
                'game_type_id' => $row->game_type_id,
                'game_name' => $row->game_name,
                'session_id' => $row->session_id,
                'ai_score' => $row->ai_score,
                'ai_date' => $row->ai_date
            ];
        }

        if (count($playerData) > 1) {
            usort($playerData, fn($a, $b) => strcmp($a['name'], $b['name']));
        }

        foreach ($playerData as $playerInfo) {
            $seriesData = [];

            foreach ($playerInfo['scores'] as $scoreData) {
                $timestamp = Carbon::parse($scoreData['date'])->timestamp * 1000;

                $truncatedSessionId = substr($scoreData['session_id'], 0, 10) . '...';

                $seriesData[] = [
                    'x' => $timestamp,
                    'y' => $scoreData['score'],
                    'meta' => [
                        'game_id' => $scoreData['game_id'],
                        'game_type_id' => $scoreData['game_type_id'],
                        'game_name' => $scoreData['game_name'],
                        'ai_score' => $scoreData['ai_score'],
                        'session_id' => $truncatedSessionId
                    ]
                ];
            }

            $series[] = [
                'name' => $playerInfo['name'],
                'data' => $seriesData
            ];
        }

        return $series;
    }


    /**
     * Get game sessions data for heatmap display - grouped by session_id
     * Returns sessions grouped by session_id with all players and AI data combined
     */
    public function getGameSessionsHeatmapData(?int $gameTypeId = null, ?string $startDate = null, ?string $endDate = null, ?int $userId = null)
    {
        $query = GameScore::query()
            ->select(
                'game_scores.session_id',
                'game_scores.created_at',
                'game_scores.player_id',
                'game_scores.score as total_score',
                'game_scores.answer_json',
                'users.name as player_name',
                'games.id as game_id',
                'game_types.name as game_name',
                'ai_scores.score as ai_score'
            )
            ->join('users', 'game_scores.player_id', '=', 'users.id')
            ->join('games', 'game_scores.game_id', '=', 'games.id')
            ->join('game_types', 'games.game_type_id', '=', 'game_types.id')
            ->leftJoin('ai_scores', function($join) {
                $join->on('game_scores.session_id', '=', 'ai_scores.session_id')
                    ->on('game_scores.game_id', '=', 'ai_scores.game_id');
            })
            ->orderBy('game_scores.created_at', 'desc');

        // Apply filters
        if ($gameTypeId !== null) {
            $query->where('games.game_type_id', $gameTypeId);
        }
        if ($startDate) {
            $query->whereDate('game_scores.created_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $query->whereDate('game_scores.created_at', '<=', Carbon::parse($endDate));
        }
        if ($userId !== null) {
            $query->where('game_scores.player_id', $userId);
        }

        // Get all results
        $results = $query->get();

        // Group by session_id to combine all players and AI data
        $groupedSessions = $results->groupBy('session_id');
        
        $data = $groupedSessions->map(function ($sessionGroup, $sessionId) {
            // Get the first record for session-level data
            $firstRecord = $sessionGroup->first();
            
            // Collect all players in this session
            $players = $sessionGroup->map(function ($record) {
                $questionsCount = 0;
                $correctAnswers = 0;

                if ($record->answer_json) {
                    $answers = is_string($record->answer_json)
                        ? json_decode($record->answer_json, true)
                        : $record->answer_json;

                    if (is_array($answers)) {
                        $questionsCount = count($answers);
                        $correctAnswers = collect($answers)->where('is_correct', true)->count();
                    }
                }

                return [
                    'player_id' => $record->player_id,
                    'player_name' => $record->player_name,
                    'total_score' => $record->total_score,
                    'questions_count' => $questionsCount,
                    'correct_answers' => $correctAnswers,
                ];
            })->values()->toArray();

            // Calculate combined session score (sum of all player scores)
            $combinedScore = $sessionGroup->sum('total_score');
            
            // Get AI score (should be the same across all records in the group)
            $aiScore = $firstRecord->ai_score;
            
            // Add AI to players list if AI score exists
            if ($aiScore !== null) {
                $players[] = [
                    'player_id' => 'ai',
                    'player_name' => 'AI',
                    'total_score' => $aiScore,
                    'questions_count' => 0, // AI questions count would need separate logic if needed
                    'correct_answers' => 0,
                ];
                $combinedScore += $aiScore;
            }

            $truncatedSessionId = substr($sessionId, 0, 10) . '...';

            return [
                'session_id' => $sessionId,
                'truncated_session_id' => $truncatedSessionId,
                'created_at' => $firstRecord->created_at,
                'game_id' => $firstRecord->game_id,
                'game_name' => $firstRecord->game_name,
                'combined_score' => $combinedScore, // Total score for the entire session
                'ai_score' => $aiScore,
                'players' => $players, // Array of all players in this session
                'player_count' => count($players),
            ];
        })->values();

        // Return all data without pagination
        return [
            'data' => $data,
            'total' => $data->count(),
        ];
    }

    /**
     * Get detailed session information including questions and answers
     * Added extensive debugging for data structure analysis
     */
    public function getSessionDetails(string $sessionId)
    {
        // Fetch all game scores (player answers) and joined info
        $gameScores = GameScore::query()
            ->select(
                'game_scores.*',
                'users.name as player_name',
                'games.id as game_id',
                'game_scores.answer_json as game_answer_json',
                'game_types.id as game_type_id',
                'game_types.name as game_name',
                'ai_scores.score as ai_score'
            )
            ->join('users', 'game_scores.player_id', '=', 'users.id')
            ->join('games', 'game_scores.game_id', '=', 'games.id')
            ->join('game_types', 'games.game_type_id', '=', 'game_types.id')
            ->leftJoin('ai_scores', function($join) {
                $join->on('ai_scores.session_id', '=', 'game_scores.session_id')
                    ->on('ai_scores.game_id', '=', 'game_scores.game_id');
            })
            ->where('game_scores.session_id', $sessionId)
            ->get();

        if ($gameScores->isEmpty()) {
            return null;
        }

        $first = $gameScores->first();

        if (!$first) {
            Log::warning('Empty result when accessing first game score', ['session_id' => $sessionId]);
            return null;
        }

        // Get list of all players
        $allPlayers = GameScore::where('session_id', $sessionId)
            ->join('users', 'game_scores.player_id', '=', 'users.id')
            ->select(
                'users.name as player_name',
                'game_scores.player_id',
                'game_scores.score as total_score'
            )
            ->get();

        // Group player answers per question
        $playerAnswersByQuestion = [];

        foreach ($gameScores as $score) {
            $playerName = $score->player_name;
            $decodedOnce = json_decode($score->game_answer_json, true);
            $answerData = is_string($decodedOnce) ? json_decode($decodedOnce, true) : $decodedOnce;

            if (is_array($answerData)) {
                foreach ($answerData as $qNum => $answer) {
                    $questionNumber = $answer['question_number'] ?? $answer['question_id'] ?? $qNum;

                    $playerAnswersByQuestion[$questionNumber]['question'] = $answer['question'] ?? $answer['question_text'] ?? 'Unknown Question';

                    $playerAnswersByQuestion[$questionNumber]['answers'][] = [
                        'player_name' => $playerName,
                        'submitted' => $answer['submitted'] ?? $answer['user_answer'] ?? $answer['selected_answer'] ?? null,
                        'is_correct' => $answer['is_correct'] ?? $answer['correct'] ?? false,
                        'score_awarded' => $answer['score_awarded'] ?? $answer['points'] ?? $answer['score'] ?? 0
                    ];
                }
            } else {
                Log::warning('Player answer JSON could not be decoded into array', [
                    'session_id' => $sessionId,
                    'player_name' => $playerName,
                    'raw' => $score->game_answer_json,
                    'decoded' => $answerData
                ]);
            }

        }

        // Get AI answer data
        $aiScore = AiScore::where('session_id', $first->session_id)
            ->where('game_id', $first->game_id)
            ->first();

        if ($aiScore && $aiScore->answer_json) {
            $aiAnswerData = json_decode($aiScore->answer_json, true);

            if (is_array($aiAnswerData)) {
                foreach ($aiAnswerData as $qNum => $answer) {
                    $questionNumber = $answer['question_number'] ?? $answer['question_id'] ?? $qNum;

                    $playerAnswersByQuestion[$questionNumber]['ai'] = [
                        'submitted' => $answer['submitted'] ?? $answer['selected_answer'] ?? $answer['ai_answer'] ?? null,
                        'is_correct' => $answer['is_correct'] ?? $answer['correct'] ?? null,
                        'score_awarded' => $answer['score_awarded'] ?? $answer['points'] ?? $answer['score'] ?? null
                    ];
                }
            } else {
                Log::warning('AI answer JSON could not be decoded into array', [
                    'session_id' => $sessionId,
                    'raw' => $aiScore->answer_json,
                    'decoded' => $aiAnswerData
                ]);
            }
        }

        // Build final question list
        $questions = [];
        foreach ($playerAnswersByQuestion as $qNum => $data) {
            $questions[] = [
                'question_number' => $qNum,
                'question_text' => $data['question'],
                'player_answers' => $data['answers'],
                'ai_answer' => $data['ai']['submitted'] ?? null,
                'ai_is_correct' => $data['ai']['is_correct'] ?? null,
                'ai_score' => $data['ai']['score_awarded'] ?? null,
            ];
        }

        // Sort by question number
        usort($questions, fn($a, $b) => ($a['question_number'] ?? 0) - ($b['question_number'] ?? 0));
        

        return [
            'session_id' => substr($first->session_id, 0, 10) . '...',
            'player_name' => $first->player_name,
            'game_name' => $first->game_name,
            'total_score' => $this->totalScoreByGameType($first->game_type_id),
            'ai_score' => $aiScore->score ?? null,
            'created_at' => $first->created_at,
            'players' => $allPlayers,
            'questions' => $questions,
            'debug_info' => [
                'player_answers_count' => count($playerAnswersByQuestion),
                'ai_answers_count' => isset($aiAnswerData) ? count($aiAnswerData) : 0,
                'total_questions' => count($questions),
            ]
        ];
    }


    public function getCumulativeBarGraphData(): array
    {
        Log::info('Fetching cumulative scores by player across all games');

        $gameScores = GameScore::query()
            ->join('users', 'game_scores.player_id', '=', 'users.id')
            ->orderBy('game_scores.created_at', 'asc')
            ->select(
                'users.name as player_name',
                'game_scores.score',
                'game_scores.created_at'
            )
            ->get();

        $playerCumulativeScores = [];
        $playerCurrentScores = [];

        foreach ($gameScores as $score) {
            $playerName = $score->player_name;
            $currentScore = $score->score;
            $timestamp = $score->created_at->timestamp * 1000; // ApexCharts expects milliseconds

            // Initialize if player not seen before
            if (!isset($playerCurrentScores[$playerName])) {
                $playerCurrentScores[$playerName] = 0;
            }
            if (!isset($playerCumulativeScores[$playerName])) {
                $playerCumulativeScores[$playerName] = [];
            }

            $playerCurrentScores[$playerName] += $currentScore;
            $playerCumulativeScores[$playerName][] = [
                'x' => $timestamp,
                'y' => $playerCurrentScores[$playerName],
            ];
        }

        $series = [];
        foreach ($playerCumulativeScores as $playerName => $data) {
            $series[] = [
                'name' => $playerName,
                'data' => $data,
            ];
        }

        Log::info('Cumulative scores by player generated', ['series' => $series]);

        return $series;
    }
}