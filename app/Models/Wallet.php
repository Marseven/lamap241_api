<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'bonus_balance',
        'locked_balance',
        'total_deposited',
        'total_withdrawn',
        'total_won',
        'total_lost',
        'is_active'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'bonus_balance' => 'decimal:2',
        'locked_balance' => 'decimal:2',
        'total_deposited' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
        'total_won' => 'decimal:2',
        'total_lost' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    /**
     * Get the user that owns the wallet.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet's transactions.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get available balance (excluding locked amount).
     */
    public function getAvailableBalanceAttribute()
    {
        return $this->balance - $this->locked_balance;
    }

    /**
     * Get total balance (including bonus).
     */
    public function getTotalBalanceAttribute()
    {
        return $this->balance + $this->bonus_balance;
    }

    /**
     * Lock amount for a game.
     */
    public function lockAmount($amount): bool
    {
        if ($this->available_balance < $amount) {
            return false;
        }

        return DB::transaction(function () use ($amount) {
            $this->locked_balance += $amount;
            $this->balance -= $amount;
            return $this->save();
        });
    }

    /**
     * Unlock amount from a game.
     */
    public function unlockAmount($amount): bool
    {
        return DB::transaction(function () use ($amount) {
            $this->locked_balance = max(0, $this->locked_balance - $amount);
            $this->balance += $amount;
            return $this->save();
        });
    }

    /**
     * Process a deposit.
     */
    public function deposit($amount, $paymentMethod, $phoneNumber = null): Transaction
    {
        return DB::transaction(function () use ($amount, $paymentMethod, $phoneNumber) {
            $balanceBefore = $this->balance;

            $this->balance += $amount;
            $this->total_deposited += $amount;
            $this->save();

            return $this->transactions()->create([
                'user_id' => $this->user_id,
                'reference' => 'DEP-' . uniqid(),
                'type' => 'deposit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->balance,
                'status' => 'completed',
                'payment_method' => $paymentMethod,
                'phone_number' => $phoneNumber,
                'processed_at' => now()
            ]);
        });
    }

    /**
     * Process a withdrawal.
     */
    public function withdraw($amount, $paymentMethod, $phoneNumber, $fee = 0): ?Transaction
    {
        $totalAmount = $amount + $fee;

        if ($this->available_balance < $totalAmount) {
            return null;
        }

        return DB::transaction(function () use ($amount, $fee, $totalAmount, $paymentMethod, $phoneNumber) {
            $balanceBefore = $this->balance;

            $this->balance -= $totalAmount;
            $this->total_withdrawn += $amount;
            $this->save();

            return $this->transactions()->create([
                'user_id' => $this->user_id,
                'reference' => 'WTH-' . uniqid(),
                'type' => 'withdrawal',
                'amount' => $amount,
                'fee' => $fee,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->balance,
                'status' => 'pending',
                'payment_method' => $paymentMethod,
                'phone_number' => $phoneNumber
            ]);
        });
    }

    /**
     * Add game winnings.
     */
    public function addWinnings($amount, $gameRoomId): Transaction
    {
        return DB::transaction(function () use ($amount, $gameRoomId) {
            $balanceBefore = $this->balance;

            $this->balance += $amount;
            $this->total_won += $amount;
            $this->save();

            return $this->transactions()->create([
                'user_id' => $this->user_id,
                'reference' => 'WIN-' . uniqid(),
                'type' => 'game_win',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->balance,
                'status' => 'completed',
                'processed_at' => now(),
                'metadata' => ['game_room_id' => $gameRoomId]
            ]);
        });
    }

    /**
     * Deduct game bet.
     */
    public function placeBet($amount, $gameRoomId): ?Transaction
    {
        if ($this->available_balance < $amount) {
            return null;
        }

        return DB::transaction(function () use ($amount, $gameRoomId) {
            $balanceBefore = $this->balance;

            $this->balance -= $amount;
            $this->total_lost += $amount;
            $this->locked_balance += $amount;
            $this->save();

            return $this->transactions()->create([
                'user_id' => $this->user_id,
                'reference' => 'BET-' . uniqid(),
                'type' => 'game_bet',
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->balance,
                'status' => 'completed',
                'processed_at' => now(),
                'metadata' => ['game_room_id' => $gameRoomId]
            ]);
        });
    }
}
