<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserStats;
use App\Models\Game;
use App\Models\GameRoom;
use App\Events\AchievementUnlocked;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AchievementService
{
    /**
     * Achievements détaillés avec conditions et récompenses
     */
    const ACHIEVEMENTS = [
        // Achievements de base
        'first_win' => [
            'name' => 'Première victoire',
            'description' => 'Remporter sa première partie',
            'icon' => '🏆',
            'category' => 'milestone',
            'rarity' => 'common',
            'points' => 10,
            'reward' => 100,
            'condition' => 'games_won >= 1'
        ],
        'first_steps' => [
            'name' => 'Premiers pas',
            'description' => 'Jouer 5 parties',
            'icon' => '👶',
            'category' => 'milestone',
            'rarity' => 'common',
            'points' => 5,
            'reward' => 50,
            'condition' => 'games_played >= 5'
        ],
        
        // Achievements de série
        'streak_3' => [
            'name' => 'En feu',
            'description' => '3 victoires d\'affilée',
            'icon' => '🔥',
            'category' => 'streak',
            'rarity' => 'common',
            'points' => 20,
            'reward' => 200,
            'condition' => 'current_streak >= 3'
        ],
        'streak_5' => [
            'name' => 'Inarrêtable',
            'description' => '5 victoires d\'affilée',
            'icon' => '⚡',
            'category' => 'streak',
            'rarity' => 'uncommon',
            'points' => 50,
            'reward' => 500,
            'condition' => 'current_streak >= 5'
        ],
        'streak_10' => [
            'name' => 'Légende',
            'description' => '10 victoires d\'affilée',
            'icon' => '💫',
            'category' => 'streak',
            'rarity' => 'rare',
            'points' => 100,
            'reward' => 1000,
            'condition' => 'current_streak >= 10'
        ],
        'streak_20' => [
            'name' => 'Divinité',
            'description' => '20 victoires d\'affilée',
            'icon' => '👑',
            'category' => 'streak',
            'rarity' => 'legendary',
            'points' => 250,
            'reward' => 2500,
            'condition' => 'current_streak >= 20'
        ],
        
        // Achievements de volume
        'games_10' => [
            'name' => 'Joueur régulier',
            'description' => '10 parties jouées',
            'icon' => '🎮',
            'category' => 'volume',
            'rarity' => 'common',
            'points' => 15,
            'reward' => 150,
            'condition' => 'games_played >= 10'
        ],
        'games_50' => [
            'name' => 'Passionné',
            'description' => '50 parties jouées',
            'icon' => '🎯',
            'category' => 'volume',
            'rarity' => 'uncommon',
            'points' => 30,
            'reward' => 300,
            'condition' => 'games_played >= 50'
        ],
        'games_100' => [
            'name' => 'Vétéran',
            'description' => '100 parties jouées',
            'icon' => '💯',
            'category' => 'volume',
            'rarity' => 'rare',
            'points' => 75,
            'reward' => 750,
            'condition' => 'games_played >= 100'
        ],
        'games_500' => [
            'name' => 'Maître',
            'description' => '500 parties jouées',
            'icon' => '🏅',
            'category' => 'volume',
            'rarity' => 'epic',
            'points' => 150,
            'reward' => 1500,
            'condition' => 'games_played >= 500'
        ],
        
        // Achievements financiers
        'big_winner' => [
            'name' => 'Gros gain',
            'description' => 'Remporter plus de 10,000 FCFA en une partie',
            'icon' => '💰',
            'category' => 'financial',
            'rarity' => 'uncommon',
            'points' => 40,
            'reward' => 400,
            'condition' => 'biggest_win >= 10000'
        ],
        'whale' => [
            'name' => 'Baleine',
            'description' => 'Miser plus de 100,000 FCFA au total',
            'icon' => '🐋',
            'category' => 'financial',
            'rarity' => 'rare',
            'points' => 80,
            'reward' => 800,
            'condition' => 'total_bet >= 100000'
        ],
        'profitable' => [
            'name' => 'Rentable',
            'description' => 'Avoir un profit positif de 50,000 FCFA',
            'icon' => '📈',
            'category' => 'financial',
            'rarity' => 'epic',
            'points' => 120,
            'reward' => 1200,
            'condition' => 'profit >= 50000'
        ],
        
        // Achievements de performance
        'perfect_game' => [
            'name' => 'Partie parfaite',
            'description' => 'Remporter une partie sans passer',
            'icon' => '✨',
            'category' => 'performance',
            'rarity' => 'rare',
            'points' => 60,
            'reward' => 600,
            'condition' => 'custom'
        ],
        'speed_demon' => [
            'name' => 'Démon de vitesse',
            'description' => 'Remporter une partie en moins de 60 secondes',
            'icon' => '⏱️',
            'category' => 'performance',
            'rarity' => 'epic',
            'points' => 100,
            'reward' => 1000,
            'condition' => 'custom'
        ],
        'high_roller' => [
            'name' => 'Gros joueur',
            'description' => 'Jouer une partie avec une mise de 50,000 FCFA',
            'icon' => '🎰',
            'category' => 'financial',
            'rarity' => 'rare',
            'points' => 90,
            'reward' => 900,
            'condition' => 'custom'
        ],
        
        // Achievements sociaux
        'bot_slayer' => [
            'name' => 'Tueur de bots',
            'description' => 'Battre 10 bots différents',
            'icon' => '🤖',
            'category' => 'social',
            'rarity' => 'uncommon',
            'points' => 35,
            'reward' => 350,
            'condition' => 'custom'
        ],
        'giant_killer' => [
            'name' => 'Tueur de géants',
            'description' => 'Battre un joueur avec un winrate > 80%',
            'icon' => '⚔️',
            'category' => 'social',
            'rarity' => 'epic',
            'points' => 110,
            'reward' => 1100,
            'condition' => 'custom'
        ],
        
        // Achievements temporels
        'early_bird' => [
            'name' => 'Lève-tôt',
            'description' => 'Jouer avant 6h du matin',
            'icon' => '🌅',
            'category' => 'temporal',
            'rarity' => 'uncommon',
            'points' => 25,
            'reward' => 250,
            'condition' => 'custom'
        ],
        'night_owl' => [
            'name' => 'Oiseau de nuit',
            'description' => 'Jouer après minuit',
            'icon' => '🦉',
            'category' => 'temporal',
            'rarity' => 'uncommon',
            'points' => 25,
            'reward' => 250,
            'condition' => 'custom'
        ],
        'weekend_warrior' => [
            'name' => 'Guerrier du weekend',
            'description' => 'Jouer 10 parties en weekend',
            'icon' => '🏖️',
            'category' => 'temporal',
            'rarity' => 'common',
            'points' => 20,
            'reward' => 200,
            'condition' => 'custom'
        ]
    ];

    /**
     * Vérifier et débloquer les achievements après une partie
     */
    public function checkAchievements(User $user, Game $game): array
    {
        $stats = $user->stats ?? $user->stats()->create([]);
        $unlockedAchievements = [];

        foreach (self::ACHIEVEMENTS as $key => $achievement) {
            if ($this->hasAchievement($stats, $key)) {
                continue; // Déjà débloqué
            }

            if ($this->checkCondition($stats, $game, $achievement)) {
                $unlockedAchievements[] = $this->unlockAchievement($stats, $key, $achievement);
            }
        }

        return $unlockedAchievements;
    }

    /**
     * Vérifier si un achievement est déjà débloqué
     */
    private function hasAchievement(UserStats $stats, string $key): bool
    {
        $achievements = $stats->achievements ?? [];
        return isset($achievements[$key]);
    }

    /**
     * Vérifier la condition d'un achievement
     */
    private function checkCondition(UserStats $stats, Game $game, array $achievement): bool
    {
        $condition = $achievement['condition'];

        if ($condition === 'custom') {
            return $this->checkCustomCondition($stats, $game, $achievement);
        }

        // Évaluer la condition standard
        return $this->evaluateCondition($stats, $condition);
    }

    /**
     * Évaluer une condition standard
     */
    private function evaluateCondition(UserStats $stats, string $condition): bool
    {
        // Remplacer les variables par les valeurs réelles
        $condition = str_replace([
            'games_won', 'games_played', 'current_streak', 'biggest_win',
            'total_bet', 'profit'
        ], [
            $stats->games_won, $stats->games_played, $stats->current_streak,
            $stats->biggest_win, $stats->total_bet, $stats->profit
        ], $condition);

        // Évaluer la condition (attention à la sécurité)
        try {
            return eval("return $condition;");
        } catch (\Exception $e) {
            Log::error('Erreur dans l\'évaluation de condition', [
                'condition' => $condition,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Vérifier les conditions personnalisées
     */
    private function checkCustomCondition(UserStats $stats, Game $game, array $achievement): bool
    {
        $user = $stats->user;
        $achievementKey = array_search($achievement, self::ACHIEVEMENTS);

        switch ($achievementKey) {
            case 'perfect_game':
                return $this->checkPerfectGame($game);
            
            case 'speed_demon':
                return $this->checkSpeedDemon($game);
            
            case 'high_roller':
                return $this->checkHighRoller($game);
            
            case 'bot_slayer':
                return $this->checkBotSlayer($user);
            
            case 'giant_killer':
                return $this->checkGiantKiller($game);
            
            case 'early_bird':
                return now()->hour < 6;
            
            case 'night_owl':
                return now()->hour >= 0 && now()->hour < 6;
            
            case 'weekend_warrior':
                return $this->checkWeekendWarrior($user);
            
            default:
                return false;
        }
    }

    /**
     * Vérifier partie parfaite (sans passer)
     */
    private function checkPerfectGame(Game $game): bool
    {
        $passCount = $game->moves()
            ->where('move_type', 'pass')
            ->where('player_id', $game->round_winner_id)
            ->count();
        
        return $passCount === 0;
    }

    /**
     * Vérifier partie rapide
     */
    private function checkSpeedDemon(Game $game): bool
    {
        return $game->duration && $game->duration < 60;
    }

    /**
     * Vérifier gros joueur
     */
    private function checkHighRoller(Game $game): bool
    {
        return $game->gameRoom->bet_amount >= 50000;
    }

    /**
     * Vérifier tueur de bots
     */
    private function checkBotSlayer(User $user): bool
    {
        $botWins = Game::where('round_winner_id', $user->id)
            ->whereHas('gameRoom.players', function ($query) {
                $query->where('is_bot', true);
            })
            ->distinct('current_player_id')
            ->count();
        
        return $botWins >= 10;
    }

    /**
     * Vérifier tueur de géants
     */
    private function checkGiantKiller(Game $game): bool
    {
        $opponents = $game->gameRoom->players()
            ->where('user_id', '!=', $game->round_winner_id)
            ->with('stats')
            ->get();
        
        foreach ($opponents as $opponent) {
            if ($opponent->stats && $opponent->stats->win_rate > 80) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Vérifier guerrier du weekend
     */
    private function checkWeekendWarrior(User $user): bool
    {
        $weekendGames = Game::whereHas('gameRoom.players', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->whereIn(\DB::raw('DAYOFWEEK(created_at)'), [1, 7]) // Dimanche=1, Samedi=7
            ->count();
        
        return $weekendGames >= 10;
    }

    /**
     * Débloquer un achievement
     */
    private function unlockAchievement(UserStats $stats, string $key, array $achievement): array
    {
        $achievements = $stats->achievements ?? [];
        $achievements[$key] = now()->toISOString();
        $stats->achievements = $achievements;
        $stats->save();

        // Ajouter la récompense
        if ($achievement['reward'] > 0) {
            $stats->user->increment('balance', $achievement['reward']);
            
            // Créer une transaction
            $stats->user->transactions()->create([
                'type' => 'achievement_reward',
                'amount' => $achievement['reward'],
                'status' => 'completed',
                'reference' => 'ACH_' . strtoupper($key) . '_' . time(),
                'metadata' => [
                    'achievement_key' => $key,
                    'achievement_name' => $achievement['name'],
                    'points' => $achievement['points']
                ]
            ]);
        }

        $unlockedData = array_merge($achievement, [
            'key' => $key,
            'unlocked_at' => now()->toISOString()
        ]);

        // Diffuser l'événement
        event(new AchievementUnlocked($stats->user, $unlockedData));

        Log::info('Achievement débloqué', [
            'user_id' => $stats->user_id,
            'achievement' => $key,
            'reward' => $achievement['reward']
        ]);

        return $unlockedData;
    }

    /**
     * Obtenir les achievements d'un utilisateur
     */
    public function getUserAchievements(User $user): array
    {
        $stats = $user->stats;
        if (!$stats) {
            return [
                'unlocked' => [],
                'locked' => array_values(self::ACHIEVEMENTS),
                'total_points' => 0,
                'progress' => 0
            ];
        }

        $achievements = $stats->achievements ?? [];
        $unlocked = [];
        $locked = [];
        $totalPoints = 0;

        foreach (self::ACHIEVEMENTS as $key => $achievement) {
            if (isset($achievements[$key])) {
                $unlocked[] = array_merge($achievement, [
                    'key' => $key,
                    'unlocked_at' => $achievements[$key]
                ]);
                $totalPoints += $achievement['points'];
            } else {
                $locked[] = array_merge($achievement, [
                    'key' => $key,
                    'progress' => $this->getAchievementProgress($stats, $key, $achievement)
                ]);
            }
        }

        return [
            'unlocked' => $unlocked,
            'locked' => $locked,
            'total_points' => $totalPoints,
            'progress' => round((count($unlocked) / count(self::ACHIEVEMENTS)) * 100, 2)
        ];
    }

    /**
     * Obtenir le progrès d'un achievement
     */
    private function getAchievementProgress(UserStats $stats, string $key, array $achievement): array
    {
        $condition = $achievement['condition'];
        
        if ($condition === 'custom') {
            return ['percentage' => 0, 'current' => 0, 'target' => 1];
        }

        // Extraire la valeur cible de la condition
        if (preg_match('/(\w+)\s*>=\s*(\d+)/', $condition, $matches)) {
            $field = $matches[1];
            $target = (int)$matches[2];
            $current = $stats->$field ?? 0;
            
            return [
                'percentage' => min(100, ($current / $target) * 100),
                'current' => $current,
                'target' => $target
            ];
        }

        return ['percentage' => 0, 'current' => 0, 'target' => 1];
    }

    /**
     * Obtenir le leaderboard des achievements
     */
    public function getAchievementLeaderboard(int $limit = 10): array
    {
        $leaderboard = UserStats::with('user:id,pseudo,avatar')
            ->get()
            ->map(function ($stats) {
                $achievements = $stats->achievements ?? [];
                $points = 0;
                
                foreach ($achievements as $key => $unlockedAt) {
                    if (isset(self::ACHIEVEMENTS[$key])) {
                        $points += self::ACHIEVEMENTS[$key]['points'];
                    }
                }
                
                return [
                    'user' => $stats->user,
                    'achievements_count' => count($achievements),
                    'total_points' => $points,
                    'completion_rate' => round((count($achievements) / count(self::ACHIEVEMENTS)) * 100, 2)
                ];
            })
            ->sortByDesc('total_points')
            ->take($limit)
            ->values()
            ->all();

        return $leaderboard;
    }

    /**
     * Obtenir les statistiques globales des achievements
     */
    public function getGlobalAchievementStats(): array
    {
        $totalUsers = UserStats::count();
        $achievements = [];

        foreach (self::ACHIEVEMENTS as $key => $achievement) {
            $unlockedCount = UserStats::whereJsonContains('achievements', [$key => true])->count();
            
            $achievements[] = [
                'key' => $key,
                'name' => $achievement['name'],
                'category' => $achievement['category'],
                'rarity' => $achievement['rarity'],
                'unlocked_count' => $unlockedCount,
                'unlock_rate' => $totalUsers > 0 ? round(($unlockedCount / $totalUsers) * 100, 2) : 0
            ];
        }

        return [
            'total_achievements' => count(self::ACHIEVEMENTS),
            'total_users' => $totalUsers,
            'achievements' => $achievements
        ];
    }
}