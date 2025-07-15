<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaderboardUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $leaderboardData;
    public $leaderboardType;

    public function __construct(array $leaderboardData, string $leaderboardType = 'general')
    {
        $this->leaderboardData = $leaderboardData;
        $this->leaderboardType = $leaderboardType;
    }

    public function broadcastOn()
    {
        return [
            new Channel('leaderboard'),
        ];
    }

    public function broadcastAs()
    {
        return 'leaderboard.updated';
    }

    public function broadcastWith()
    {
        return [
            'leaderboard' => $this->leaderboardData,
            'type' => $this->leaderboardType,
            'timestamp' => now()->toISOString(),
        ];
    }
}