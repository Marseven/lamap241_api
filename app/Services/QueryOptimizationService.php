<?php

namespace App\Services;

use App\Models\GameRoom;
use App\Models\Game;
use App\Models\User;
use App\Models\Transaction;
use App\Models\UserStats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class QueryOptimizationService
{
    /**
     * Optimiser les requêtes pour les salles de jeu
     */
    public function optimizeGameRoomsQuery(array $filters = []): Builder
    {
        $query = GameRoom::with(['creator:id,pseudo,avatar', 'players:id,pseudo,avatar'])
            ->select([
                'id', 'code', 'name', 'status', 'creator_id', 'current_players', 
                'max_players', 'bet_amount', 'is_exhibition', 'allow_spectators',
                'created_at', 'updated_at'
            ]);

        // Filtres optimisés
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_exhibition'])) {
            $query->where('is_exhibition', $filters['is_exhibition']);
        }

        if (isset($filters['available_only']) && $filters['available_only']) {
            $query->where('status', GameRoom::STATUS_WAITING)
                  ->where('current_players', '<', DB::raw('max_players'));
        }

        // Ordonner par priorité
        $query->orderBy('status', 'desc')
              ->orderBy('created_at', 'desc');

        return $query;
    }

    /**
     * Optimiser les requêtes pour les jeux
     */
    public function optimizeGamesQuery(array $filters = []): Builder
    {
        $query = Game::with([
            'gameRoom:id,code,name,status,is_exhibition,bet_amount',
            'currentPlayer:id,pseudo,avatar',
            'roundWinner:id,pseudo,avatar'
        ])->select([
            'id', 'game_room_id', 'status', 'round_number', 'current_player_id',
            'round_winner_id', 'game_state', 'created_at', 'updated_at'
        ]);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['user_id'])) {
            $query->whereHas('gameRoom.players', function ($q) use ($filters) {
                $q->where('users.id', $filters['user_id']);
            });
        }

        return $query->orderBy('updated_at', 'desc');
    }

    /**
     * Optimiser les requêtes pour les transactions
     */
    public function optimizeTransactionsQuery(int $userId, array $filters = []): Builder
    {
        $query = Transaction::where('user_id', $userId)
            ->select([
                'id', 'user_id', 'type', 'amount', 'status', 'reference',
                'provider', 'provider_reference', 'created_at', 'updated_at'
            ]);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Optimiser le calcul des statistiques utilisateur
     */
    public function optimizeUserStatsQuery(int $userId): array
    {
        $cacheKey = "user_stats_{$userId}";
        
        return Cache::remember($cacheKey, 300, function () use ($userId) {
            $stats = UserStats::where('user_id', $userId)->first();
            
            if (!$stats) {
                $this->generateUserStats($userId);
                $stats = UserStats::where('user_id', $userId)->first();
            }

            return [
                'games_played' => $stats->games_played ?? 0,
                'games_won' => $stats->games_won ?? 0,
                'games_lost' => $stats->games_lost ?? 0,
                'win_rate' => $stats->win_rate ?? 0,
                'total_winnings' => $stats->total_winnings ?? 0,
                'total_losses' => $stats->total_losses ?? 0,
                'current_streak' => $stats->current_streak ?? 0,
                'best_streak' => $stats->best_streak ?? 0,
                'average_game_duration' => $stats->average_game_duration ?? 0,
                'last_game_at' => $stats->last_game_at,
                'updated_at' => $stats->updated_at,
            ];
        });
    }

    /**
     * Optimiser les requêtes pour le classement
     */
    public function optimizeLeaderboardQuery(string $type = 'wins', int $limit = 10): array
    {
        $cacheKey = "leaderboard_{$type}_{$limit}";
        
        return Cache::remember($cacheKey, 600, function () use ($type, $limit) {
            $query = User::join('user_stats', 'users.id', '=', 'user_stats.user_id')
                ->select([
                    'users.id', 'users.pseudo', 'users.avatar',
                    'user_stats.games_played', 'user_stats.games_won',
                    'user_stats.win_rate', 'user_stats.total_winnings',
                    'user_stats.current_streak', 'user_stats.best_streak'
                ])
                ->where('user_stats.games_played', '>', 0);

            switch ($type) {
                case 'wins':
                    $query->orderBy('user_stats.games_won', 'desc');
                    break;
                case 'winrate':
                    $query->where('user_stats.games_played', '>=', 10)
                          ->orderBy('user_stats.win_rate', 'desc');
                    break;
                case 'money':
                    $query->orderBy('user_stats.total_winnings', 'desc');
                    break;
                case 'streak':
                    $query->orderBy('user_stats.current_streak', 'desc');
                    break;
                default:
                    $query->orderBy('user_stats.games_won', 'desc');
            }

            return $query->limit($limit)->get()->toArray();
        });
    }

    /**
     * Optimiser les requêtes pour l'historique des jeux
     */
    public function optimizeGameHistoryQuery(int $userId, int $limit = 20): array
    {
        $cacheKey = "game_history_{$userId}_{$limit}";
        
        return Cache::remember($cacheKey, 180, function () use ($userId, $limit) {
            return Game::whereHas('gameRoom.players', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->with([
                'gameRoom:id,code,name,bet_amount,is_exhibition,pot_amount',
                'gameRoom.players:id,pseudo,avatar'
            ])
            ->select([
                'id', 'game_room_id', 'status', 'round_number', 'winner_id',
                'created_at', 'updated_at'
            ])
            ->where('status', Game::STATUS_COMPLETED)
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
        });
    }

    /**
     * Générer les statistiques utilisateur
     */
    private function generateUserStats(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) return;

        // Calculer les statistiques depuis les jeux
        $gamesPlayed = Game::whereHas('gameRoom.players', function ($q) use ($userId) {
            $q->where('users.id', $userId);
        })->where('status', Game::STATUS_COMPLETED)->count();

        $gamesWon = Game::whereHas('gameRoom.players', function ($q) use ($userId) {
            $q->where('users.id', $userId);
        })->where('status', Game::STATUS_COMPLETED)
          ->where('winner_id', $userId)
          ->count();

        $winRate = $gamesPlayed > 0 ? round(($gamesWon / $gamesPlayed) * 100, 2) : 0;

        // Calculer les gains/pertes
        $totalWinnings = Transaction::where('user_id', $userId)
            ->where('type', Transaction::TYPE_GAME_WIN)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->sum('amount');

        $totalLosses = Transaction::where('user_id', $userId)
            ->where('type', Transaction::TYPE_GAME_LOSS)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->sum('amount');

        // Sauvegarder les statistiques
        UserStats::updateOrCreate(
            ['user_id' => $userId],
            [
                'games_played' => $gamesPlayed,
                'games_won' => $gamesWon,
                'games_lost' => $gamesPlayed - $gamesWon,
                'win_rate' => $winRate,
                'total_winnings' => $totalWinnings,
                'total_losses' => abs($totalLosses),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Vider le cache des statistiques
     */
    public function clearStatsCache(int $userId): void
    {
        Cache::forget("user_stats_{$userId}");
        Cache::forget("game_history_{$userId}_20");
        
        // Vider aussi le cache du classement
        Cache::forget('leaderboard_wins_10');
        Cache::forget('leaderboard_winrate_10');
        Cache::forget('leaderboard_money_10');
        Cache::forget('leaderboard_streak_10');
    }
}