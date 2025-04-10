<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    public function getWeatherData($city)
    {
        $openWeatherKey = '50f86958e0582bbaafc9aaa786c7660f';

        
        // First API call to get current weather and coordinates
        $currentWeather = Http::withOptions([
            'verify' => false,
        ])->get("https://api.openweathermap.org/data/2.5/forecast", [
            'q' => $city,
            'appid' => $openWeatherKey
        ]);


        if (!$currentWeather->successful()) {
            return null;
        }

        // Format the data for easier display
        $formattedData = [];
        foreach ($currentWeather['list'] as $forecast) {

            Log::info('forecast: ' , ['forecast' => $forecast]);
            
            $formattedData[] = [
                'time' => $forecast['dt_txt'],
                'temperature' => round($forecast['main']['temp'] - 273.15, 2),
                'feels_like' => round($forecast['main']['feels_like'] - 273.15, 2),
                'humidity' => $forecast['main']['humidity'],
                'pressure' => $forecast['main']['pressure'],
                'wind_speed' => $forecast['wind']['speed'],
                'description' => ucfirst($forecast['weather'][0]['description']),
                'rain' => $forecast['rain']['3h'] ?? 0
            ];
        }

        return $formattedData;
    }
}
