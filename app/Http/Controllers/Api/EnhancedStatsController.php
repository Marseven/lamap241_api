<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use App\Services\AchievementService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EnhancedStatsController extends Controller
{
    private StatsService $statsService;
    private AchievementService $achievementService;

    public function __construct(StatsService $statsService, AchievementService $achievementService)
    {
        $this->statsService = $statsService;
        $this->achievementService = $achievementService;
    }

    /**
     * Obtenir les statistiques détaillées de l'utilisateur connecté
     */
    public function getMyDetailedStats()
    {
        try {
            $user = auth()->user();
            $stats = $this->statsService->getUserDetailedStats($user);
            
            return response()->json([
                'message' => 'Statistiques détaillées récupérées',
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques détaillées', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error_code' => 'STATS_ERROR'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques détaillées d'un utilisateur spécifique
     */
    public function getUserDetailedStats(int $userId)
    {
        try {
            $user = User::findOrFail($userId);
            $stats = $this->statsService->getUserDetailedStats($user);
            
            return response()->json([
                'message' => 'Statistiques utilisateur récupérées',
                'user' => [
                    'id' => $user->id,
                    'pseudo' => $user->pseudo,
                    'avatar' => $user->avatar
                ],
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques utilisateur', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error_code' => 'USER_STATS_ERROR'
            ], 500);
        }
    }

    /**
     * Obtenir tous les classements
     */
    public function getAllLeaderboards()
    {
        try {
            $leaderboards = $this->statsService->getLeaderboards();
            
            return response()->json([
                'message' => 'Classements récupérés',
                'leaderboards' => $leaderboards
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des classements', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des classements',
                'error_code' => 'LEADERBOARDS_ERROR'
            ], 500);
        }
    }

    /**
     * Obtenir un classement spécifique
     */
    public function getLeaderboard(Request $request, string $type)
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);
        
        try {
            $limit = $request->get('limit', 10);
            $leaderboards = $this->statsService->getLeaderboards();
            
            if (!isset($leaderboards[$type])) {
                return response()->json([
                    'message' => 'Type de classement invalide',
                    'error_code' => 'INVALID_LEADERBOARD_TYPE'
                ], 400);
            }
            
            return response()->json([
                'message' => 'Classement récupéré',
                'type' => $type,
                'leaderboard' => array_slice($leaderboards[$type], 0, $limit)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération du classement', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération du classement',
                'error_code' => 'LEADERBOARD_ERROR'
            ], 500);
        }
    }

    /**
     * Obtenir les achievements de l'utilisateur connecté
     */
    public function getMyAchievements()
    {
        try {
            $user = auth()->user();
            $achievements = $this->achievementService->getUserAchievements($user);
            
            return response()->json([
                'message' => 'Achievements récupérés',
                'achievements' => $achievements
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des achievements', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des achievements',
                'error_code' => 'ACHIEVEMENTS_ERROR'
            ], 500);
        }
    }

    /**
     * Obtenir les achievements d'un utilisateur spécifique
     */
    public function getUserAchievements(int $userId)
    {
        try {
            $user = User::findOrFail($userId);
            $achievements = $this->achievementService->getUserAchievements($user);
            
            return response()->json([
                'message' => 'Achievements utilisateur récupérés',
                'user' => [
                    'id' => $user->id,
                    'pseudo' => $user->pseudo,
                    'avatar' => $user->avatar
                ],
                'achievements' => $achievements
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des achievements utilisateur', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des achievements',
                'error_code' => 'USER_ACHIEVEMENTS_ERROR'
            ], 500);
        }
    }

    /**
     * Obtenir le classement des achievements
     */
    public function getAchievementLeaderboard(Request $request)
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);
        
        try {
            $limit = $request->get('limit', 10);
            $leaderboard = $this->achievementService->getAchievementLeaderboard($limit);
            
            return response()->json([
                'message' => 'Classement des achievements récupéré',
                'leaderboard' => $leaderboard
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération du classement des achievements', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération du classement',
                'error_code' => 'ACHIEVEMENT_LEADERBOARD_ERROR'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques globales des achievements
     */
    public function getGlobalAchievementStats()
    {
        try {
            $stats = $this->achievementService->getGlobalAchievementStats();
            
            return response()->json([
                'message' => 'Statistiques globales des achievements récupérées',
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques globales des achievements', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error_code' => 'GLOBAL_ACHIEVEMENT_STATS_ERROR'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques globales du jeu
     */
    public function getGlobalStats()
    {
        try {
            $stats = $this->statsService->getGlobalStats();
            
            return response()->json([
                'message' => 'Statistiques globales récupérées',
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques globales', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error_code' => 'GLOBAL_STATS_ERROR'
            ], 500);
        }
    }

    /**
     * Comparer deux utilisateurs
     */
    public function compareUsers(Request $request, int $userId1, int $userId2)
    {
        try {
            $user1 = User::findOrFail($userId1);
            $user2 = User::findOrFail($userId2);
            
            $stats1 = $this->statsService->getUserDetailedStats($user1);
            $stats2 = $this->statsService->getUserDetailedStats($user2);
            
            $comparison = [
                'user1' => [
                    'user' => ['id' => $user1->id, 'pseudo' => $user1->pseudo, 'avatar' => $user1->avatar],
                    'stats' => $stats1
                ],
                'user2' => [
                    'user' => ['id' => $user2->id, 'pseudo' => $user2->pseudo, 'avatar' => $user2->avatar],
                    'stats' => $stats2
                ],
                'comparison' => [
                    'win_rate_diff' => $stats1['basic_stats']['win_rate'] - $stats2['basic_stats']['win_rate'],
                    'games_played_diff' => $stats1['basic_stats']['games_played'] - $stats2['basic_stats']['games_played'],
                    'profit_diff' => $stats1['financial_stats']['profit'] - $stats2['financial_stats']['profit'],
                    'achievements_diff' => $stats1['achievements']['total_points'] - $stats2['achievements']['total_points']
                ]
            ];
            
            return response()->json([
                'message' => 'Comparaison des utilisateurs effectuée',
                'comparison' => $comparison
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la comparaison des utilisateurs', [
                'user1_id' => $userId1,
                'user2_id' => $userId2,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la comparaison',
                'error_code' => 'COMPARISON_ERROR'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques de progression
     */
    public function getProgressStats()
    {
        try {
            $user = auth()->user();
            $stats = $user->stats;
            
            if (!$stats) {
                return response()->json([
                    'message' => 'Aucune statistique disponible',
                    'progress' => []
                ]);
            }
            
            $progress = [
                'level' => $this->calculateLevel($stats->games_played),
                'experience' => $stats->games_played,
                'next_level_experience' => $this->getNextLevelExperience($stats->games_played),
                'level_progress' => $this->getLevelProgress($stats->games_played),
                'milestones' => $this->getMilestones($stats),
                'next_achievements' => $this->getNextAchievements($user)
            ];
            
            return response()->json([
                'message' => 'Statistiques de progression récupérées',
                'progress' => $progress
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques de progression', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error_code' => 'PROGRESS_STATS_ERROR'
            ], 500);
        }
    }

    /**
     * Méthodes utilitaires pour la progression
     */
    private function calculateLevel(int $gamesPlayed): int
    {
        // Niveau basé sur le nombre de parties jouées
        return min(100, (int) sqrt($gamesPlayed / 5) + 1);
    }

    private function getNextLevelExperience(int $gamesPlayed): int
    {
        $currentLevel = $this->calculateLevel($gamesPlayed);
        return pow($currentLevel, 2) * 5;
    }

    private function getLevelProgress(int $gamesPlayed): float
    {
        $currentLevel = $this->calculateLevel($gamesPlayed);
        $currentLevelExp = pow($currentLevel - 1, 2) * 5;
        $nextLevelExp = pow($currentLevel, 2) * 5;
        
        return (($gamesPlayed - $currentLevelExp) / ($nextLevelExp - $currentLevelExp)) * 100;
    }

    private function getMilestones($stats): array
    {
        $milestones = [];
        
        $targets = [
            ['games_played', 10, 'Novice'],
            ['games_played', 50, 'Expérimenté'],
            ['games_played', 100, 'Vétéran'],
            ['games_won', 10, 'Gagnant'],
            ['games_won', 50, 'Champion'],
            ['current_streak', 5, 'Série']
        ];
        
        foreach ($targets as [$field, $target, $name]) {
            $current = $stats->$field ?? 0;
            $milestones[] = [
                'name' => $name,
                'current' => $current,
                'target' => $target,
                'completed' => $current >= $target,
                'progress' => min(100, ($current / $target) * 100)
            ];
        }
        
        return $milestones;
    }

    private function getNextAchievements(User $user): array
    {
        $achievements = $this->achievementService->getUserAchievements($user);
        return array_slice($achievements['locked'], 0, 3); // 3 prochains achievements
    }
}