<?php

namespace App\Events;

use App\Models\Games;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class GameJoined implements ShouldBroadcast
{
    public $game;

    public function __construct(Games $game)
    {
        $this->game = $game;
    }

    // Channel that the event will be broadcast on
    public function broadcastOn()
    {
        return new Channel('game.' . $this->game->id); // Channel name is based on the game ID
    }

    // What data to send with the broadcast event
    public function broadcastWith()
    {
        return [
            'players_count' => $this->game->users()->count(), // Broadcast player count
        ];
    }
}
