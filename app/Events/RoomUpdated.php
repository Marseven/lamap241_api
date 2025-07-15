<?php

namespace App\Events;

use App\Models\GameRoom;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $room;
    public $updateType;

    public function __construct(GameRoom $room, string $updateType = 'general')
    {
        $this->room = $room;
        $this->updateType = $updateType;
    }

    public function broadcastOn()
    {
        return [
            new PresenceChannel('room.' . $this->room->code),
            new Channel('notifications'), // Pour les notifications générales
        ];
    }

    public function broadcastAs()
    {
        return 'room.updated';
    }

    public function broadcastWith()
    {
        return [
            'room' => [
                'code' => $this->room->code,
                'name' => $this->room->name,
                'status' => $this->room->status,
                'current_players' => $this->room->current_players,
                'max_players' => $this->room->max_players,
                'bet_amount' => $this->room->bet_amount,
                'is_exhibition' => $this->room->is_exhibition,
                'players' => $this->room->players->map(function ($player) {
                    return [
                        'id' => $player->id,
                        'pseudo' => $player->pseudo,
                        'avatar' => $player->avatar,
                        'is_ready' => $player->pivot->is_ready ?? false,
                    ];
                }),
            ],
            'update_type' => $this->updateType,
            'timestamp' => now()->toISOString(),
        ];
    }
}