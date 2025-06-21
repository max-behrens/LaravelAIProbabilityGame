<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Games extends Model
{
    use HasFactory;

    protected $appends = ['players_count', 'title'];

    protected $fillable = [
        'user_id',
        'game_type_id',
        // no need for 'title'
    ];

    /**
     * Dynamically get the game's title from its game type.
     */
    public function getTitleAttribute(): string
    {
        return $this->gameType->name ?? 'Game';
    }

    /**
     * Get the count of players.
     */
    public function getPlayersCountAttribute(): int
    {
        return $this->users()->count();
    }

    /**
     * Relationship: many users belong to many games.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'games_user', 'game_id', 'user_id');
    }

    /**
     * Relationship: a game belongs to a game type.
     */
    public function gameType()
    {
        return $this->belongsTo(GameType::class, 'game_type_id');
    }

    /**
     * Relationship: a game has many game scores.
     */
    public function gameScores()
    {
        return $this->hasMany(GameScore::class);
    }

    public function start()
    {
        $this->status = 'started';
        $this->save();
    }

    public function attachUsers(array $userIds)
    {
        $currentCount = $this->users()->count();
        $maxPlayers = $this->max_players ?? 1;
        $availableSlots = $maxPlayers - $currentCount;

        $userIdsToAttach = array_slice($userIds, 0, $availableSlots);
        if (!empty($userIdsToAttach)) {
            $this->users()->attach($userIdsToAttach);
        }
    }


}
