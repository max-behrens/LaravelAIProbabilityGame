<?php

namespace App\Http\Controllers\Front;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Services\Front\PostService;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\GamesService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class IndexController extends Controller
{
    protected $gamesService;

    public function __invoke(Request $request)
    {
        // Read game_id from the request query parameters for initial state
        $requestedGameId = $request->query('game_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $exponentialScale = $request->query('exponential_scale'); // Read the new parameter

        return Inertia::render('Dashboard/Index', [
            'current_game_id' => $requestedGameId ? (int) $requestedGameId : null,
            'current_start_date' => $startDate,
            'current_end_date' => $endDate,
            'current_exponential_scale' => $exponentialScale, // Pass to frontend
            // ... potentially other props you pass to the dashboard
        ]);
    }

    public function __construct(GamesService $gamesService)
    {
        $this->gamesService = $gamesService;
    }

    public function getAllUsers()
    {
        $users = User::all(['id', 'name', 'email']); // Select only needed fields for performance
        return response()->json($users);
    }

    public function getScores(Request $request)
    {
        $gameId = $request->gameId;
        $page = $request->query('page', 1);

        Log::info('Fetching game scores', ['gameId' => $gameId, 'page' => $page]);
        $gameScores = $this->gamesService->getGameScores($gameId, $page);
        Log::info('Game scores retrieved', ['scores' => $gameScores]);

        return response()->json($gameScores);
    }

    public function getScoreTrendStats(int $gameId)
    {
        Log::info('Fetching score trend stats', ['gameId' => $gameId]);

        $players = $this->gamesService->playerAverages($gameId);
        $totalGameScore = $this->gamesService->totalScore($gameId);

        return response()->json([
            'players' => $players,
            'totalScore' => $totalGameScore,
        ]);
    }

    /**
     * Get cumulative scores for all players across all games to the dashboard.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCumulativeLineGraph(Request $request)
    {
        // Extract parameters from the request
        $gameTypeId = $request->query('game_type_id'); // Changed from game_id
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $userId = $request->query('user_id');

        Log::info('getCumulativeLineGraph called', [
            'requested_game_type_id' => $gameTypeId, // Updated logging
            'start_date' => $startDate,
            'end_date' => $endDate,
            'user_id' => $userId
        ]);

        // Validate date formats if provided
        if ($startDate && !Carbon::canBeCreatedFromFormat($startDate, 'Y-m-d')) {
            Log::error('Invalid start date format', ['start_date' => $startDate]);
            return response()->json(['error' => 'Invalid start date format'], 400);
        }

        if ($endDate && !Carbon::canBeCreatedFromFormat($endDate, 'Y-m-d')) {
            Log::error('Invalid end date format', ['end_date' => $endDate]);
            return response()->json(['error' => 'Invalid end date format'], 400);
        }

        try {
            // Pass game_type_id instead of game_id
            $cumulativeScores = $this->gamesService->getCumulativeLineGraphData(
                $gameTypeId, // Changed parameter name
                $startDate,
                $endDate,
                $userId
            );

            Log::info('Cumulative scores retrieved', [
                'cumulativeScores_count' => count($cumulativeScores),
                'data_sample' => array_slice($cumulativeScores, 0, 2)
            ]);

            return response()->json($cumulativeScores);
        } catch (\Exception $e) {
            Log::error('Error retrieving cumulative scores', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Failed to retrieve cumulative scores'], 500);
        }
    }

    public function getCumulativeHeatMap()
    {
        Log::info('Fetching cumulative scores for all players across all games');

        $cumulativeScores = $this->gamesService->getCumulativeHeatMapData();
        
        Log::info('Cumulative scores retrieved', ['cumulativeScores' => $cumulativeScores]);

        return response()->json($cumulativeScores);
    }


    public function getCumulativeBarGraph()
    {
        Log::info('Fetching cumulative scores for all players across all games');

        $cumulativeScores = $this->gamesService->getCumulativeBarGraphData();
        
        Log::info('Cumulative scores retrieved', ['cumulativeScores' => $cumulativeScores]);

        return response()->json($cumulativeScores);
    }
}