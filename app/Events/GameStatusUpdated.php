<?php

namespace App\Events;

use App\Models\Games;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $game;

    public function __construct(Games $game)
    {
        $this->game = $game;
    }

    public function broadcastOn()
    {
        return new Channel('games');
    }

    public function broadcastAs()
    {
        return 'game.updated';
    }

    public function broadcastWith()
    {
        return [
            'game_id' => $this->game->id,
            'players_count' => $this->game->users()->count(),
        ];
    }
}
