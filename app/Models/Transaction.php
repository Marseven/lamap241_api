<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'reference',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'fee',
        'status',
        'payment_method',
        'phone_number',
        'external_reference',
        'description',
        'metadata',
        'processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'fee' => 'decimal:2',
        'metadata' => 'json',
        'processed_at' => 'datetime'
    ];

    /**
     * Transaction types
     */
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAWAL = 'withdrawal';
    const TYPE_GAME_BET = 'game_bet';
    const TYPE_GAME_WIN = 'game_win';
    const TYPE_BONUS = 'bonus';
    const TYPE_COMMISSION = 'commission';
    const TYPE_REFUND = 'refund';

    /**
     * Transaction statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the user that owns the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet that owns the transaction.
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Scope for completed transactions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for pending transactions.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute()
    {
        return number_format(abs($this->amount), 0, ',', ' ') . ' FCFA';
    }

    /**
     * Check if transaction is credit.
     */
    public function isCredit(): bool
    {
        return in_array($this->type, [
            self::TYPE_DEPOSIT,
            self::TYPE_GAME_WIN,
            self::TYPE_BONUS,
            self::TYPE_REFUND
        ]);
    }

    /**
     * Check if transaction is debit.
     */
    public function isDebit(): bool
    {
        return !$this->isCredit();
    }

    /**
     * Get transaction status color.
     */
    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'green',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_PROCESSING => 'blue',
            self::STATUS_FAILED => 'red',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray'
        };
    }

    /**
     * Get transaction type icon.
     */
    public function getIconAttribute()
    {
        return match ($this->type) {
            self::TYPE_DEPOSIT => '📈',
            self::TYPE_WITHDRAWAL => '📉',
            self::TYPE_GAME_BET => '🎯',
            self::TYPE_GAME_WIN => '🏆',
            self::TYPE_BONUS => '🎁',
            self::TYPE_COMMISSION => '💸',
            self::TYPE_REFUND => '↩️',
            default => '💰'
        };
    }

    /**
     * Mark transaction as completed.
     */
    public function markAsCompleted($externalReference = null): bool
    {
        $this->status = self::STATUS_COMPLETED;
        $this->processed_at = now();

        if ($externalReference) {
            $this->external_reference = $externalReference;
        }

        return $this->save();
    }

    /**
     * Mark transaction as failed.
     */
    public function markAsFailed($reason = null): bool
    {
        $this->status = self::STATUS_FAILED;
        $this->processed_at = now();

        if ($reason) {
            $this->metadata = array_merge($this->metadata ?? [], ['failure_reason' => $reason]);
        }

        return $this->save();
    }
}
