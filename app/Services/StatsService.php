<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserStats;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\GameMove;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StatsService
{
    private AchievementService $achievementService;

    public function __construct(AchievementService $achievementService)
    {
        $this->achievementService = $achievementService;
    }

    /**
     * Obtenir les statistiques détaillées d'un utilisateur
     */
    public function getUserDetailedStats(User $user): array
    {
        $stats = $user->stats ?? $user->stats()->create([]);
        $achievements = $this->achievementService->getUserAchievements($user);
        
        return [
            'basic_stats' => [
                'games_played' => $stats->games_played,
                'games_won' => $stats->games_won,
                'games_lost' => $stats->games_lost,
                'games_abandoned' => $stats->games_abandoned,
                'win_rate' => $stats->win_rate,
                'current_streak' => $stats->current_streak,
                'best_streak' => $stats->best_streak,
                'rank' => $stats->getRank()
            ],
            'financial_stats' => [
                'total_bet' => $stats->total_bet,
                'total_won' => $stats->total_won,
                'total_lost' => $stats->total_lost,
                'profit' => $stats->profit,
                'roi' => $stats->roi,
                'biggest_win' => $stats->biggest_win,
                'average_bet' => $stats->average_bet
            ],
            'performance_stats' => [
                'rounds_played' => $stats->rounds_played,
                'rounds_won' => $stats->rounds_won,
                'rounds_win_rate' => $stats->rounds_played > 0 ? round(($stats->rounds_won / $stats->rounds_played) * 100, 2) : 0,
                'avg_game_duration' => $this->getAverageGameDuration($user),
                'fastest_win' => $this->getFastestWin($user),
                'favorite_time' => $this->getFavoritePlayTime($user)
            ],
            'gameplay_stats' => [
                'total_moves' => $this->getTotalMoves($user),
                'avg_moves_per_game' => $this->getAverageMovesPerGame($user),
                'pass_rate' => $this->getPassRate($user),
                'card_preferences' => $this->getCardPreferences($user),
                'opponent_analysis' => $this->getOpponentAnalysis($user)
            ],
            'achievements' => $achievements,
            'trends' => $this->getTrends($user),
            'badges' => $this->getBadges($user)
        ];
    }

    /**
     * Obtenir la durée moyenne des parties
     */
    private function getAverageGameDuration(User $user): float
    {
        $duration = Game::whereHas('gameRoom.players', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->whereNotNull('ended_at')
            ->avg('duration');
        
        return round($duration ?? 0, 2);
    }

    /**
     * Obtenir la victoire la plus rapide
     */
    private function getFastestWin(User $user): ?float
    {
        $fastestWin = Game::where('round_winner_id', $user->id)
            ->whereNotNull('ended_at')
            ->min('duration');
        
        return $fastestWin ? round($fastestWin, 2) : null;
    }

    /**
     * Obtenir l'heure de jeu préférée
     */
    private function getFavoritePlayTime(User $user): array
    {
        $hourCounts = Game::whereHas('gameRoom.players', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();
        
        if (!$hourCounts) {
            return ['hour' => null, 'count' => 0];
        }
        
        return [
            'hour' => $hourCounts->hour,
            'count' => $hourCounts->count,
            'period' => $this->getTimePeriod($hourCounts->hour)
        ];
    }

    /**
     * Obtenir le nombre total de mouvements
     */
    private function getTotalMoves(User $user): int
    {
        return GameMove::where('player_id', $user->id)->count();
    }

    /**
     * Obtenir la moyenne de mouvements par partie
     */
    private function getAverageMovesPerGame(User $user): float
    {
        $totalMoves = $this->getTotalMoves($user);
        $gamesPlayed = $user->stats->games_played ?? 0;
        
        return $gamesPlayed > 0 ? round($totalMoves / $gamesPlayed, 2) : 0;
    }

    /**
     * Obtenir le taux de passage
     */
    private function getPassRate(User $user): float
    {
        $totalMoves = $this->getTotalMoves($user);
        $passes = GameMove::where('player_id', $user->id)
            ->where('move_type', 'pass')
            ->count();
        
        return $totalMoves > 0 ? round(($passes / $totalMoves) * 100, 2) : 0;
    }

    /**
     * Obtenir les préférences de cartes
     */
    private function getCardPreferences(User $user): array
    {
        $cardMoves = GameMove::where('player_id', $user->id)
            ->where('move_type', 'play_card')
            ->whereNotNull('card_played')
            ->get();
        
        $suitPreferences = [];
        $valuePreferences = [];
        
        foreach ($cardMoves as $move) {
            $card = $move->card_played;
            if (isset($card['suit'])) {
                $suitPreferences[$card['suit']] = ($suitPreferences[$card['suit']] ?? 0) + 1;
            }
            if (isset($card['value'])) {
                $valuePreferences[$card['value']] = ($valuePreferences[$card['value']] ?? 0) + 1;
            }
        }
        
        arsort($suitPreferences);
        arsort($valuePreferences);
        
        return [
            'favorite_suit' => array_key_first($suitPreferences),
            'favorite_value' => array_key_first($valuePreferences),
            'suit_distribution' => $suitPreferences,
            'value_distribution' => $valuePreferences
        ];
    }

    /**
     * Obtenir l'analyse des adversaires
     */
    private function getOpponentAnalysis(User $user): array
    {
        $gameRooms = GameRoom::whereHas('players', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with('players', 'games')
            ->get();
        
        $opponentStats = [];
        $botWins = 0;
        $humanWins = 0;
        
        foreach ($gameRooms as $room) {
            foreach ($room->games as $game) {
                if ($game->round_winner_id === $user->id) {
                    $opponents = $room->players->where('id', '!=', $user->id);
                    foreach ($opponents as $opponent) {
                        if ($opponent->is_bot) {
                            $botWins++;
                        } else {
                            $humanWins++;
                        }
                    }
                }
            }
        }
        
        return [
            'bot_wins' => $botWins,
            'human_wins' => $humanWins,
            'prefers_bots' => $botWins > $humanWins,
            'total_opponents_beaten' => $botWins + $humanWins
        ];
    }

    /**
     * Obtenir les tendances
     */
    private function getTrends(User $user): array
    {
        $last30Days = Game::whereHas('gameRoom.players', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at')
            ->get();
        
        $dailyStats = [];
        $winStreak = 0;
        $currentStreak = 0;
        
        foreach ($last30Days as $game) {
            $date = $game->created_at->format('Y-m-d');
            $won = $game->round_winner_id === $user->id;
            
            if (!isset($dailyStats[$date])) {
                $dailyStats[$date] = ['games' => 0, 'wins' => 0];
            }
            
            $dailyStats[$date]['games']++;
            if ($won) {
                $dailyStats[$date]['wins']++;
                $currentStreak++;
                $winStreak = max($winStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }
        
        return [
            'games_last_30_days' => count($last30Days),
            'win_streak_last_30_days' => $winStreak,
            'daily_stats' => $dailyStats,
            'most_active_day' => $this->getMostActiveDay($dailyStats),
            'trend_direction' => $this->getTrendDirection($dailyStats)
        ];
    }

    /**
     * Obtenir les badges
     */
    private function getBadges(User $user): array
    {
        $stats = $user->stats;
        $badges = [];
        
        if ($stats->win_rate >= 80) {
            $badges[] = ['name' => 'Expert', 'icon' => '🎖️', 'color' => 'gold'];
        } elseif ($stats->win_rate >= 60) {
            $badges[] = ['name' => 'Skilled', 'icon' => '🏅', 'color' => 'silver'];
        } elseif ($stats->win_rate >= 40) {
            $badges[] = ['name' => 'Average', 'icon' => '🥉', 'color' => 'bronze'];
        }
        
        if ($stats->games_played >= 100) {
            $badges[] = ['name' => 'Veteran', 'icon' => '⭐', 'color' => 'blue'];
        }
        
        if ($stats->current_streak >= 5) {
            $badges[] = ['name' => 'Hot Streak', 'icon' => '🔥', 'color' => 'red'];
        }
        
        if ($stats->profit > 0) {
            $badges[] = ['name' => 'Profitable', 'icon' => '💰', 'color' => 'green'];
        }
        
        return $badges;
    }

    /**
     * Obtenir les classements complets
     */
    public function getLeaderboards(): array
    {
        return [
            'winnings' => $this->getWinningsLeaderboard(),
            'win_rate' => $this->getWinRateLeaderboard(),
            'games_played' => $this->getGamesPlayedLeaderboard(),
            'current_streak' => $this->getCurrentStreakLeaderboard(),
            'achievements' => $this->achievementService->getAchievementLeaderboard(),
            'weekly' => $this->getWeeklyLeaderboard()
        ];
    }

    /**
     * Leaderboard des gains
     */
    private function getWinningsLeaderboard(int $limit = 10): array
    {
        return UserStats::with('user:id,pseudo,avatar')
            ->orderByDesc('total_won')
            ->limit($limit)
            ->get()
            ->map(function ($stats, $index) {
                return [
                    'rank' => $index + 1,
                    'user' => $stats->user,
                    'total_won' => $stats->total_won,
                    'games_played' => $stats->games_played,
                    'win_rate' => $stats->win_rate
                ];
            })
            ->toArray();
    }

    /**
     * Leaderboard du taux de victoire
     */
    private function getWinRateLeaderboard(int $limit = 10): array
    {
        return UserStats::with('user:id,pseudo,avatar')
            ->where('games_played', '>=', 10) // Minimum 10 parties
            ->orderByDesc(DB::raw('(games_won / games_played) * 100'))
            ->limit($limit)
            ->get()
            ->map(function ($stats, $index) {
                return [
                    'rank' => $index + 1,
                    'user' => $stats->user,
                    'win_rate' => $stats->win_rate,
                    'games_played' => $stats->games_played,
                    'games_won' => $stats->games_won
                ];
            })
            ->toArray();
    }

    /**
     * Leaderboard des parties jouées
     */
    private function getGamesPlayedLeaderboard(int $limit = 10): array
    {
        return UserStats::with('user:id,pseudo,avatar')
            ->orderByDesc('games_played')
            ->limit($limit)
            ->get()
            ->map(function ($stats, $index) {
                return [
                    'rank' => $index + 1,
                    'user' => $stats->user,
                    'games_played' => $stats->games_played,
                    'games_won' => $stats->games_won,
                    'win_rate' => $stats->win_rate
                ];
            })
            ->toArray();
    }

    /**
     * Leaderboard des séries actuelles
     */
    private function getCurrentStreakLeaderboard(int $limit = 10): array
    {
        return UserStats::with('user:id,pseudo,avatar')
            ->where('current_streak', '>', 0)
            ->orderByDesc('current_streak')
            ->limit($limit)
            ->get()
            ->map(function ($stats, $index) {
                return [
                    'rank' => $index + 1,
                    'user' => $stats->user,
                    'current_streak' => $stats->current_streak,
                    'best_streak' => $stats->best_streak,
                    'games_played' => $stats->games_played
                ];
            })
            ->toArray();
    }

    /**
     * Leaderboard hebdomadaire
     */
    private function getWeeklyLeaderboard(int $limit = 10): array
    {
        $weeklyStats = Game::where('created_at', '>=', now()->startOfWeek())
            ->selectRaw('round_winner_id, COUNT(*) as wins')
            ->whereNotNull('round_winner_id')
            ->groupBy('round_winner_id')
            ->orderByDesc('wins')
            ->limit($limit)
            ->with('roundWinner:id,pseudo,avatar')
            ->get()
            ->map(function ($game, $index) {
                return [
                    'rank' => $index + 1,
                    'user' => $game->roundWinner,
                    'weekly_wins' => $game->wins
                ];
            })
            ->toArray();
        
        return $weeklyStats;
    }

    /**
     * Obtenir les statistiques globales
     */
    public function getGlobalStats(): array
    {
        return Cache::remember('global_stats', 300, function () {
            return [
                'total_users' => User::count(),
                'total_games' => Game::count(),
                'total_rooms' => GameRoom::count(),
                'active_users_today' => User::whereDate('updated_at', today())->count(),
                'games_today' => Game::whereDate('created_at', today())->count(),
                'total_bets' => UserStats::sum('total_bet'),
                'total_winnings' => UserStats::sum('total_won'),
                'avg_win_rate' => UserStats::avg(DB::raw('(games_won / games_played) * 100')),
                'most_active_user' => $this->getMostActiveUser(),
                'biggest_winner' => $this->getBiggestWinner(),
                'achievement_stats' => $this->achievementService->getGlobalAchievementStats()
            ];
        });
    }

    /**
     * Méthodes utilitaires
     */
    private function getTimePeriod(int $hour): string
    {
        return match(true) {
            $hour >= 6 && $hour < 12 => 'morning',
            $hour >= 12 && $hour < 18 => 'afternoon',
            $hour >= 18 && $hour < 24 => 'evening',
            default => 'night'
        };
    }

    private function getMostActiveDay(array $dailyStats): ?string
    {
        if (empty($dailyStats)) return null;
        
        $maxGames = 0;
        $mostActiveDay = null;
        
        foreach ($dailyStats as $date => $stats) {
            if ($stats['games'] > $maxGames) {
                $maxGames = $stats['games'];
                $mostActiveDay = $date;
            }
        }
        
        return $mostActiveDay;
    }

    private function getTrendDirection(array $dailyStats): string
    {
        if (count($dailyStats) < 2) return 'stable';
        
        $dates = array_keys($dailyStats);
        $recent = array_slice($dates, -7); // 7 derniers jours
        $older = array_slice($dates, -14, 7); // 7 jours précédents
        
        $recentAvg = count($recent) > 0 ? array_sum(array_map(fn($d) => $dailyStats[$d]['games'], $recent)) / count($recent) : 0;
        $olderAvg = count($older) > 0 ? array_sum(array_map(fn($d) => $dailyStats[$d]['games'], $older)) / count($older) : 0;
        
        if ($recentAvg > $olderAvg * 1.1) return 'increasing';
        if ($recentAvg < $olderAvg * 0.9) return 'decreasing';
        return 'stable';
    }

    private function getMostActiveUser(): ?array
    {
        $user = UserStats::with('user:id,pseudo,avatar')
            ->orderByDesc('games_played')
            ->first();
        
        return $user ? [
            'user' => $user->user,
            'games_played' => $user->games_played
        ] : null;
    }

    private function getBiggestWinner(): ?array
    {
        $user = UserStats::with('user:id,pseudo,avatar')
            ->orderByDesc('total_won')
            ->first();
        
        return $user ? [
            'user' => $user->user,
            'total_won' => $user->total_won
        ] : null;
    }
}