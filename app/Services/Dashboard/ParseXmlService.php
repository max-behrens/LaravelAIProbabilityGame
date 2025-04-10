<?php

// app/Services/ParseXmlService.php

namespace App\Services\Dashboard;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ParseXmlService
{
    public function getTimestampsByDescription(string $xml, string $description): array
    {
        // Parse XML string into a SimpleXMLElement object
        $xmlObj = simplexml_load_string($xml);
        
        // Array to store matching timestamps
        $timestamps = [];
        
        // Check if XML was parsed successfully
        if ($xmlObj) {
            // Iterate through each event element
            foreach ($xmlObj->event as $event) {
                // Check if description matches
                if ((string)$event->description === $description) {
                    // Add timestamp as integer to array
                    $timestamps[] = (int)$event['timestamp'];
                }
            }
        }
        
        return $timestamps;
    }
}
