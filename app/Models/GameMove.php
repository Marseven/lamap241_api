<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameMove extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'player_id',
        'move_number',
        'card_played',
        'game_state_before',
        'game_state_after',
        'move_type',
        'played_at'
    ];

    protected $casts = [
        'card_played' => 'json',
        'game_state_before' => 'json',
        'game_state_after' => 'json',
        'played_at' => 'datetime'
    ];

    /**
     * Move types
     */
    const TYPE_PLAY_CARD = 'play_card';
    const TYPE_PASS = 'pass';
    const TYPE_TIMEOUT = 'timeout';
    const TYPE_FORFEIT = 'forfeit';

    /**
     * Get the game.
     */
    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get the player.
     */
    public function player()
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    /**
     * Get formatted move description.
     */
    public function getDescriptionAttribute()
    {
        switch ($this->move_type) {
            case self::TYPE_PLAY_CARD:
                $card = $this->card_played;
                return "{$this->player->pseudo} a joué {$card['value']}{$card['suit']}";

            case self::TYPE_PASS:
                return "{$this->player->pseudo} a passé son tour";

            case self::TYPE_TIMEOUT:
                return "{$this->player->pseudo} n'a pas joué à temps";

            case self::TYPE_FORFEIT:
                return "{$this->player->pseudo} a abandonné";

            default:
                return "Action inconnue";
        }
    }

    /**
     * Get move duration (time taken to make the move).
     */
    public function getDurationAttribute()
    {
        if ($this->move_number === 1) {
            return null;
        }

        $previousMove = static::where('game_id', $this->game_id)
            ->where('move_number', $this->move_number - 1)
            ->first();

        if (!$previousMove) {
            return null;
        }

        return $this->played_at->diffInSeconds($previousMove->played_at);
    }

    /**
     * Check if move was made quickly (under 5 seconds).
     */
    public function wasQuick(): bool
    {
        return $this->duration !== null && $this->duration < 5;
    }

    /**
     * Check if move was slow (over 30 seconds).
     */
    public function wasSlow(): bool
    {
        return $this->duration !== null && $this->duration > 30;
    }

    /**
     * Scope for card plays only.
     */
    public function scopeCardPlays($query)
    {
        return $query->where('move_type', self::TYPE_PLAY_CARD);
    }

    /**
     * Scope for specific game.
     */
    public function scopeForGame($query, $gameId)
    {
        return $query->where('game_id', $gameId);
    }

    /**
     * Get moves for replay.
     */
    public static function getReplayData($gameId)
    {
        return static::forGame($gameId)
            ->orderBy('move_number')
            ->get()
            ->map(function ($move) {
                return [
                    'move_number' => $move->move_number,
                    'player' => [
                        'id' => $move->player_id,
                        'pseudo' => $move->player->pseudo
                    ],
                    'type' => $move->move_type,
                    'card' => $move->card_played,
                    'duration' => $move->duration,
                    'timestamp' => $move->played_at->timestamp
                ];
            });
    }
}
