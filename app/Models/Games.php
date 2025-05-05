<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Games extends Model
{
    use HasFactory;
    
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($game) {
            // Automatically set the title when a game is created
            $game->title = 'Game ' . $game->id;
        });
    }

    protected $appends = ['players_count'];

    protected $fillable = [
        'title',
    ];

    public function getPlayersCountAttribute()
    {
        return $this->users()->count();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'games_user', 'games_id', 'user_id');
    }

    public function gameType()
    {
        return $this->belongsTo(GameType::class, 'game_type_id');
    }

    public function gameScores()
    {
        return $this->hasMany(GameScore::class);
    }


}
