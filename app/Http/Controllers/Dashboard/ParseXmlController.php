<?php

// app/Http/Controllers/ParseXmlController.php

namespace App\Http\Controllers\Dashboard;

use Inertia\Inertia;
use App\Services\Dashboard\ParseXmlService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;



class ParseXmlController extends Controller
{

    protected $parseXmlService;

    public function __construct(ParseXmlService $parseXmlService)
    {
        $this->parseXmlService = $parseXmlService;
    }


    public function show()
    {
        return Inertia::render('Dashboard/XmlParser/Index'); // Points to the Inertia page where your component is located
    }


    public function getTimestamps(Request $request)
    {
        $xmlPath = public_path('storage/xml/log.xml'); // Correct path for files in public/storage
    
        if (!file_exists($xmlPath)) {
            return response()->json(['error' => 'XML file not found'], 404);
        }
    
        $xml = file_get_contents($xmlPath);
        $description = $request->input('description');
        $timestamps = $this->parseXmlService->getTimestampsByDescription($xml, $description);
    
        return response()->json($timestamps);
    }
    
}
