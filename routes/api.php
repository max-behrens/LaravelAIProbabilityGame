<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Games;
use App\Http\Controllers\Front\IndexController;
use App\Http\Controllers\Dashboard\GamesController;
use App\Http\Controllers\Dashboard\AIGameController;
use App\Http\Controllers\Dashboard\DashboardAIController;
use App\Http\Controllers\Dashboard\PostController;
use App\Http\Controllers\Dashboard\ParseXmlController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->post('/dashboard/posts/save-chat', [PostController::class, 'saveChat']);



Route::post('/ask-openai', [DashboardAIController::class, 'askOpenAI']);

Route::get('/chart-data', function () {
    return response()->json([
        'labels' => ['January', 'February', 'March', 'April'],
        'values' => [10, 20, 15, 25]
    ]);
});

Route::get('/games/{gameId}/score-trends', [GamesController::class, 'getScoreTrendStats']);

Route::get('/games/{gameId}/all-scores', [GamesController::class, 'getAllScores']);

Route::middleware('auth:sanctum')->post('/games/{gameId}/submit-answer', [GamesController::class, 'submitAnswer']);

Route::get('/games/{gameId}/scores', [GamesController::class, 'getScores']);

Route::get('/games/{gameId}/question-averages', [GamesController::class, 'getQuestionAverages']);

Route::get('/games/{gameId}', [GamesController::class, 'show']);

Route::get('/games/{gameId}/players', [GamesController::class, 'getPlayers']);

Route::post('/games/{gameId}/broadcast', [GamesController::class, 'broadcast']);



// AI Game Routes

Route::post('/ai/answer', [AIGameController::class, 'getAIAnswer']);

Route::get('/ai/game/{gameId}/answers', [AIGameController::class, 'getGameAIAnswers']);

Route::get('/games/{gameId}/ai-scores', [AIGameController::class, 'getAIScores']);

Route::middleware('auth:sanctum')->post('/games/{gameId}/submit-ai-answer', [GamesController::class, 'submitAnswer']);



// Dashboard Stats Routes

Route::get('/dashboard/cumulative-linegraph', [IndexController::class, 'getCumulativeLineGraph']);

Route::get('/dashboard/cumulative-heatmap', [IndexController::class, 'getCumulativeHeatMap']);

Route::get('/dashboard/cumulative-bargraph', [IndexController::class, 'getCumulativeBarGraph']);

Route::get('/users', [IndexController::class, 'getAllUsers']);









// Define the route that the Vue component is calling
Route::get('/parse-xml/timestamps', [ParseXmlController::class, 'getTimestamps']);

