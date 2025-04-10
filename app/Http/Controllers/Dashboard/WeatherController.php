<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Weather;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\WeatherService;
use App\Services\Dashboard\WeatherCalculationService;
use App\Services\Dashboard\AIExplanationService;
use Illuminate\Support\Facades\Log;

class WeatherController extends Controller
{
    protected $weatherService;
    protected $weatherCalculationService;
    protected $aiExplanationService;

    public function __construct(WeatherService $weatherService, WeatherCalculationService $weatherCalculationService, AIExplanationService $aiExplanationService)
    {
        $this->authorizeResource(Weather::class, 'weather');
        $this->weatherService = $weatherService;
        $this->weatherCalculationService = $weatherCalculationService;
        $this->aiExplanationService = $aiExplanationService;
    }

    public function getWeather(Request $request)
    {


        Log::info('$request->city ', ['$request->city' => $request->city]);


        $weatherData = $this->weatherService->getWeatherData($request->city);
        $calculationResults = [];

        if (!is_array($weatherData) || count($weatherData) === 0) {
            return response()->json(['error' => 'Weather data is empty or invalid'], 500);
        }

        Log::info('$request->city 2 ', ['$request->city' => $request->city]);


        $totalDataPoints = count($weatherData);
        $stepSize = max(1, ceil($totalDataPoints / 5));

        $selectedForecasts = [];
        for ($i = 0; $i < $totalDataPoints; $i += $stepSize) {
            $selectedForecasts[] = $weatherData[$i];
            if (count($selectedForecasts) >= 5) break;
        }

        // Perform calculations for temperature, humidity, and pressure
        $temperatureChanges = $this->weatherCalculationService->calculateRateOfChange($selectedForecasts, 'temperature');
        $humidityChanges = $this->weatherCalculationService->calculateRateOfChange($selectedForecasts, 'humidity');
        $pressureChanges = $this->weatherCalculationService->calculateRateOfChange($selectedForecasts, 'pressure');

        $calculationResults = [
            'temperatureChanges' => $temperatureChanges,
            'averageTemperatureChanges' => $this->weatherCalculationService->calculateAverageRateOfChange($temperatureChanges),
            'humidityChanges' => $humidityChanges,
            'averageHumidityChanges' => $this->weatherCalculationService->calculateAverageRateOfChange($humidityChanges),
            'pressureChanges' => $pressureChanges,
            'averagePressureChanges' => $this->weatherCalculationService->calculateAverageRateOfChange($pressureChanges),
        ];

        Log::info('$request->city 3 ', ['$request->city' => $request->city]);


        $aiDataExplanations = $this->aiExplanationService->getAIWeatherExplanations($calculationResults, $city ?? '');

        Log::info('calculationResults 4 ', ['calculationResults' => $calculationResults]);

        return response()->json([
            'forecasts' => $selectedForecasts,
            'temperatureChanges' => $temperatureChanges,
            'humidityChanges' => $humidityChanges,
            'pressureChanges' => $pressureChanges,
            'averageTemperatureChanges' => $calculationResults['averageTemperatureChanges'],
            'averageHumidityChanges' => $calculationResults['averageHumidityChanges'],
            'averagePressureChanges' => $calculationResults['averagePressureChanges'],
            'temperatureExplanation' => $aiDataExplanations['temperatureExplanation'],
            'humidityExplanation' => $aiDataExplanations['humidityExplanation'],
            'pressureExplanation' => $aiDataExplanations['pressureExplanation'],
        ]);
    }
}
