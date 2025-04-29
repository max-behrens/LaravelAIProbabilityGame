<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameScore extends Model
{
    use HasFactory;

    protected $table = 'game_scores';

    // Define the fillable fields that can be mass-assigned
    protected $fillable = [
        'game_id',
        'player_id',
        'score',
        'session_id',
    ];

    /**
     * Get the user associated with the game score.
     */
    public function user()
    {

        return $this->belongsTo(User::class, 'player_id');
    }

    /**
     * Get the game that this score belongs to.
     */
    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}
