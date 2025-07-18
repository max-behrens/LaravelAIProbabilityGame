<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIScore extends Model
{
    use HasFactory;

    protected $table = 'ai_scores';

    protected $casts = [
        'answer_json' => 'array',
    ];

    // Define the fillable fields that can be mass-assigned
    protected $fillable = [
        'game_id',
        'question_id',
        'session_id',
        'answer',
        'score',
        'answer_json',
    ];

    /**
     * Get the game that this AI answer belongs to.
     */
    public function game()
    {
        return $this->belongsTo(Games::class, 'game_id');
    }

    /**
     * Get the question that this AI answer belongs to.
     */
    public function question()
    {
        return $this->belongsTo(GameQuestion::class, 'question_id');
    }
}