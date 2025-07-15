<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'pseudo',
        'email',
        'phone',
        'password',
        'balance',
        'avatar',
        'status',
        'last_seen_at',
        'settings'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'password' => 'hashed',
        'settings' => 'json',
        'balance' => 'decimal:2'
    ];

    /**
     * Get the user's wallet.
     */
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get the user's transactions.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the user's created game rooms.
     */
    public function createdRooms()
    {
        return $this->hasMany(GameRoom::class, 'creator_id');
    }

    /**
     * Get the game rooms the user is playing in.
     */
    public function gameRooms()
    {
        return $this->belongsToMany(GameRoom::class, 'game_room_players')
            ->withPivot('position', 'status', 'is_ready', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    /**
     * Get the user's statistics.
     */
    public function stats()
    {
        return $this->hasOne(UserStats::class);
    }

    /**
     * Get the user's game moves.
     */
    public function gameMoves()
    {
        return $this->hasMany(GameMove::class, 'player_id');
    }

    /**
     * Check if user can afford a bet.
     */
    public function canAfford($amount): bool
    {
        return $this->wallet && $this->wallet->balance >= $amount;
    }

    /**
     * Deduct amount from balance.
     */
    public function deductBalance($amount): bool
    {
        if (!$this->wallet || !$this->canAfford($amount)) {
            return false;
        }

        $this->wallet->balance -= $amount;
        return $this->wallet->save();
    }

    /**
     * Add amount to balance.
     */
    public function addBalance($amount): bool
    {
        if (!$this->wallet) {
            $this->wallet()->create(['balance' => $amount]);
            return true;
        }

        $this->wallet->balance += $amount;
        return $this->wallet->save();
    }

    /**
     * Update last seen timestamp.
     */
    public function updateLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }

    /**
     * Check if user is online (seen in last 5 minutes).
     */
    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) < 5;
    }

    /**
     * Get or create user stats.
     */
    public function getOrCreateStats()
    {
        return $this->stats()->firstOrCreate([
            'user_id' => $this->id
        ]);
    }
}
