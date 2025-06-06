<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_room_id',
        'round_number',
        'status',
        'current_player_id',
        'deck',
        'player_cards',
        'table_cards',
        'game_state',
        'round_winner_id',
        'started_at',
        'ended_at'
    ];

    protected $casts = [
        'deck' => 'json',
        'player_cards' => 'json',
        'table_cards' => 'json',
        'game_state' => 'json',
        'started_at' => 'datetime',
        'ended_at' => 'datetime'
    ];

    /**
     * Game statuses
     */
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ABANDONED = 'abandoned';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($game) {
            if (!$game->deck) {
                $game->deck = $game->generateDeck();
            }
            if (!$game->game_state) {
                $game->initializeGameState();
            }
        });
    }

    /**
     * Get the game room.
     */
    public function gameRoom()
    {
        return $this->belongsTo(GameRoom::class);
    }

    /**
     * Get the current player.
     */
    public function currentPlayer()
    {
        return $this->belongsTo(User::class, 'current_player_id');
    }

    /**
     * Get the round winner.
     */
    public function roundWinner()
    {
        return $this->belongsTo(User::class, 'round_winner_id');
    }

    /**
     * Get the game moves.
     */
    public function moves()
    {
        return $this->hasMany(GameMove::class);
    }

    /**
     * Generate a deck of cards.
     */
    public function generateDeck(): array
    {
        $suits = ['♠', '♥', '♣', '♦'];
        $values = range(2, 10);
        $deck = [];

        foreach ($suits as $suit) {
            foreach ($values as $value) {
                $deck[] = [
                    'value' => $value,
                    'suit' => $suit,
                    'id' => "{$value}{$suit}"
                ];
            }
        }

        // Shuffle the deck
        shuffle($deck);

        return $deck;
    }

    /**
     * Initialize game state.
     */
    public function initializeGameState(): void
    {
        $players = $this->gameRoom->activePlayers()->get();
        $deck = $this->deck;
        $playerCards = [];
        $playerScores = [];

        // Deal 5 cards to each player
        foreach ($players as $player) {
            $cards = [];
            for ($i = 0; $i < 5; $i++) {
                $cards[] = array_pop($deck);
            }
            $playerCards[$player->id] = $cards;
            $playerScores[$player->id] = 0;
        }

        $this->deck = $deck;
        $this->player_cards = $playerCards;
        $this->table_cards = [];
        $this->current_player_id = $players->first()->id;

        $this->game_state = [
            'turn' => 1,
            'phase' => 'playing', // playing, finished
            'player_order' => $players->pluck('id')->toArray(),
            'player_scores' => $playerScores,
            'last_action' => null,
            'consecutive_passes' => 0
        ];
    }

    /**
     * Deal cards to players.
     */
    public function dealCards(): void
    {
        $this->initializeGameState();
        $this->save();
    }

    /**
     * Get player's cards.
     */
    public function getPlayerCards($playerId): array
    {
        return $this->player_cards[$playerId] ?? [];
    }

    /**
     * Get current player's cards.
     */
    public function getCurrentPlayerCards(): array
    {
        return $this->getPlayerCards($this->current_player_id);
    }

    /**
     * Check if a move is valid.
     */
    public function isValidMove($playerId, $card): bool
    {
        // Check if it's player's turn
        if ($this->current_player_id != $playerId) {
            return false;
        }

        // Check if player has the card
        $playerCards = $this->getPlayerCards($playerId);
        $hasCard = collect($playerCards)->contains(function ($c) use ($card) {
            return $c['value'] == $card['value'] && $c['suit'] == $card['suit'];
        });

        if (!$hasCard) {
            return false;
        }

        // Check if move follows game rules
        $tableCards = $this->table_cards;

        // If table is empty, any card can be played
        if (empty($tableCards)) {
            return true;
        }

        // Get last played card
        $lastCard = end($tableCards);

        // Must play same suit with higher value
        if ($card['suit'] !== $lastCard['suit']) {
            return false;
        }

        if ($card['value'] <= $lastCard['value']) {
            return false;
        }

        return true;
    }

    /**
     * Play a card.
     */
    public function playCard($playerId, $card): bool
    {
        if (!$this->isValidMove($playerId, $card)) {
            return false;
        }

        // Remove card from player's hand
        $playerCards = $this->player_cards;
        $playerCards[$playerId] = array_values(array_filter($playerCards[$playerId], function ($c) use ($card) {
            return !($c['value'] == $card['value'] && $c['suit'] == $card['suit']);
        }));

        // Add card to table
        $tableCards = $this->table_cards;
        $tableCards[] = array_merge($card, ['played_by' => $playerId]);

        // Update game state
        $gameState = $this->game_state;
        $gameState['turn']++;
        $gameState['last_action'] = [
            'type' => 'play_card',
            'player_id' => $playerId,
            'card' => $card,
            'timestamp' => now()
        ];
        $gameState['consecutive_passes'] = 0;

        // Check if player won this round (no more cards)
        if (count($playerCards[$playerId]) === 0) {
            $this->round_winner_id = $playerId;
            $this->status = self::STATUS_COMPLETED;
            $this->ended_at = now();

            // Update scores
            $gameState['player_scores'][$playerId]++;
            $gameState['phase'] = 'finished';
        } else {
            // Move to next player
            $this->current_player_id = $this->getNextPlayerId();
        }

        // Save move
        $this->moves()->create([
            'player_id' => $playerId,
            'move_number' => $gameState['turn'],
            'card_played' => $card,
            'game_state_before' => $this->game_state,
            'game_state_after' => $gameState,
            'move_type' => 'play_card',
            'played_at' => now()
        ]);

        // Update game
        $this->player_cards = $playerCards;
        $this->table_cards = $tableCards;
        $this->game_state = $gameState;

        return $this->save();
    }

    /**
     * Player passes turn.
     */
    public function pass($playerId): bool
    {
        if ($this->current_player_id != $playerId) {
            return false;
        }

        $gameState = $this->game_state;
        $gameState['turn']++;
        $gameState['consecutive_passes']++;
        $gameState['last_action'] = [
            'type' => 'pass',
            'player_id' => $playerId,
            'timestamp' => now()
        ];

        // If all players passed, clear the table
        if ($gameState['consecutive_passes'] >= count($gameState['player_order'])) {
            $this->table_cards = [];
            $gameState['consecutive_passes'] = 0;
        }

        // Move to next player
        $this->current_player_id = $this->getNextPlayerId();

        // Save move
        $this->moves()->create([
            'player_id' => $playerId,
            'move_number' => $gameState['turn'],
            'move_type' => 'pass',
            'game_state_before' => $this->game_state,
            'game_state_after' => $gameState,
            'played_at' => now()
        ]);

        $this->game_state = $gameState;

        return $this->save();
    }

    /**
     * Get next player ID.
     */
    public function getNextPlayerId()
    {
        $playerOrder = $this->game_state['player_order'];
        $currentIndex = array_search($this->current_player_id, $playerOrder);
        $nextIndex = ($currentIndex + 1) % count($playerOrder);

        return $playerOrder[$nextIndex];
    }

    /**
     * Check if game is finished.
     */
    public function isFinished(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Get game duration.
     */
    public function getDurationAttribute()
    {
        if (!$this->ended_at) {
            return null;
        }

        return $this->ended_at->diffInSeconds($this->started_at);
    }

    /**
     * Get game state for API.
     */
    public function getGameStateForPlayer($playerId)
    {
        $state = [
            'round_number' => $this->round_number,
            'status' => $this->status,
            'current_player_id' => $this->current_player_id,
            'is_my_turn' => $this->current_player_id == $playerId,
            'table_cards' => $this->table_cards,
            'my_cards' => $this->getPlayerCards($playerId),
            'other_players' => []
        ];

        // Add other players info
        foreach ($this->game_state['player_order'] as $pid) {
            if ($pid != $playerId) {
                $state['other_players'][$pid] = [
                    'cards_count' => count($this->getPlayerCards($pid)),
                    'score' => $this->game_state['player_scores'][$pid]
                ];
            }
        }

        return $state;
    }
}
