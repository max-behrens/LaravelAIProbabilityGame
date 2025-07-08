<?php

namespace App\Http\Controllers\Front;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Services\Front\PostService;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\GamesService;
use Illuminate\Support\Facades\Log;

class IndexController extends Controller
{
    protected $gamesService;

    public function __invoke(PostService $service, Request $request)
    {
        return redirect()->route('dashboard');
    }

    public function __construct(GamesService $gamesService)
    {
        $this->gamesService = $gamesService;
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
     * Get cumulative scores for all players across all games.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCumulativeScores()
    {
        Log::info('Fetching cumulative scores for all players across all games');
        $cumulativeScores = $this->gamesService->getCumulativeScoresByPlayer();
        Log::info('Cumulative scores retrieved', ['cumulativeScores' => $cumulativeScores]);

        return response()->json($cumulativeScores);
    }
}