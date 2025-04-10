<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherCalculationService
{
    public function calculateRateOfChange(array $data, string $field): array
    {
        $differences = [];

        for ($i = 1; $i < count($data); $i++) {
            $currentValue = $data[$i][$field];
            $previousValue = $data[$i - 1][$field];
            
            if ($previousValue !== 0) {
                $rateOfChange = (($currentValue - $previousValue) / abs($previousValue)) * 100;
                $differences[] = round($rateOfChange, 2);
            } else {
                $differences[] = 0;
            }
        }

        return $differences;
    }

    public function calculateAverageRateOfChange(array $data): float
    {

        Log::info('AVERAGE data: ' , ['data' => $data]);


        $totalValue = 0;
        for ($i = 1; $i < count($data); $i++) {
            $totalValue += $data[$i];
        }

        return round($totalValue / count($data), 2);
    }
}
