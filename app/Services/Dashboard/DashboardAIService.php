<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardAIService
{
    public function getAIResponse($userInput, $calculationResults, $city)
    {
        Log::info('User input:', ['userInput' => $userInput]);
        Log::info('Calculation Results:', ['calculationResults' => $calculationResults]);
        Log::info('City:', ['city' => $city]);
        Log::info('OpenAI Secret', ['secret' => config('services.openai.secret')]);

        $city = $city ?? 'Unknown City';

        $explanationData = '';
        if (!empty($calculationResults)) {
            $explanationData = 'Weather explanations: ' . implode(' ', [
                $calculationResults['temperatureExplanation'] ?? '',
                $calculationResults['humidityExplanation'] ?? '',
                $calculationResults['pressureExplanation'] ?? ''
            ]);
        }

        $response = Http::withoutVerifying()
            ->withToken(config('services.openai.secret'))
            ->withHeaders([
                'OpenAI-Organization' => config('services.openai.organisation'),
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                "model" => "gpt-3.5-turbo-0125",
                "messages" => [
                    ["role" => "system", "content" => "You are a helpful assistant."],
                    ["role" => "user", "content" => $userInput],
                    ["role" => "system", "content" => $explanationData],
                    ["role" => "system", "content" => "City: $city"]
                ]
            ]);

        $aiResponse = $response->json('choices.0.message.content');

        Log::info('AI Response:', ['response' => $aiResponse]);

        return $aiResponse;
    }
}
