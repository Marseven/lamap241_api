<?php

namespace App\Events;

use App\Models\GameRoom;
use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $room;
    public $game;

    /**
     * Create a new event instance.
     */
    public function __construct(GameRoom $room, Game $game)
    {
        $this->room = $room;
        $this->game = $game;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->room->code),
            new PresenceChannel('game.' . $this->room->code),
            new Channel('notifications'), // Pour les notifications générales
        ];
    }

    public function broadcastAs()
    {
        return 'game.started';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'room' => [
                'code' => $this->room->code,
                'name' => $this->room->name,
                'status' => $this->room->status,
                'is_exhibition' => $this->room->is_exhibition,
                'bet_amount' => $this->room->bet_amount,
            ],
            'game' => [
                'id' => $this->game->id,
                'round_number' => $this->game->round_number,
                'status' => $this->game->status,
                'current_player_id' => $this->game->current_player_id,
            ],
            'players' => $this->room->players->map(function ($player) {
                return [
                    'id' => $player->id,
                    'pseudo' => $player->pseudo,
                    'avatar' => $player->avatar,
                ];
            }),
            'message' => 'La partie a commencé !',
            'timestamp' => now()->toISOString(),
        ];
    }
}
