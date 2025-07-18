<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\Front\IndexController;
use App\Http\Controllers\Dashboard\WeatherController;
use App\Http\Controllers\Dashboard\DashboardAIController;
use App\Http\Controllers\Dashboard\GamesController;
use App\Http\Controllers\Dashboard\AIGameController;
use App\Http\Controllers\Dashboard\ParseXmlController;
use App\Http\Controllers\Auth\ErrorController;
use App\Http\Controllers\Front\PostController as FrontPostController;
use App\Http\Controllers\Dashboard\PostController as DashboardPostController;
use App\Models\Games;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', IndexController::class)->name('index');

Route::get('/403', [ErrorController::class, 'unauthorized'])->name('errors.403');




Route::middleware(['auth', 'verified'])->group(function () {

        Route::post('/games/{gameId}/submit-answer', [GamesController::class, 'submitAnswer'])->name('games.submit-answer');


            Route::get('/games/{gameId}/scores', [GamesController::class, 'getScores']);

            Route::get('/games/{gameId}/all-scores', [GamesController::class, 'getAllScores']);

            Route::get('/games/{gameId}/question-averages', [GamesController::class, 'getQuestionAverages']);

            Route::get('/games/{gameId}/score-trends', [GamesController::class, 'getScoreTrendStats']);

            Route::get('/games/{gameId}/players', [GamesController::class, 'getPlayers']);

            Route::post('/games/{game}/join', [GamesController::class, 'join']);
        Route::post('/games/{game}/leave', [GamesController::class, 'leave']);
        Route::post('/games/{game}/start', [GamesController::class, 'start'])->name('games.start');


    
    Route::get('/favicon.ico', function () {
        return Response::file(public_path('favicon.ico'));
    });
});



Route::post('/games/{game}/player-ready', [GamesController::class, 'playerReady'])->middleware('auth');



Route::prefix('dashboard')
    ->middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('/', function () {
            return Inertia::render('Dashboard/Index');
        })->name('dashboard');

        Route::resource('posts', DashboardPostController::class)->except(['update']);
        Route::prefix('posts')->group(function () {
            Route::put('/publish/{post}', [DashboardPostController::class, 'publish'])->name('posts.publish');
            Route::post('/update/{post}', [DashboardPostController::class, 'update'])->name('posts.update');
        });

        // Dashboard Stats Routes
        Route::get('/cumulative-linegraph', [IndexController::class, 'getCumulativeLineGraph']);
        Route::get('/cumulative-heatmap', [IndexController::class, 'getCumulativeHeatMap']);
        Route::get('/cumulative-bargraph', [IndexController::class, 'getCumulativeBarGraph']);
        Route::get('/users', [IndexController::class, 'getAllUsers']);


        Route::get('/games', [GamesController::class, 'index']);



        Route::get('/aigame', function () {
            // Get games with users relationship and players count
            $games = Games::with('users')->withCount('users as players_count')->get();
            
            return Inertia::render('Dashboard/AIGame/Index', [
                'games' => $games,
                'user' => auth()->user()
            ]);
        })->name('ai-game');

        Route::get('/room/{game}/{user}', [GamesController::class, 'showRoom'])
        ->name('room')
        ->middleware('auth');
        

         Route::get('/weather', function () {
            return Inertia::render('Dashboard/Weather/Index');
        })->name('weather.index');
        
        Route::post('/weather/get-data', [WeatherController::class, 'getWeather'])->name('weather.getData');

        Route::post('/ai/get-answer', [AIGameController::class, 'getAIAnswer'])->name('ai.getAnswer');
        Route::get('/ai/game/{gameId}/answers', [AIGameController::class, 'getGameAIAnswers'])->name('ai.getGameAnswers');

        // Route::get('/dashboard/posts/create', [DashboardPostController::class, 'store'])->name('posts.create');


        Route::get('/vue-react-page', function () {
            return Inertia::render('Dashboard/React/VueReactPage');
        })->name('react.index');

        Route::get('/angular-demo', function () {
            return Inertia::render('Dashboard/Angular/AngularDemo');
        })->name('angular.demo');


        Route::get('/parse-xml', [ParseXmlController::class, 'show'])->name('parse-xml');
        Route::get('/parse-xml/timestamps', [ParseXmlController::class, 'getTimestamps']);



        Route::post('/ask-openai', [DashboardAIController::class, 'askOpenAI']);




    });



require __DIR__ . '/auth.php';

Route::get('/{post:slug}', [FrontPostController::class, 'show'])->name('front.posts.show');



// Static asset handler for production
if (app()->environment('production')) {
    Route::get('/build/assets/{filename}', function ($filename) {
        $path = public_path("build/assets/{$filename}");
        
        if (!file_exists($path)) {
            return response("Asset not found: {$filename}", 404);
        }
        
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
        ];
        
        $mimeType = $mimeTypes[$extension] ?? 'text/plain';
        
        return response(file_get_contents($path))
            ->header('Content-Type', $mimeType)
            ->header('Cache-Control', 'public, max-age=31536000');
    })->where('filename', '.*');
}


Route::get('/image/{path}', function ($path) {
    // Remove duplicate 'posts/15/' prefix if present
    $cleanPath = preg_replace('/^posts\/\d+\//', '', $path);
    
    try {
        // Log the original and cleaned path for debugging
        Log::info('Image Path Debug', [
            'original_path' => $path,
            'cleaned_path' => $cleanPath
        ]);

        // Check if the file exists
        if (!Storage::disk('public')->exists($cleanPath)) {
            Log::warning('Image not found', ['path' => $cleanPath]);
            return response()->json(['error' => 'Image not found'], 404);
        }

        // Return the file response
        return Storage::disk('public')->response($cleanPath);
    } catch (\Exception $e) {
        Log::error('Image retrieval error', [
            'path' => $cleanPath,
            'error' => $e->getMessage()
        ]);
        return response()->json(['error' => 'Error retrieving image'], 500);
    }
})->name('get-image')->where('path', '.*');

