<?php

namespace App\Events;

use App\Models\Game;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CardPlayed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $game;
    public $player;
    public $card;
    public $gameState;

    /**
     * Create a new event instance.
     */
    public function __construct(Game $game, User $player, array $card)
    {
        $this->game = $game;
        $this->player = $player;
        $this->card = $card;
        $this->gameState = $game->getGameStateForPlayer($player->id);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('game.' . $this->game->gameRoom->code),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'player' => [
                'id' => $this->player->id,
                'pseudo' => $this->player->pseudo,
            ],
            'card' => $this->card,
            'game_state' => $this->gameState,
            'timestamp' => now()->toISOString(),
        ];
    }
}
