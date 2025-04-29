<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Games;
use App\Http\Controllers\Dashboard\GamesController;
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

Route::get('/player-averages', [GamesController::class, 'getPlayerAverages']);

Route::post('/submit-answer', [GamesController::class, 'submitAnswer']);

Route::get('/games/{gameId}/scores', [GamesController::class, 'getScores']);




// Define the route that the Vue component is calling
Route::get('/parse-xml/timestamps', [ParseXmlController::class, 'getTimestamps']);

