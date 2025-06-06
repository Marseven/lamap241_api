<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GameRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'creator_id',
        'bet_amount',
        'pot_amount',
        'commission_amount',
        'max_players',
        'current_players',
        'rounds_to_win',
        'time_limit',
        'allow_spectators',
        'status',
        'winner_id',
        'started_at',
        'finished_at',
        'settings'
    ];

    protected $casts = [
        'bet_amount' => 'decimal:2',
        'pot_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'allow_spectators' => 'boolean',
        'settings' => 'json',
        'started_at' => 'datetime',
        'finished_at' => 'datetime'
    ];

    /**
     * Room statuses
     */
    const STATUS_WAITING = 'waiting';
    const STATUS_READY = 'ready';
    const STATUS_PLAYING = 'playing';
    const STATUS_FINISHED = 'finished';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($room) {
            $room->code = static::generateUniqueCode();
            $room->commission_amount = $room->bet_amount * $room->max_players * 0.1;
        });
    }

    /**
     * Generate unique room code.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Get the creator of the room.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get the winner of the room.
     */
    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    /**
     * Get the players in the room.
     */
    public function players()
    {
        return $this->belongsToMany(User::class, 'game_room_players')
            ->withPivot('position', 'status', 'is_ready', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    /**
     * Get active players.
     */
    public function activePlayers()
    {
        return $this->players()
            ->wherePivotIn('status', ['waiting', 'ready', 'playing']);
    }

    /**
     * Get the games in this room.
     */
    public function games()
    {
        return $this->hasMany(Game::class);
    }

    /**
     * Get current game.
     */
    public function currentGame()
    {
        return $this->games()->where('status', 'in_progress')->latest()->first();
    }

    /**
     * Check if room is full.
     */
    public function isFull(): bool
    {
        return $this->current_players >= $this->max_players;
    }

    /**
     * Check if room can start.
     */
    public function canStart(): bool
    {
        return $this->status === self::STATUS_READY &&
            $this->current_players === $this->max_players &&
            $this->activePlayers()->wherePivot('is_ready', true)->count() === $this->max_players;
    }

    /**
     * Add player to room.
     */
    public function addPlayer(User $user): bool
    {
        if ($this->isFull() || $this->status !== self::STATUS_WAITING) {
            return false;
        }

        // Check if user can afford the bet
        if (!$user->canAfford($this->bet_amount)) {
            return false;
        }

        // Place the bet
        $transaction = $user->wallet->placeBet($this->bet_amount, $this->id);
        if (!$transaction) {
            return false;
        }

        // Add player to room
        $this->players()->attach($user->id, [
            'position' => $this->current_players + 1,
            'status' => 'waiting',
            'joined_at' => now()
        ]);

        // Update room
        $this->current_players++;
        $this->pot_amount += $this->bet_amount;

        if ($this->isFull()) {
            $this->status = self::STATUS_READY;
        }

        return $this->save();
    }

    /**
     * Remove player from room.
     */
    public function removePlayer(User $user): bool
    {
        $player = $this->players()->find($user->id);
        if (!$player) {
            return false;
        }

        // If game hasn't started, refund the bet
        if ($this->status === self::STATUS_WAITING || $this->status === self::STATUS_READY) {
            $user->wallet->unlockAmount($this->bet_amount);
            $user->wallet->addBalance($this->bet_amount);

            // Create refund transaction
            $user->wallet->transactions()->create([
                'user_id' => $user->id,
                'reference' => 'REF-' . uniqid(),
                'type' => 'refund',
                'amount' => $this->bet_amount,
                'status' => 'completed',
                'description' => 'Remboursement - Salle ' . $this->code,
                'metadata' => ['game_room_id' => $this->id]
            ]);

            $this->pot_amount -= $this->bet_amount;
        }

        // Update player status
        $this->players()->updateExistingPivot($user->id, [
            'status' => 'left',
            'left_at' => now()
        ]);

        // Update room
        $this->current_players--;

        if ($this->current_players === 0) {
            $this->status = self::STATUS_CANCELLED;
        } elseif ($this->status === self::STATUS_READY && $this->current_players < $this->max_players) {
            $this->status = self::STATUS_WAITING;
        }

        return $this->save();
    }

    /**
     * Start the game.
     */
    public function startGame(): ?Game
    {
        if (!$this->canStart()) {
            return null;
        }

        $this->status = self::STATUS_PLAYING;
        $this->started_at = now();
        $this->save();

        // Create first game/round
        return $this->games()->create([
            'round_number' => 1,
            'status' => 'in_progress',
            'started_at' => now()
        ]);
    }

    /**
     * End the game.
     */
    public function endGame(User $winner): bool
    {
        $this->winner_id = $winner->id;
        $this->status = self::STATUS_FINISHED;
        $this->finished_at = now();

        // Calculate winnings (90% of pot)
        $winnings = $this->pot_amount * 0.9;

        // Unlock winner's bet
        $winner->wallet->unlockAmount($this->bet_amount);

        // Add winnings
        $winner->wallet->addWinnings($winnings, $this->id);

        // Update winner stats
        $stats = $winner->getOrCreateStats();
        $stats->games_won++;
        $stats->total_won += $winnings;
        $stats->current_streak++;
        if ($stats->current_streak > $stats->best_streak) {
            $stats->best_streak = $stats->current_streak;
        }
        if ($winnings > $stats->biggest_win) {
            $stats->biggest_win = $winnings;
        }
        $stats->save();

        // Update losers stats
        $this->activePlayers()->where('users.id', '!=', $winner->id)->each(function ($loser) {
            $stats = $loser->getOrCreateStats();
            $stats->games_lost++;
            $stats->current_streak = 0;
            $stats->save();
        });

        return $this->save();
    }

    /**
     * Get room status label.
     */
    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            self::STATUS_WAITING => 'En attente',
            self::STATUS_READY => 'Prêt',
            self::STATUS_PLAYING => 'En cours',
            self::STATUS_FINISHED => 'Terminé',
            self::STATUS_CANCELLED => 'Annulé',
            default => 'Inconnu'
        };
    }

    /**
     * Get room status color.
     */
    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            self::STATUS_WAITING => 'yellow',
            self::STATUS_READY => 'blue',
            self::STATUS_PLAYING => 'green',
            self::STATUS_FINISHED => 'gray',
            self::STATUS_CANCELLED => 'red',
            default => 'gray'
        };
    }
}
