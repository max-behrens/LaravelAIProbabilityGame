<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use App\Services\Dashboard\DashboardAIService;
use App\Services\Dashboard\AIExplanationService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class DashboardAIController extends Controller
{
    protected $dashboardAIService;
    protected $aiExplanationService;

    public function __construct(DashboardAIService $dashboardAIService, AIExplanationService $aiExplanationService)
    {
        $this->dashboardAIService = $dashboardAIService;
        $this->aiExplanationService = $aiExplanationService;
    }

    public function askOpenAI(Request $request)
    {
        $request->validate([
            'user_input' => 'required|string|max:500',
            'calculationResults' => 'array',
            'city' => 'nullable|string|max:255',
        ]);

        $userInput = $request->user_input;
        $calculationResults = $request->calculationResults;
        $city = $request->city ?? 'Unknown City';  // Default city if none provided

        // Pass the calculation results and user input to OpenAI
        $response = $this->dashboardAIService->getAIResponse($userInput, $calculationResults, $city);

        return response()->json(['aiResponse' => $response]);
    }
}
