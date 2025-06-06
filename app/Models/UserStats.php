<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserStats extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'games_played',
        'games_won',
        'games_lost',
        'games_abandoned',
        'rounds_played',
        'rounds_won',
        'total_bet',
        'total_won',
        'total_lost',
        'biggest_win',
        'win_streak',
        'current_streak',
        'best_streak',
        'achievements'
    ];

    protected $casts = [
        'total_bet' => 'decimal:2',
        'total_won' => 'decimal:2',
        'total_lost' => 'decimal:2',
        'biggest_win' => 'decimal:2',
        'achievements' => 'json'
    ];

    /**
     * Achievement types
     */
    const ACHIEVEMENTS = [
        'first_win' => ['name' => 'Première victoire', 'icon' => '🏆'],
        'streak_3' => ['name' => '3 victoires d\'affilée', 'icon' => '🔥'],
        'streak_5' => ['name' => '5 victoires d\'affilée', 'icon' => '⚡'],
        'streak_10' => ['name' => '10 victoires d\'affilée', 'icon' => '💫'],
        'games_10' => ['name' => '10 parties jouées', 'icon' => '🎮'],
        'games_50' => ['name' => '50 parties jouées', 'icon' => '🎯'],
        'games_100' => ['name' => '100 parties jouées', 'icon' => '💯'],
        'big_winner' => ['name' => 'Gros gain (10k+)', 'icon' => '💰'],
        'whale' => ['name' => 'Baleine (100k+ misés)', 'icon' => '🐋'],
        'perfect_game' => ['name' => 'Partie parfaite', 'icon' => '✨'],
    ];

    /**
     * Get the user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get win rate.
     */
    public function getWinRateAttribute()
    {
        if ($this->games_played == 0) {
            return 0;
        }

        return round(($this->games_won / $this->games_played) * 100, 2);
    }

    /**
     * Get average bet.
     */
    public function getAverageBetAttribute()
    {
        if ($this->games_played == 0) {
            return 0;
        }

        return round($this->total_bet / $this->games_played, 2);
    }

    /**
     * Get profit/loss.
     */
    public function getProfitAttribute()
    {
        return $this->total_won - $this->total_lost;
    }

    /**
     * Get ROI (Return on Investment).
     */
    public function getRoiAttribute()
    {
        if ($this->total_bet == 0) {
            return 0;
        }

        return round((($this->total_won - $this->total_bet) / $this->total_bet) * 100, 2);
    }

    /**
     * Update stats after game.
     */
    public function updateAfterGame($won, $betAmount, $winAmount = 0)
    {
        $this->games_played++;
        $this->total_bet += $betAmount;

        if ($won) {
            $this->games_won++;
            $this->total_won += $winAmount;
            $this->current_streak++;

            if ($this->current_streak > $this->best_streak) {
                $this->best_streak = $this->current_streak;
            }

            if ($winAmount > $this->biggest_win) {
                $this->biggest_win = $winAmount;
            }
        } else {
            $this->games_lost++;
            $this->total_lost += $betAmount;
            $this->current_streak = 0;
        }

        // Check for new achievements
        $this->checkAchievements();

        return $this->save();
    }

    /**
     * Check and unlock achievements.
     */
    public function checkAchievements()
    {
        $achievements = $this->achievements ?? [];

        // First win
        if ($this->games_won === 1 && !isset($achievements['first_win'])) {
            $achievements['first_win'] = now();
        }

        // Streak achievements
        if ($this->current_streak >= 3 && !isset($achievements['streak_3'])) {
            $achievements['streak_3'] = now();
        }
        if ($this->current_streak >= 5 && !isset($achievements['streak_5'])) {
            $achievements['streak_5'] = now();
        }
        if ($this->current_streak >= 10 && !isset($achievements['streak_10'])) {
            $achievements['streak_10'] = now();
        }

        // Games played achievements
        if ($this->games_played >= 10 && !isset($achievements['games_10'])) {
            $achievements['games_10'] = now();
        }
        if ($this->games_played >= 50 && !isset($achievements['games_50'])) {
            $achievements['games_50'] = now();
        }
        if ($this->games_played >= 100 && !isset($achievements['games_100'])) {
            $achievements['games_100'] = now();
        }

        // Big winner
        if ($this->biggest_win >= 10000 && !isset($achievements['big_winner'])) {
            $achievements['big_winner'] = now();
        }

        // Whale
        if ($this->total_bet >= 100000 && !isset($achievements['whale'])) {
            $achievements['whale'] = now();
        }

        $this->achievements = $achievements;
    }

    /**
     * Get unlocked achievements.
     */
    public function getUnlockedAchievements()
    {
        $unlocked = [];
        $achievements = $this->achievements ?? [];

        foreach ($achievements as $key => $unlockedAt) {
            if (isset(self::ACHIEVEMENTS[$key])) {
                $unlocked[] = array_merge(
                    ['key' => $key, 'unlocked_at' => $unlockedAt],
                    self::ACHIEVEMENTS[$key]
                );
            }
        }

        return $unlocked;
    }

    /**
     * Get next achievements to unlock.
     */
    public function getNextAchievements()
    {
        $next = [];
        $achievements = $this->achievements ?? [];

        // Check streak achievements
        if (!isset($achievements['streak_3']) && $this->current_streak < 3) {
            $next[] = [
                'key' => 'streak_3',
                'progress' => $this->current_streak,
                'target' => 3,
                'percentage' => ($this->current_streak / 3) * 100
            ];
        }

        // Check games played achievements
        if (!isset($achievements['games_10']) && $this->games_played < 10) {
            $next[] = [
                'key' => 'games_10',
                'progress' => $this->games_played,
                'target' => 10,
                'percentage' => ($this->games_played / 10) * 100
            ];
        } elseif (!isset($achievements['games_50']) && $this->games_played < 50) {
            $next[] = [
                'key' => 'games_50',
                'progress' => $this->games_played,
                'target' => 50,
                'percentage' => ($this->games_played / 50) * 100
            ];
        }

        return $next;
    }

    /**
     * Get user rank based on total won.
     */
    public function getRank()
    {
        return self::where('total_won', '>', $this->total_won)->count() + 1;
    }

    /**
     * Scope for leaderboard.
     */
    public function scopeLeaderboard($query, $limit = 10)
    {
        return $query->orderByDesc('total_won')
            ->limit($limit)
            ->with('user:id,pseudo,avatar');
    }

    /**
     * Get formatted stats for API.
     */
    public function toArray()
    {
        return array_merge(parent::toArray(), [
            'win_rate' => $this->win_rate,
            'average_bet' => $this->average_bet,
            'profit' => $this->profit,
            'roi' => $this->roi,
            'achievements_unlocked' => count($this->achievements ?? []),
            'rank' => $this->getRank()
        ]);
    }
}
