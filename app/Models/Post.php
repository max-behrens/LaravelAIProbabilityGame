<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    // Allow mass assignment for these fields, including the new ones
    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'content',
        'is_active',
        'featured_image',
        'calculation_data',  // New field for calculation data
        'ai_response_results',  // New field for AI response results
        'chatbot_messages'  // New field for chatbot messages
    ];

    // Cast the new fields as arrays to automatically handle JSON encoding/decoding
    protected $casts = [
        'calculation_data' => 'array',  // Automatically casts the 'calculation_data' field to an array
        'ai_response_results' => 'array',  // Automatically casts the 'ai_response_results' field to an array
        'chatbot_messages' => 'array',  // Automatically casts the 'chatbot_messages' field to an array
    ];

    // Define the relationship to the User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
