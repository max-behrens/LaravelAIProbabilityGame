<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\Front\IndexController;
use App\Http\Controllers\Dashboard\WeatherController;
use App\Http\Controllers\Dashboard\DashboardAIController;
use App\Http\Controllers\Dashboard\GamesController;
use App\Http\Controllers\Dashboard\ParseXmlController;
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



Route::middleware(['auth', 'verified'])->group(function () {

    Route::post('/submit-answer', [GamesController::class, 'submitAnswer']);

    Route::get('/games/{gameId}/scores', [GamesController::class, 'getScores']);


    
    Route::get('/favicon.ico', function () {
        return Response::file(public_path('favicon.ico'));
    });
});


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


        Route::get('/games', [GamesController::class, 'index']);
        Route::post('/games/{game}/join', [GamesController::class, 'join']);
        Route::post('/games/{game}/leave', [GamesController::class, 'leave']);

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

        Route::get('/dashboard', function () {
            return Inertia::render('Dashboard');
        });


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

