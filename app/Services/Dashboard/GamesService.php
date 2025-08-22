<?php

namespace App\Services\Dashboard;

use App\Models\GameScore;
use App\Models\AIScore;
use App\Models\Games;
use App\Models\GameType;
use App\Models\GameQuestion;
use App\Models\GameTypeDifficulty;
use App\Models\GameTypeCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GamesService
{

    public function getIndexGames(
        $page = 1, 
        ?string $startDate = null, 
        ?string $endDate = null, 
        ?array $userIds = null, 
        bool $andUsers = false, 
        ?int $gameType = null,
        $perPage = 5)
    {

        $gameType = (int) $gameType;

        $query = \DB::table('games')
            ->leftJoin('game_scores', 'game_scores.game_id', '=', 'games.id')
            ->leftjoin('games_user', 'games_user.game_id', '=', 'games.id')
            ->leftjoin('users', 'users.id', '=', 'games_user.user_id')
            ->leftJoin('game_types', 'game_types.id', '=', 'games.game_type_id')
            ->select(
                'games.id',
                'games.max_players',
                'game_types.name as game_type_name',
                \DB::raw('COUNT(DISTINCT games_user.user_id) as players_count')
            )
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('game_scores.created_at', [$startDate, $endDate]);
            })
            ->when(!empty($userIds), function ($q) use ($userIds, $andUsers) {
                if ($andUsers) {
                    $q->whereIn('users.id', $userIds)
                    ->groupBy('games.id', 'games.max_players', 'game_types.name')
                    ->havingRaw('COUNT(DISTINCT users.id) = ?', [count($userIds)])
                    ->havingRaw('COUNT(DISTINCT users.id) = COUNT(DISTINCT games_user.user_id)');
                } else {
                    $q->whereIn('users.id', $userIds);
                }
            })
            ->when($gameType && $gameType > 0, function ($q) use ($gameType) {
                $q->where('games.game_type_id', $gameType);
            })
            ->groupBy('games.id', 'games.max_players', 'game_types.name');
            // ->orderBy('games.created_at', 'desc');

        $games = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform collection to match previous structure (if needed)
        $games->getCollection()->transform(function ($game) {
            return [
                'id' => $game->id,
                'players_count' => $game->players_count,
                'max_players' => $game->max_players,
                'users' => [],
                'game_type_name' => $game->game_type_name,
            ];
        });

        return $games;
    }





    public function getGameScores(
        $gameId,
        $page = 1,
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $userIds = null,
        bool $andUsers = false,
        ?int $difficultyId = null,
        ?int $categoryId = null,
        $perPage = 5,
        string $sortField = 'created_at',
        string $sortDirection = 'desc')
    {
        Log::debug('Fetching paginated game scores', [
            'gameId' => $gameId, 
            'page' => $page, 
            'perPage' => $perPage,
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection
        ]);

        $query = GameScore::query()
            ->join('users', 'game_scores.player_id', '=', 'users.id')
            ->where('game_scores.game_id', $gameId)
            ->select(
                'game_scores.id',
                'game_scores.session_id',
                'game_scores.player_id as user_id',
                'users.name as user_name',
                'game_scores.game_id',
                'game_scores.score',
                'game_scores.created_at',
                'game_scores.answer_json'
            );

        if ($startDate && $endDate) {
            $query->whereBetween('game_scores.created_at', [$startDate, $endDate]);
        }

        if (!empty($userIds) && !$andUsers) {
            $query->whereIn('game_scores.player_id', $userIds);
        }

        if (!empty($userIds) && $andUsers) {
            $sessionsWithAllUsers = GameScore::query()
                ->where('game_id', $gameId)
                ->whereIn('player_id', $userIds)
                ->select('session_id')
                ->groupBy('session_id')
                ->havingRaw('COUNT(DISTINCT player_id) = ?', [count($userIds)])
                ->pluck('session_id');

            $query->whereIn('game_scores.session_id', $sessionsWithAllUsers);
        }

        if ($difficultyId !== null) {
            Log::debug('Applying difficulty filter', ['difficultyId' => $difficultyId]);
            $this->applyJsonIdFilter($query, 'game_scores', 'difficulty_id', $difficultyId);
        }

        if ($categoryId !== null) {
            Log::debug('Applying category filter', ['categoryId' => $categoryId]);
            $this->applyJsonIdFilter($query, 'game_scores', 'category_id', $categoryId);
        }

        // Apply sorting
        $validSortFields = ['score', 'created_at'];
        $validSortDirections = ['asc', 'desc'];
        
        if (in_array($sortField, $validSortFields) && in_array($sortDirection, $validSortDirections)) {
            $query->orderBy('game_scores.' . $sortField, $sortDirection);
        } else {
            // Default sorting
            $query->orderBy('game_scores.created_at', 'desc');
        }

        $scores = $query->paginate($perPage, ['*'], 'page', $page);

        Log::debug('Fetched scores raw', ['scores' => $scores->items()]);

        // Get all unique difficulty and category IDs from the scores
        $difficultyIds = [];
        $categoryIds = [];
        
        foreach ($scores->items() as $score) {
            $answerData = is_string($score->answer_json) ? json_decode($score->answer_json, true) : $score->answer_json;
            if (isset($answerData['difficulty_id'])) {
                $difficultyIds[] = $answerData['difficulty_id'];
            }
            if (isset($answerData['category_id'])) {
                $categoryIds[] = $answerData['category_id'];
            }
        }

        // Fetch difficulty and category names in bulk
        $difficulties = collect();
        $categories = collect();
        
        if (!empty($difficultyIds)) {
            $difficulties = \DB::table('game_type_difficulties')
                ->whereIn('id', array_unique($difficultyIds))
                ->pluck('name', 'id');
        }
        
        if (!empty($categoryIds)) {
            $categories = \DB::table('game_type_categories')
                ->whereIn('id', array_unique($categoryIds))
                ->pluck('name', 'id');
        }

        Log::debug('Difficulties and Categories', [
            'difficulties' => $difficulties->toArray(),
            'categories' => $categories->toArray()
        ]);

        $scores->getCollection()->transform(function ($score) use ($difficulties, $categories, $gameId) {
                Log::debug('Transforming score', ['score_id' => $score->id, 'has_answer_json' => isset($score->answer_json)]);
            $score->user = [
                'id' => $score->user_id,
                'name' => $score->user_name,
            ];

            if ($score->answer_json) {
                $answerData = is_string($score->answer_json) ? json_decode($score->answer_json, true) : $score->answer_json;

                // Use the fetched names or fall back to ID-based names
                if (isset($answerData['difficulty_id'])) {
                    $answerData['difficulty_name'] = $difficulties->get($answerData['difficulty_id']) 
                        ?? 'Difficulty #' . $answerData['difficulty_id'];
                } else {
                    $answerData['difficulty_name'] = 'N/A';
                }

                if (isset($answerData['category_id'])) {
                    $answerData['category_name'] = $categories->get($answerData['category_id']) 
                        ?? 'Category #' . $answerData['category_id'];
                } else {
                    $answerData['category_name'] = 'N/A';
                }

                 // Add max score based on difficulty
                if (isset($answerData['difficulty_id'])) {
                    $difficultyId = (int)$answerData['difficulty_id'];
                    $totalScores = $this->totalScore($gameId, null, 1);



                    switch ($difficultyId) {
                        case 1:
                            $answerData['max_score'] = $totalScores['totalEasy'];
                            break;
                        case 2:
                            $answerData['max_score'] = $totalScores['totalMedium'];
                            break;
                        case 3:
                            $answerData['max_score'] = $totalScores['totalDifficult'];
                            break;
                        default:
                            $answerData['max_score'] = $totalScores['totalEasy']; // fallback
                    }
                } else {
                    $answerData['max_score'] = $totalScores['totalEasy']; // fallback
                }

                        Log::debug('Score with max_score', ['score_id' => $score->id, 'max_score' => $answerData['max_score'], 'difficulty_id' => $answerData['difficulty_id'] ?? 'none']);

                $score->answer_json = $answerData;
            }

            unset($score->user_name);

            return $score;
        });


        return $scores;
    }


    /**
     * The leaderboard fetches all Player & all AI Scores across all games.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * 
     */
    public function getLeaderboardGames(
        $page = 1, 
        ?string $startDate = null,
        ?string $endDate = null, 
        ?array $userIds = null, 
        bool $andUsers = false, 
        ?int $gameType = null,
        $perPage = null,
        ?int $difficultyId = null,
        ?int $categoryId = null,
        bool $includeAI = false,
        ?string $searchQuery = null,
        string $sortField = 'score',
        string $sortDirection = 'desc'
    )
    {
        Log::debug('Fetching leaderboard games', [
            'page' => $page,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'userIds' => $userIds,
            'andUsers' => $andUsers,
            'gameType' => $gameType,
            'includeAI' => $includeAI,
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId,
            'searchQuery' => $searchQuery,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection
        ]);

        // Base query for user scores
        $userScoresQuery = GameScore::query()
            ->join('users', 'game_scores.player_id', '=', 'users.id')
            ->join('games', 'game_scores.game_id', '=', 'games.id')
            ->join('game_types', 'games.game_type_id', '=', 'game_types.id')
            ->select(
                'game_scores.id',
                'game_scores.session_id',
                'game_scores.player_id as user_id',
                'users.name as user_name',
                'game_scores.game_id',
                'game_scores.score',
                DB::raw("DATE_ADD(game_scores.created_at, INTERVAL 1 HOUR) as created_at"),
                'game_scores.answer_json',
                'game_types.name as game_type_name',
                DB::raw("'user' as score_type") // Add score_type for user scores
            );

        // Apply filters to user scores
        if ($startDate && $endDate) {
            $userScoresQuery->whereBetween('game_scores.created_at', [$startDate, $endDate]);
        }

        if (!empty($userIds) && !$andUsers) {
            $userScoresQuery->whereIn('game_scores.player_id', $userIds);
        }

        if (!empty($userIds) && $andUsers) {
            $sessionsWithAllUsers = GameScore::query()
                ->whereIn('player_id', $userIds)
                ->select('session_id')
                ->groupBy('session_id')
                ->havingRaw('COUNT(DISTINCT player_id) = ?', [count($userIds)])
                ->pluck('session_id');

            $userScoresQuery->whereIn('game_scores.session_id', $sessionsWithAllUsers);
        }

        if ($gameType && $gameType > 0) {
            $userScoresQuery->where('games.game_type_id', $gameType);
        }

        if ($difficultyId !== null) {
            $this->applyJsonIdFilter($userScoresQuery, 'game_scores', 'difficulty_id', $difficultyId);
        }

        if ($categoryId !== null) {
            $this->applyJsonIdFilter($userScoresQuery, 'game_scores', 'category_id', $categoryId);
        }

        if ($searchQuery) {
            $userScoresQuery->where('game_scores.session_id', 'LIKE', "%{$searchQuery}%");
        }

        // Validate sort parameters
        $validSortFields = ['score', 'created_at', 'user_name'];
        $validSortDirections = ['asc', 'desc'];
        
        if (!in_array($sortField, $validSortFields)) {
            $sortField = 'score';
        }
        if (!in_array($sortDirection, $validSortDirections)) {
            $sortDirection = 'desc';
        }

        // Always wrap in subquery for consistent structure
        if ($includeAI) {
            $aiScoresQuery = DB::table('ai_scores')
                ->join('games', 'ai_scores.game_id', '=', 'games.id')
                ->join('game_types', 'games.game_type_id', '=', 'game_types.id')
                ->select(
                    DB::raw('NULL as id'),
                    'ai_scores.session_id',
                    DB::raw('0 as user_id'),
                    DB::raw("'AI' as user_name"),
                    'ai_scores.game_id',
                    'ai_scores.score',
                    DB::raw("DATE_ADD(ai_scores.created_at, INTERVAL 1 HOUR) as created_at"),
                    'ai_scores.answer_json',
                    'game_types.name as game_type_name',
                    DB::raw("'ai' as score_type") // Add score_type for AI scores
                );

            // Apply same filters to AI scores
            if ($startDate && $endDate) {
                $aiScoresQuery->whereBetween('ai_scores.created_at', [$startDate, $endDate]);
            }

            if (!empty($userIds)) {
                if ($andUsers) {
                    $aiScoresQuery->whereIn('ai_scores.session_id', $sessionsWithAllUsers ?? []);
                } else {
                    $sessionsWithAnyUser = GameScore::query()
                        ->whereIn('player_id', $userIds)
                        ->select('session_id')
                        ->distinct()
                        ->pluck('session_id');
                    $aiScoresQuery->whereIn('ai_scores.session_id', $sessionsWithAnyUser);
                }
            }

            if ($gameType && $gameType > 0) {
                $aiScoresQuery->where('games.game_type_id', $gameType);
            }

            if ($difficultyId !== null) {
                $this->applyJsonIdFilter($aiScoresQuery, 'ai_scores', 'difficulty_id', $difficultyId);
            }

            if ($categoryId !== null) {
                $this->applyJsonIdFilter($aiScoresQuery, 'ai_scores', 'category_id', $categoryId);
            }

            if ($searchQuery) {
                $aiScoresQuery->where('ai_scores.session_id', 'LIKE', "%{$searchQuery}%");
            }

            // Create union query
            $unionQuery = $userScoresQuery->union($aiScoresQuery);
            
            // Wrap union in subquery for proper sorting
            $finalQuery = DB::table(DB::raw("({$unionQuery->toSql()}) as combined_scores"))
                ->mergeBindings($unionQuery->getQuery())
                ->orderBy($sortField, $sortDirection);
        } else {
            // For consistency, wrap user scores in subquery too
            $finalQuery = DB::table(DB::raw("({$userScoresQuery->toSql()}) as combined_scores"))
                ->mergeBindings($userScoresQuery->getQuery())
                ->orderBy($sortField, $sortDirection);
        }

        // Apply pagination
        $scores = $finalQuery->paginate($perPage, ['*'], 'page', $page);

        // Get all unique difficulty and category IDs for batch fetching names
        $difficultyIds = [];
        $categoryIds = [];
        
        foreach ($scores->items() as $score) {
            $answerData = $this->decodeAnswerJson($score->answer_json);
            if (isset($answerData['difficulty_id'])) {
                $difficultyIds[] = $answerData['difficulty_id'];
            }
            if (isset($answerData['category_id'])) {
                $categoryIds[] = $answerData['category_id'];
            }
        }

        // Fetch difficulty and category names in bulk
        $difficulties = collect();
        $categories = collect();
        
        if (!empty($difficultyIds)) {
            $difficulties = DB::table('game_type_difficulties')
                ->whereIn('id', array_unique($difficultyIds))
                ->pluck('name', 'id');
        }
        
        if (!empty($categoryIds)) {
            $categories = DB::table('game_type_categories')
                ->whereIn('id', array_unique($categoryIds))
                ->pluck('name', 'id');
        }

        // Transform the results
        $scores->getCollection()->transform(function ($score) use ($difficulties, $categories, $includeAI) {
            // Convert stdClass to array if needed
            $scoreArray = is_object($score) ? (array) $score : $score;
            
            if ($scoreArray['user_name'] === 'AI') {
                $scoreArray['user_name'] = 'AI: ';
            }

            $scoreArray['user'] = [
                'id' => $scoreArray['user_id'] ?? 0,
                'name' => $scoreArray['user_name'] ?? 'Unknown',
            ];

            if (!empty($scoreArray['answer_json'])) {
                $answerData = $this->decodeAnswerJson($scoreArray['answer_json']);

                if (is_array($answerData)) {
                    if (isset($answerData['difficulty_id'])) {
                        $answerData['difficulty_name'] = $difficulties->get($answerData['difficulty_id']) 
                            ?? 'Difficulty #' . $answerData['difficulty_id'];
                    } else {
                        $answerData['difficulty_name'] = 'N/A';
                    }

                    if (isset($answerData['category_id'])) {
                        $answerData['category_name'] = $categories->get($answerData['category_id']) 
                            ?? 'Category #' . $answerData['category_id'];
                    } else {
                        $answerData['category_name'] = 'N/A';
                    }

                    // Add max score based on difficulty for percentage calculation
                    if (isset($answerData['difficulty_id'])) {
                        $difficultyId = (int)$answerData['difficulty_id'];
                        $totalScores = $this->totalScore($scoreArray['game_id'], null, 1);

                        switch ($difficultyId) {
                            case 1:
                                $answerData['max_score'] = $totalScores['totalEasy'];
                                break;
                            case 2:
                                $answerData['max_score'] = $totalScores['totalMedium'];
                                break;
                            case 3:
                                $answerData['max_score'] = $totalScores['totalDifficult'];
                                break;
                            default:
                                $answerData['max_score'] = $totalScores['totalEasy'];
                        }
                    } else {
                        $answerData['max_score'] = 75; // fallback
                    }

                    $scoreArray['answer_json'] = $answerData;
                } else {
                    // If answer_json couldn't be decoded, set defaults
                    $scoreArray['answer_json'] = [
                        'difficulty_name' => 'N/A',
                        'category_name' => 'N/A',
                        'max_score' => 75
                    ];
                }
            } else {
                // No answer_json, set defaults
                $scoreArray['answer_json'] = [
                    'difficulty_name' => 'N/A',
                    'category_name' => 'N/A',
                    'max_score' => 75
                ];
            }

            // Clean up the user_name field
            unset($scoreArray['user_name']);

            return (object) $scoreArray;
        });

        return $scores;
    }

    // Question retrieval methods:

    public function getGameQuestionsByDifficultyAndCategory(Games $game, $difficultyId, $categoryId)
    {

        Log::debug('Fetching game questions by difficulty and category', [
            'gameId' => $game->id,
            'game_typeId' => $game->game_type_id,
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId
        ]);
        
        // Build the query
        $query = GameQuestion::where('game_type_id', $game->game_type_id)
            ->where('difficulty_id', $difficultyId)
            ->where('category_id', $categoryId)
            ->with(['difficulty', 'category']);
        
        // Log raw SQL and bindings
        Log::debug('Raw SQL query', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        // Execute query
        return $query->get();
    }

    public function getGameQuestions(Games $game)
    {
        $questions = $game->gameType->gameQuestions;

        return $questions;
    }

    public function getGameType(Games $game): ?GameType
    {
        $type = $game->gameType;

        return $type;
    }





    // Game submission methods:

    public function submitAnswers($gameId, $userId, array $answers, $sessionId, $difficultyId = null, $categoryId = null)
    {
        Log::debug('Submitting answers', [
            'gameId' => $gameId, 
            'answers' => $answers,
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId
        ]);
        
        $game = Games::findOrFail($gameId);
        
        // Build query for game questions with difficulty and category filters
        $gameQuestionsQuery = $game->gameType->gameQuestions();
        
        if ($difficultyId !== null) {
            $gameQuestionsQuery->where('difficulty_id', $difficultyId);
        }
        if ($categoryId !== null) {
            $gameQuestionsQuery->where('category_id', $categoryId);
        }
        
        $gameQuestions = $gameQuestionsQuery->get();
        
        Log::debug('Session created', ['sessionId' => $sessionId, 'user' => $userId]);

        $answerJson = [];

        // Add difficulty_id and category_id to answer_json for reference
        if ($difficultyId !== null) {
            $answerJson['difficulty_id'] = $difficultyId;
        }
        if ($categoryId !== null) {
            $answerJson['category_id'] = $categoryId;
        }

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
            'difficulty_id' => $difficultyId,
            'category_id' => $categoryId,
            'answers' => $answerJson
        ]);

        return $sessionId;
    }



    // Game Room Charts methods:

    public function playerAverages(
        int $gameId, 
        ?string $startDate = null, 
        ?string $endDate = null, 
        ?array $userIds = null, 
        bool $andUsers = false, 
        bool $excludeAI = true,
        ?int $difficultyId = null,
        ?int $categoryId = null
    )
    {
        Log::debug('Calculating player averages with filters', [
            'gameId' => $gameId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'userIds' => $userIds,
            'andUsers' => $andUsers,
            'excludeAi' => $excludeAI
        ]);

        // Base query for user averages
        $userAveragesQuery = DB::table('users')
            ->join('game_scores', 'users.id', '=', 'game_scores.player_id')
            ->where('game_scores.game_id', $gameId);

        // Apply date range filter
        if ($startDate && $endDate) {
            $userAveragesQuery->whereBetween('game_scores.created_at', [$startDate, $endDate]);
        }
        
        // Define the sessions to consider based on user filters
        $sessionsToConsider = null;
        
        // Apply user ID filter (OR logic)
        if (!empty($userIds) && !$andUsers) {
            $userAveragesQuery->whereIn('game_scores.player_id', $userIds);
            $sessionsToConsider = GameScore::query()
                ->where('game_id', $gameId)
                ->whereIn('player_id', $userIds)
                ->select('session_id')
                ->distinct()
                ->pluck('session_id');
        }

        // Handle AND logic for users
        if (!empty($userIds) && $andUsers) {
            $sessionsToConsider = GameScore::query()
                ->where('game_id', $gameId)
                ->whereIn('player_id', $userIds)
                ->select('session_id')
                ->groupBy('session_id')
                ->havingRaw('COUNT(DISTINCT player_id) = ?', [count($userIds)])
                ->pluck('session_id');
                
            $userAveragesQuery->whereIn('game_scores.session_id', $sessionsToConsider);
        }

            if ($difficultyId !== null) {
                Log::debug('Applying difficulty filter', ['difficultyId' => $difficultyId]);
                $this->applyJsonIdFilter($userAveragesQuery, 'game_scores', 'difficulty_id', $difficultyId);
            }

            if ($categoryId !== null) {
                Log::debug('Applying category filter', ['categoryId' => $categoryId]);
                $this->applyJsonIdFilter($userAveragesQuery, 'game_scores', 'category_id', $categoryId);
            }


        $userAverages = $userAveragesQuery
            ->select('users.id', 'users.name', DB::raw('AVG(game_scores.score) as average_score'))
            ->groupBy('users.id', 'users.name')
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })
            ->toArray();

        // Get count data for each user (difficulty_category combinations)
        // Always get ALL games for each user, regardless of current filters
        $userIds = array_column($userAverages, 'id');
        
        if (!empty($userIds)) {
            // Base query for counts - NO FILTERS applied here to get complete breakdown
            $countsQuery = DB::table('game_scores')
                ->where('game_id', $gameId)
                ->whereIn('player_id', $userIds);

            if ($categoryId !== null) {
                $this->applyJsonIdFilter($countsQuery, 'game_scores', 'category_id', $categoryId);
            }

            // Get raw counts data
            $countsData = $countsQuery
                ->select('player_id', 'answer_json')
                ->get();

            // Process counts by user and difficulty/category combination
            $userCounts = [];
            foreach ($countsData as $row) {
                $answerData = is_string($row->answer_json) ? json_decode($row->answer_json, true) : $row->answer_json;
                $diffId = $answerData['difficulty_id'] ?? null;
                $catId = $answerData['category_id'] ?? null;
                
                if ($diffId !== null && $catId !== null) {
                    $key = "{$diffId}_{$catId}";
                    if (!isset($userCounts[$row->player_id])) {
                        $userCounts[$row->player_id] = [];
                    }
                    $userCounts[$row->player_id][$key] = ($userCounts[$row->player_id][$key] ?? 0) + 1;
                }
            }

            // Add counts to user averages
            foreach ($userAverages as &$user) {
                $user['counts'] = $userCounts[$user['id']] ?? [];
            }

            if (($difficultyId === null && $categoryId !== null) || 
                ($difficultyId === null && $categoryId === null)) {
                
                foreach ($userAverages as &$user) {
                    $weightedMaxScore = 0;
                    $totalGames = 0;
                    
                    // Get the total scores for each difficulty
                    $gameScores = $this->totalScore($gameId, null, null);
                    $easyMax = $gameScores['totalEasy'];
                    $mediumMax = $gameScores['totalMedium']; 
                    $hardMax = $gameScores['totalDifficult'];
                    
                    if (!empty($user['counts'])) {
                        // Calculate weighted average based on actual games played
                        foreach ($user['counts'] as $key => $count) {
                            [$diffId, $catId] = explode('_', $key);
                            
                            // Apply category filter if set
                            if ($categoryId !== null && $catId != $categoryId) {
                                continue;
                            }
                            
                            // Get max score for this difficulty
                            $maxForDifficulty = match((int)$diffId) {
                                1 => $easyMax,
                                2 => $mediumMax,  
                                3 => $hardMax,
                                default => 75 // fallback
                            };
                            
                            $weightedMaxScore += ($maxForDifficulty * $count);
                            $totalGames += $count;
                        }
                        
                        if ($totalGames > 0) {
                            $user['weightedMaxScore'] = $weightedMaxScore / $totalGames;
                        } else {
                            $user['weightedMaxScore'] = 75; // fallback
                        }
                    } else {
                        $user['weightedMaxScore'] = 75; // fallback
                    }
                }
            }
        }

        // Only include AI scores if excludeAI is false
        if (!$excludeAI) {
            // AI scores
            $aiAverageQuery = DB::table('ai_scores')
                ->where('game_id', $gameId);
                
            // Apply filters to AI scores
            if ($startDate && $endDate) {
                $aiAverageQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            if ($difficultyId !== null) {
                $this->applyJsonIdFilter($aiAverageQuery, 'ai_scores', 'difficulty_id', $difficultyId);
            }

            if ($categoryId !== null) {
                $this->applyJsonIdFilter($aiAverageQuery, 'ai_scores', 'category_id', $categoryId);
            }
            
            // Only include AI scores if there are sessions that match the human player filters
            if ($sessionsToConsider) {
                $aiAverageQuery->whereIn('session_id', $sessionsToConsider);
            }

            $aiAverage = $aiAverageQuery
                ->select(DB::raw("'AI' as name"), DB::raw('AVG(score) as average_score'))
                ->first();
            
            if ($aiAverage && $aiAverage->average_score !== null) {
                // Get AI counts - apply category filter if set
                $aiCountsQuery = DB::table('ai_scores')
                    ->where('game_id', $gameId);

                // Apply category filter to AI counts if selected
                if ($categoryId !== null) {
                    $this->applyJsonIdFilter($aiCountsQuery, 'ai_scores', 'category_id', $categoryId);
                }


                $aiCountsData = $aiCountsQuery
                    ->select('answer_json')
                    ->get();

                $aiCounts = [];
                foreach ($aiCountsData as $row) {
                    $answerData = is_string($row->answer_json) ? json_decode($row->answer_json, true) : $row->answer_json;
                    $diffId = $answerData['difficulty_id'] ?? null;
                    $catId = $answerData['category_id'] ?? null;
                    
                    if ($diffId !== null && $catId !== null) {
                        $key = "{$diffId}_{$catId}";
                        $aiCounts[$key] = ($aiCounts[$key] ?? 0) + 1;
                    }
                }

                $userAverages[] = [
                    'id' => 'ai',
                    'name' => 'AI',
                    'average_score' => $aiAverage->average_score,
                    'counts' => $aiCounts,
                ];

                if (($difficultyId === null && $categoryId !== null) || 
                    ($difficultyId === null && $categoryId === null)) {
                    
                    // Get reference to the AI user we just added
                    $aiUserIndex = count($userAverages) - 1;
                    $weightedMaxScore = 0;
                    $totalGames = 0;
                    
                    $gameScores = $this->totalScore($gameId, null, null);
                    $easyMax = $gameScores['totalEasy'];
                    $mediumMax = $gameScores['totalMedium']; 
                    $hardMax = $gameScores['totalDifficult'];
                    
                    if (!empty($userAverages[$aiUserIndex]['counts'])) {
                        foreach ($userAverages[$aiUserIndex]['counts'] as $key => $count) {
                            [$diffId, $catId] = explode('_', $key);
                            
                            // Apply category filter if set
                            if ($categoryId !== null && $catId != $categoryId) {
                                continue;
                            }
                            
                            $maxForDifficulty = match((int)$diffId) {
                                1 => $easyMax,
                                2 => $mediumMax,  
                                3 => $hardMax,
                                default => 75
                            };
                            
                            $weightedMaxScore += ($maxForDifficulty * $count);
                            $totalGames += $count;
                        }
                        
                        if ($totalGames > 0) {
                            $userAverages[$aiUserIndex]['weightedMaxScore'] = $weightedMaxScore / $totalGames;
                        } else {
                            $userAverages[$aiUserIndex]['weightedMaxScore'] = 75;
                        }
                    } else {
                        $userAverages[$aiUserIndex]['weightedMaxScore'] = 75;
                    }
                }
            }
        }
        
        Log::debug('Player averages result', ['averages' => $userAverages]);
        
        return $userAverages;
    }


    public function totalScore($gameId, $difficultyId, $categoryId)
    {
        Log::debug('Calculating total score for game', [
            'gameId' => $gameId,
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId
        ]);

        $game = Games::findOrFail($gameId);

        // ---- Main totalScore (based on filters) ----
        $mainQuery = $game->gameType->gameQuestions();

        if (!is_null($difficultyId)) {
            $mainQuery->where('difficulty_id', $difficultyId);

            // Only loop through one set of questions.
            if (is_null($categoryId)) {
                $mainQuery->where('category_id', 1);
            }
        }
        if (!is_null($categoryId)) {
            $mainQuery->where('category_id', $categoryId);    
            
            // Only loop through one set of questions.
            if (is_null($difficultyId)) {
                $mainQuery->where('difficulty_id', 1);
            }
        }

        $totalScore = $mainQuery->sum('score_awarded');

        // ---- Always calculate totals per difficulty ----
        $easyTotal = $game->gameType->gameQuestions()
            ->where('category_id', 1)
            ->where('difficulty_id', 1)
            ->sum('score_awarded');

        $mediumTotal = $game->gameType->gameQuestions()
            ->where('category_id', 1)
            ->where('difficulty_id', 2)
            ->sum('score_awarded');

        $hardTotal = $game->gameType->gameQuestions()
            ->where('category_id', 1)
            ->where('difficulty_id', 3)
            ->sum('score_awarded');

        return [
            'totalScore'     => $totalScore,
            'totalEasy'      => $easyTotal,
            'totalMedium'    => $mediumTotal,
            'totalDifficult' => $hardTotal
        ];
    }


    public function getGameHeatmapScores(
        int $gameId, 
        ?string $startDate = null, 
        ?string $endDate = null, 
        ?array $userIds = null, 
        bool $andUsers = false, 
        bool $excludeAI = true, 
        ?int $difficultyId = null,
        ?int $categoryId = null
    )
    {
        Log::debug('Fetching all AI game scores with filters', [
            'gameId' => $gameId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'userIds' => $userIds,
            'andUsers' => $andUsers,
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId,
        ]);

        // Base query for user scores
        $userScoresQuery = GameScore::query()
            ->join('users', 'game_scores.player_id', '=', 'users.id')
            ->where('game_scores.game_id', $gameId)
            ->select(
                'game_scores.id',
                'game_scores.session_id',
                'game_scores.player_id as user_id',
                'users.name as user_name',
                'game_scores.game_id',
                'game_scores.score',
                'game_scores.answer_json',
                'game_scores.created_at'
            );

        // Apply date range filter
        if ($startDate && $endDate) {
            $userScoresQuery->whereBetween('game_scores.created_at', [$startDate, $endDate]);
        }

        // Apply user ID filter (OR logic)
        if (!empty($userIds) && !$andUsers) {
            $userScoresQuery->whereIn('game_scores.player_id', $userIds);
        }

        // Handle AND logic
        if (!empty($userIds) && $andUsers) {
            $sessionsWithAllUsers = GameScore::query()
                ->where('game_id', $gameId)
                ->whereIn('player_id', $userIds)
                ->select('session_id')
                ->groupBy('session_id')
                ->havingRaw('COUNT(DISTINCT player_id) = ?', [count($userIds)])
                ->pluck('session_id');

            $userScoresQuery->whereIn('game_scores.session_id', $sessionsWithAllUsers);
        }

        // Only apply difficulty filter if it's explicitly set (not null)
        if ($difficultyId !== null) {
            Log::debug('Applying difficulty filter', ['difficultyId' => $difficultyId]);
            $this->applyJsonIdFilter($userScoresQuery, 'game_scores', 'difficulty_id', $difficultyId);
        }

        if ($categoryId !== null) {
            Log::debug('Applying category filter', ['categoryId' => $categoryId]);
            $this->applyJsonIdFilter($userScoresQuery, 'game_scores', 'category_id', $categoryId);
        }


        // Get and map user scores to arrays
        $userScores = $userScoresQuery->orderBy('game_scores.created_at', 'desc')->get()->map(function ($score) {
            return [
                'id' => $score->id,
                'session_id' => $score->session_id,
                'user_id' => $score->user_id,
                'game_id' => $score->game_id,
                'score' => $score->score,
                'answer_json' => $score->answer_json,
                'created_at' => $score->created_at,
                'user' => [
                    'id' => $score->user_id,
                    'name' => $score->user_name,
                ],
            ];
        });

        // Only include AI scores if excludeAI is false
        $aiScores = collect(); // Default to empty collection
        if (!$excludeAI) {
            // AI scores query
            $aiScoresQuery = DB::table('ai_scores')
                ->where('game_id', $gameId)
                ->select(
                    DB::raw('NULL as id'),
                    'session_id',
                    DB::raw('0 as user_id'),
                    'game_id',
                    'score',
                    'answer_json',
                    'created_at'
                );

            if ($startDate && $endDate) {
                $aiScoresQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            if (!empty($userIds)) {
                if ($andUsers ?? false) {
                    $aiScoresQuery->whereIn('session_id', $sessionsWithAllUsers ?? []);
                } else {
                    $sessionsWithAnyUser = GameScore::query()
                        ->where('game_id', $gameId)
                        ->whereIn('player_id', $userIds)
                        ->select('session_id')
                        ->distinct()
                        ->pluck('session_id');

                    $aiScoresQuery->whereIn('session_id', $sessionsWithAnyUser);
                }
            }

            // Only apply difficulty filter if it's explicitly set (not null)
            if ($difficultyId !== null) {
                Log::debug('Applying difficulty filter to AI scores', ['difficultyId' => $difficultyId]);
                $this->applyJsonIdFilter($aiScoresQuery, 'ai_scores', 'difficulty_id', $difficultyId);
            }

            if ($categoryId !== null) {
                Log::debug('Applying category filter to AI scores', ['categoryId' => $categoryId]);
                $this->applyJsonIdFilter($aiScoresQuery, 'ai_scores', 'category_id', $categoryId);
            }


            $aiScores = $aiScoresQuery->get()->map(function ($score) {
                return [
                    'id' => null,
                    'session_id' => $score->session_id,
                    'user_id' => 0,
                    'game_id' => $score->game_id,
                    'score' => $score->score,
                    'answer_json' => $score->answer_json,
                    'created_at' => $score->created_at,
                    'user' => [
                        'id' => 0,
                        'name' => 'AI',
                    ],
                ];
            });
        }

        // Merge and sort
        $mergedScores = $userScores->merge($aiScores)->sortByDesc('created_at')->values();

        return $mergedScores;
    }


    /**
     * Methods to retrieve cumulative scores for each player across all games to the dashboard.
     */
    public function getCumulativeLineGraphData(?int $gameTypeId = null, ?string $startDate = null, ?string $endDate = null, ?array $userIds = null, ?int $difficultyId = null, ?int $categoryId = null)
    {
        $query = GameScore::query()
            ->select(
                'game_scores.created_at',
                'game_scores.player_id',
                'game_scores.score',
                'game_scores.session_id',
                'game_scores.answer_json', // Added to access difficulty and category filters
                'ai_scores.created_at as ai_created_at',
                'ai_scores.score as ai_score',
                'ai_scores.answer_json as ai_answer_json', // Added to access AI difficulty and category filters
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

        // Handle multiple user IDs filtering - we want sessions that contain ANY of these users
        if ($difficultyId !== null && $difficultyId !== 0) {
            $query->where(function($q) use ($difficultyId) {
                $q->where(function($query) use ($difficultyId) {
                    $this->applyJsonIdFilter($query, 'game_scores', 'difficulty_id', $difficultyId);
                })->orWhere(function($query) use ($difficultyId) {
                    $this->applyJsonIdFilter($query, 'ai_scores', 'difficulty_id', $difficultyId);
                });
            });
        }

        if ($categoryId !== null && $categoryId !== 0) {
            $query->where(function($q) use ($categoryId) {
                $q->where(function($query) use ($categoryId) {
                    $this->applyJsonIdFilter($query, 'game_scores', 'category_id', $categoryId);
                })->orWhere(function($query) use ($categoryId) {
                    $this->applyJsonIdFilter($query, 'ai_scores', 'category_id', $categoryId);
                });
            });
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
                'ai_date' => $row->ai_created_at
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

    public function getGameSessionsHeatmapData(
        ?int $gameTypeId = null, 
        ?string $startDate = null, 
        ?string $endDate = null, 
        array $userIds = null, 
        bool $andOrUsers = false,
        ?int $difficultyId = null,
        ?int $categoryId = null)
    {
        // Define the main query without the game_questions join to avoid redundant data
        $query = GameScore::query()
            ->select(
                'game_scores.session_id',
                'game_scores.created_at',
                'game_scores.player_id',
                'game_scores.score as total_score',
                'game_scores.answer_json',
                'users.name as player_name',
                'games.id as game_id',
                'game_types.id as game_type_id',
                'game_types.name as game_name',
                'ai_scores.score as ai_score',
                'ai_scores.answer_json as ai_answer_json'
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
        
        // Handle multiple user IDs filtering
        if (!empty($userIds)) {
            $query->whereIn('game_scores.player_id', $userIds);
        }

        // Add difficulty filter using robust JSON extraction
        if ($difficultyId !== null && $difficultyId !== 0) {
            $query->where(function($q) use ($difficultyId) {
                $q->where(function($query) use ($difficultyId) {
                    $this->applyJsonIdFilter($query, 'game_scores', 'difficulty_id', $difficultyId);
                })->orWhere(function($query) use ($difficultyId) {
                    $this->applyJsonIdFilter($query, 'ai_scores', 'difficulty_id', $difficultyId);
                });
            });
        }

        if ($categoryId !== null && $categoryId !== 0) {
            $query->where(function($q) use ($categoryId) {
                $q->where(function($query) use ($categoryId) {
                    $this->applyJsonIdFilter($query, 'game_scores', 'category_id', $categoryId);
                })->orWhere(function($query) use ($categoryId) {
                    $this->applyJsonIdFilter($query, 'ai_scores', 'category_id', $categoryId);
                });
            });
        }


        // Get all results
        $results = $query->get();

        // Group by session_id to combine all players and AI data
        $groupedSessions = $results->groupBy('session_id');

        // Additional filtering for AND logic (double-check, though the above query should handle it)
        if (!empty($userIds) && $andOrUsers) {
            $groupedSessions = $groupedSessions->filter(function ($sessionGroup) use ($userIds) {
                $playerIdsInSession = $sessionGroup->pluck('player_id')->unique()->sort()->values();
                $requiredUserIds = collect($userIds)->unique()->sort()->values();
                
                // Check if the session contains ALL required users
                // The session must have at least all the required users (can have more)
                return $requiredUserIds->diff($playerIdsInSession)->isEmpty();
            });
        }

        // Cache for total game scores to avoid repeated queries for the same game type + difficulty + category
        $gameScoresCache = [];
        
        $data = $groupedSessions->map(function ($sessionGroup, $sessionId) use (&$gameScoresCache) {
            // Get the first record for session-level data
            $firstRecord = $sessionGroup->first();
            
            // Get the game_type_id to find the total possible score
            $gameTypeId = $firstRecord->game_type_id;

            // Extract difficulty_id and category_id from answer_json
            $difficultyId = null;
            $categoryId = null;
            
            if ($firstRecord->answer_json) {
                $answerData = is_string($firstRecord->answer_json)
                    ? json_decode($firstRecord->answer_json, true)
                    : $firstRecord->answer_json;

                    // Get difficulty_id and category_id from the answer_json
                    $difficultyId = $answerData['difficulty_id'] ?? null;
                    $categoryId = $answerData['category_id'] ?? null;
            }

            // Create a cache key that includes game type, difficulty, and category
            $cacheKey = $gameTypeId . '_' . ($difficultyId ?? 'null') . '_' . ($categoryId ?? 'null');

            // Check the cache first to avoid re-querying the same game type + difficulty + category combination
            if (!isset($gameScoresCache[$cacheKey])) {
                $query = GameQuestion::where('game_type_id', $gameTypeId);
                
                // Add difficulty and category filters if available
                if ($difficultyId !== null) {
                    $query->where('difficulty_id', $difficultyId);
                }
                if ($categoryId !== null) {
                    $query->where('category_id', $categoryId);
                }
                
                $totalGameScore = $query->sum('score_awarded');
                Log::warning('TOTAL GAME SCORE', [
                    'totalGameScore' => $totalGameScore,
                    'difficultyId' => $difficultyId,
                    'categoryId' => $categoryId
                ]);
                
                $gameScoresCache[$cacheKey] = $totalGameScore;
            } else {
                $totalGameScore = $gameScoresCache[$cacheKey];
            }

            // Collect all players in this session
            $players = $sessionGroup->map(function ($record) {
                $questionsCount = 0;
                $correctAnswers = 0;

                if ($record->answer_json) {
                    $answers = is_string($record->answer_json)
                        ? json_decode($record->answer_json, true)
                        : $record->answer_json;

                    if (is_array($answers)) {
                        // Count actual question answers (exclude metadata like difficulty_id, category_id)
                        $questionAnswers = array_filter($answers, function($key) {
                            return !in_array($key, ['difficulty_id', 'category_id']);
                        }, ARRAY_FILTER_USE_KEY);
                        
                        $questionsCount = count($questionAnswers);
                        $correctAnswers = collect($questionAnswers)->where('is_correct', true)->count();
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
            
            $truncatedSessionId = substr($sessionId, 0, 10) . '...';

            return [
                'session_id' => $sessionId,
                'truncated_session_id' => $truncatedSessionId,
                'created_at' => $firstRecord->created_at,
                'game_id' => $firstRecord->game_id,
                'game_name' => $firstRecord->game_name,
                'combined_score' => $combinedScore,
                'total_game_score' => $totalGameScore, 
                'ai_score' => $aiScore,
                'players' => $players,
                'player_count' => count($players),
                'difficulty_id' => $difficultyId,
                'category_id' => $categoryId,
            ];
        })->values();

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
        $difficultyId = 1;
        $categoryId = 1;

        foreach ($gameScores as $score) {
            $playerName = $score->player_name;
            $decodedOnce = json_decode($score->game_answer_json, true);
            $answerData = is_string($decodedOnce) ? json_decode($decodedOnce, true) : $decodedOnce;

            if (is_array($answerData)) {
                foreach ($answerData as $qNum => $answer) {
                    if (is_integer($qNum)) {
                        $questionNumber = $answer['question_number'] ?? $answer['question_id'] ?? $qNum;

                        $playerAnswersByQuestion[$questionNumber]['question'] = $answer['question'] ?? $answer['question_text'] ?? 'Unknown Question';

                        $playerAnswersByQuestion[$questionNumber]['answers'][] = [
                            'player_name' => $playerName,
                            'submitted' => $answer['submitted'] ?? $answer['user_answer'] ?? $answer['selected_answer'] ?? null,
                            'is_correct' => $answer['is_correct'] ?? $answer['correct'] ?? false,
                            'score_awarded' => $answer['score_awarded'] ?? $answer['points'] ?? $answer['score'] ?? 0
                        ];
                    } else {
                        if ($qNum === 'difficulty_id') {
                            $difficultyId = $answer;
                        }
                        if ($qNum === 'category_id') {
                            $categoryId = $answer;
                        }
                    }
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
            $aiAnswerDataDecodedOnce = $aiScore->answer_json;
            $aiAnswerData = is_string($aiAnswerDataDecodedOnce) ? json_decode($aiAnswerDataDecodedOnce, true) : $aiAnswerDataDecodedOnce;


                if (is_array($aiAnswerData)) {
                    foreach ($aiAnswerData as $qNum => $answer) {
                        if (is_integer($qNum)) {
                            $questionNumber = $answer['question_number'] ?? $answer['question_id'] ?? $qNum;

                            // Initialize the question key if it doesn't exist
                            if (!isset($playerAnswersByQuestion[$questionNumber]['question'])) {
                                $playerAnswersByQuestion[$questionNumber]['question'] = $answer['question'] ?? $answer['question_text'] ?? 'Unknown Question';
                            }

                            $playerAnswersByQuestion[$questionNumber]['ai'] = [
                                'submitted' => $answer['submitted'] ?? $answer['selected_answer'] ?? $answer['ai_answer'] ?? null,
                                'is_correct' => $answer['is_correct'] ?? $answer['correct'] ?? null,
                                'score_awarded' => $answer['score_awarded'] ?? $answer['points'] ?? $answer['score'] ?? null
                            ];
                        } 
                        if ($qNum === 'difficulty_id') {
                            $difficultyId = $answer;
                        }
                        if ($qNum === 'category_id') {
                            $categoryId = $answer;
                        }
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
                'question_text' => $data['question'] ?? 'Unknown Question',
                'player_answers' => $data['answers'] ?? [], // Provide a default empty array
                'ai_answer' => $data['ai']['submitted'] ?? null,
                'ai_is_correct' => $data['ai']['is_correct'] ?? null,
                'ai_score' => $data['ai']['score_awarded'] ?? null,
            ];
        }

        // Sort by question number
        usort($questions, fn($a, $b) => (int)($a['question_number'] ?? 0) - (int)($b['question_number'] ?? 0));


        $difficulty = GameTypeDifficulty::find($difficultyId);

        $difficultyName = $difficulty->name ?? 'Unknown Difficulty';


        $category = GameTypeCategory::find($categoryId);
        $categoryName = $category->name ?? 'Unknown Category';

        return [
            'session_id' => substr($first->session_id, 0, 10) . '...',
            'player_name' => $first->player_name,
            'game_name' => $first->game_name,
            'total_score' => $this->getTotalScoreByGame($first->game_id, $difficultyId, $categoryId),
            'ai_score' => $aiScore->score ?? null,
            'difficulty_name' => $difficultyName,
            'category_name' => $categoryName,
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

    public function getTotalScoreByGame($gameId, $difficultyId, $categoryId)
    {
        $game = Games::findOrFail($gameId);
        $gameQuestions = $game->gameType->gameQuestions()
            ->where('difficulty_id', $difficultyId)
            ->where('category_id', $categoryId)
            ->get();
        $totalScore = 0;

        foreach ($gameQuestions as $question) {
            $totalScore += $question->score_awarded;
        }
        return $totalScore;
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


    /**
     * Get game wins statistics comparing player vs AI wins by session
     *
     * @param int|null $difficultyId
     * @param int|null $categoryId
     * @return array
     */
    public function getAllGameWins(?int $gameTypeId = null, ?int $difficultyId = null, ?int $categoryId = null): array
    {
        Log::debug('Calculating game wins with filters', [
            'gameTypeId' => $gameTypeId,
            'difficultyId' => $difficultyId,
            'categoryId' => $categoryId
        ]);

        // Get all sessions with their highest player scores
        $playerSessionsQuery = DB::table('game_scores')
            ->join('games', 'game_scores.game_id', '=', 'games.id')
            ->select('game_scores.session_id', DB::raw('MAX(game_scores.score) as highest_player_score'))
            ->groupBy('game_scores.session_id');

        // Apply game type filter if provided
        if ($gameTypeId !== null) {
            $playerSessionsQuery->where('games.game_type_id', $gameTypeId);
        }

        // Apply difficulty filter if provided
        if ($difficultyId !== null) {
            $this->applyJsonIdFilter($playerSessionsQuery, 'game_scores', 'difficulty_id', $difficultyId);
        }

        // Apply category filter if provided
        if ($categoryId !== null) {
            $this->applyJsonIdFilter($playerSessionsQuery, 'game_scores', 'category_id', $categoryId);
        }

        Log::debug('PLAYER QUERY', [
            'sql' => $playerSessionsQuery->toSql(),
            'bindings' => $playerSessionsQuery->getBindings(),
        ]);

        $playerSessions = $playerSessionsQuery->get()->keyBy('session_id');

        Log::debug('PLAYER SESSIONS', [
            'playerSessions' => $playerSessions,
        ]);

        // Get all sessions with their highest AI scores
        $aiSessionsQuery = DB::table('ai_scores')
            ->join('games', 'ai_scores.game_id', '=', 'games.id')
            ->select('ai_scores.session_id', DB::raw('MAX(ai_scores.score) as highest_ai_score'))
            ->whereNotNull('ai_scores.score')
            ->groupBy('ai_scores.session_id');

        // Apply game type filter if provided
        if ($gameTypeId !== null) {
            $aiSessionsQuery->where('games.game_type_id', $gameTypeId);
        }

        // Apply difficulty filter if provided
        if ($difficultyId !== null) {
            $this->applyJsonIdFilter($aiSessionsQuery, 'ai_scores', 'difficulty_id', $difficultyId);
        }

        // Apply category filter if provided
        if ($categoryId !== null) {
            $this->applyJsonIdFilter($aiSessionsQuery, 'ai_scores', 'category_id', $categoryId);
        }

        Log::debug('AI QUERY', [
            'sql' => $aiSessionsQuery->toSql(),
            'bindings' => $aiSessionsQuery->getBindings(),
        ]);


        $aiSessions = $aiSessionsQuery->get()->keyBy('session_id');

        Log::debug('AI SESSIONS', [
            'aiSessions' => $aiSessions,
        ]);

        $playerWins = 0;
        $aiWins = 0;

        // Get all unique session IDs from both tables
        $allSessionIds = collect($playerSessions->keys())
            ->merge($aiSessions->keys())
            ->unique();

        // Compare highest scores for each session
        foreach ($allSessionIds as $sessionId) {

            $playerSession = $playerSessions->get($sessionId);
            $aiSession = $aiSessions->get($sessionId);

            if (!$playerSession || !$aiSession) {
                continue;
            }

            Log::debug('SESSIONS', [
                'playerSession' => $playerSession,
                'aiSession' => $aiSession,
                'sessionId' => $sessionId,
            ]);

            $playerHighest = $playerSession ? $playerSession->highest_player_score : 0;
            $aiHighest = $aiSession ? $aiSession->highest_ai_score : 0;

            // Only count sessions where at least one side has a score > 0
            if ($playerHighest > 0 || $aiHighest > 0) {
                if ($playerHighest > $aiHighest) {
                    $playerWins++;
                } elseif ($aiHighest > $playerHighest) {
                    $aiWins++;
                } else {
                    // Give 1 point to both if draw.
                    $playerWins++;
                    $aiWins++;
                }
            }
        }

        $result = [
            'player_wins' => $playerWins,
            'ai_wins' => $aiWins,
            'total_sessions' => $playerWins + $aiWins
        ];

        Log::debug('Game wins calculated', $result);
        return $result;
    }


    private function decodeAnswerJson($rawJson): ?array
    {
        if (empty($rawJson)) {
            return null;
        }

        // First decode
        $decoded = is_string($rawJson) ? json_decode($rawJson, true) : $rawJson;

        // If first decode failed or gave a string (double-encoded), try again
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        // Return only if valid array
        return (is_array($decoded)) ? $decoded : null;
    }



    /**
     * Apply a JSON ID filter with support for string/int/double-encoded values.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param string $tableColumnPrefix e.g. 'game_scores' or 'ai_scores'
     * @param string $jsonKey e.g. 'difficulty_id' or 'category_id'
     * @param int|string $value
     * @return void
     */
    function applyJsonIdFilter($query, string $tableColumnPrefix, string $jsonKey, $value): void
    {
        $query->where(function($q) use ($tableColumnPrefix, $jsonKey, $value) {
            // Try regular JSON extraction first
            $q->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT({$tableColumnPrefix}.answer_json, '$.\"{$jsonKey}\"')) = ?",
                [(string)$value]
            )
            ->orWhereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT({$tableColumnPrefix}.answer_json, '$.\"{$jsonKey}\"')) = ?",
                [(int)$value]
            )
            ->orWhereRaw(
                "CAST(JSON_UNQUOTE(JSON_EXTRACT({$tableColumnPrefix}.answer_json, '$.\"{$jsonKey}\"')) AS UNSIGNED) = ?",
                [(int)$value]
            )
            // Try double-encoded JSON extraction
            ->orWhereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE({$tableColumnPrefix}.answer_json), '$.\"{$jsonKey}\"')) = ?",
                [(string)$value]
            )
            ->orWhereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE({$tableColumnPrefix}.answer_json), '$.\"{$jsonKey}\"')) = ?",
                [(int)$value]
            )
            ->orWhereRaw(
                "CAST(JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE({$tableColumnPrefix}.answer_json), '$.\"{$jsonKey}\"')) AS UNSIGNED) = ?",
                [(int)$value]
            );
        });
    }
}