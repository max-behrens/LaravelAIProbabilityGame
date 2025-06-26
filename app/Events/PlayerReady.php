<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class PlayerReady implements ShouldBroadcast
{
    use SerializesModels;

    public $gameId;
    public $userId;
    public $userName;
    public $readyCount;
    public $requiredCount;

    public function __construct($gameId, $userId, $userName, $readyCount, $requiredCount)
    {
        $this->gameId = $gameId;
        $this->userId = $userId;
        $this->userName = $userName;
        $this->readyCount = $readyCount;
        $this->requiredCount = $requiredCount;
    }

    public function broadcastOn()
    {
        return new Channel("game.{$this->gameId}");
    }

    public function broadcastAs()
    {
        return 'player.ready';
    }
}
