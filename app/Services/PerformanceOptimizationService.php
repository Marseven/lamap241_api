<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class PerformanceOptimizationService
{
    /**
     * Optimiser la configuration de la base de données
     */
    public function optimizeDatabaseConfig(): void
    {
        // Augmenter la taille des pools de connexions
        Config::set('database.connections.mysql.options', [
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode="STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO"',
            \PDO::ATTR_TIMEOUT => 30,
            \PDO::ATTR_PERSISTENT => true,
        ]);
        
        // Optimiser la configuration MySQL
        DB::statement('SET SESSION query_cache_type = ON');
        DB::statement('SET SESSION query_cache_size = 67108864'); // 64MB
        DB::statement('SET SESSION innodb_buffer_pool_size = 134217728'); // 128MB
    }

    /**
     * Optimiser la configuration du cache
     */
    public function optimizeCacheConfig(): void
    {
        // Configuration du cache pour Redis/Database
        $cacheConfig = [
            'default_ttl' => 3600, // 1 heure
            'user_stats_ttl' => 300, // 5 minutes
            'leaderboard_ttl' => 600, // 10 minutes
            'game_rooms_ttl' => 60, // 1 minute
            'game_state_ttl' => 30, // 30 secondes
        ];
        
        Cache::put('cache_config', $cacheConfig, 86400); // 24 heures
    }

    /**
     * Précharger les données critiques en cache
     */
    public function preloadCriticalData(): void
    {
        Log::info('Préchargement des données critiques...');
        
        // Précharger les paramètres de l'application
        $this->preloadAppSettings();
        
        // Précharger les données de jeu
        $this->preloadGameData();
        
        // Précharger les statistiques globales
        $this->preloadGlobalStats();
        
        Log::info('Préchargement terminé');
    }

    /**
     * Nettoyer le cache expiré
     */
    public function cleanExpiredCache(): void
    {
        Log::info('Nettoyage du cache expiré...');
        
        // Nettoyer les caches utilisateur inactifs
        $this->cleanUserCaches();
        
        // Nettoyer les caches de jeu terminés
        $this->cleanGameCaches();
        
        // Nettoyer les caches de transaction
        $this->cleanTransactionCaches();
        
        Log::info('Nettoyage du cache terminé');
    }

    /**
     * Optimiser les requêtes lentes
     */
    public function optimizeSlowQueries(): void
    {
        Log::info('Optimisation des requêtes lentes...');
        
        // Analyser les requêtes lentes
        $slowQueries = DB::select('SHOW PROCESSLIST');
        
        foreach ($slowQueries as $query) {
            if (isset($query->Time) && $query->Time > 5) {
                Log::warning('Requête lente détectée', [
                    'query' => $query->Info,
                    'time' => $query->Time,
                    'user' => $query->User,
                ]);
            }
        }
        
        // Optimiser les tables
        $this->optimizeTables();
        
        Log::info('Optimisation des requêtes terminée');
    }

    /**
     * Générer un rapport de performance
     */
    public function generatePerformanceReport(): array
    {
        return [
            'database' => $this->getDatabaseStats(),
            'cache' => $this->getCacheStats(),
            'memory' => $this->getMemoryStats(),
            'api' => $this->getApiStats(),
            'recommendations' => $this->getRecommendations(),
        ];
    }

    /**
     * Précharger les paramètres de l'application
     */
    private function preloadAppSettings(): void
    {
        $settings = [
            'game_settings' => [
                'min_bet' => 500,
                'max_bet' => 100000,
                'min_players' => 2,
                'max_players' => 4,
                'default_rounds' => 3,
                'max_rounds' => 10,
            ],
            'payment_settings' => [
                'min_deposit' => 500,
                'max_deposit' => 100000,
                'min_withdrawal' => 1000,
                'max_withdrawal' => 50000,
                'commission_rate' => 0.10,
            ],
        ];
        
        foreach ($settings as $key => $value) {
            Cache::put("app_settings_{$key}", $value, 86400);
        }
    }

    /**
     * Précharger les données de jeu
     */
    private function preloadGameData(): void
    {
        // Cartes de base
        $cards = [];
        $suits = ['hearts', 'diamonds', 'clubs', 'spades'];
        
        for ($value = 2; $value <= 14; $value++) {
            foreach ($suits as $suit) {
                $cards[] = ['value' => $value, 'suit' => $suit];
            }
        }
        
        Cache::put('game_cards', $cards, 86400);
        
        // Règles de jeu
        $rules = [
            'card_values' => [
                2 => 'Deux', 3 => 'Trois', 4 => 'Quatre', 5 => 'Cinq',
                6 => 'Six', 7 => 'Sept', 8 => 'Huit', 9 => 'Neuf',
                10 => 'Dix', 11 => 'Valet', 12 => 'Dame', 13 => 'Roi', 14 => 'As'
            ],
            'suit_names' => [
                'hearts' => 'Cœur', 'diamonds' => 'Carreau',
                'clubs' => 'Trèfle', 'spades' => 'Pique'
            ],
        ];
        
        Cache::put('game_rules', $rules, 86400);
    }

    /**
     * Précharger les statistiques globales
     */
    private function preloadGlobalStats(): void
    {
        $stats = [
            'total_users' => DB::table('users')->count(),
            'total_games' => DB::table('games')->count(),
            'total_transactions' => DB::table('transactions')->count(),
            'active_rooms' => DB::table('game_rooms')->where('status', 'waiting')->count(),
            'in_progress_games' => DB::table('games')->where('status', 'in_progress')->count(),
        ];
        
        Cache::put('global_stats', $stats, 300);
    }

    /**
     * Nettoyer les caches utilisateur
     */
    private function cleanUserCaches(): void
    {
        // Trouver les utilisateurs inactifs depuis plus de 24h
        $inactiveUsers = DB::table('users')
            ->where('updated_at', '<', now()->subDay())
            ->pluck('id');
        
        foreach ($inactiveUsers as $userId) {
            Cache::forget("user_stats_{$userId}");
            Cache::forget("game_history_{$userId}_20");
            Cache::forget("user_achievements_{$userId}");
        }
    }

    /**
     * Nettoyer les caches de jeu
     */
    private function cleanGameCaches(): void
    {
        // Trouver les jeux terminés depuis plus de 1 heure
        $completedGames = DB::table('games')
            ->where('status', 'completed')
            ->where('updated_at', '<', now()->subHour())
            ->pluck('id');
        
        foreach ($completedGames as $gameId) {
            Cache::forget("game_state_{$gameId}");
            Cache::forget("game_moves_{$gameId}");
        }
    }

    /**
     * Nettoyer les caches de transaction
     */
    private function cleanTransactionCaches(): void
    {
        // Nettoyer les caches de transaction anciennes
        $oldTransactions = DB::table('transactions')
            ->where('created_at', '<', now()->subHours(6))
            ->pluck('reference');
        
        foreach ($oldTransactions as $reference) {
            Cache::forget("transaction_status_{$reference}");
        }
    }

    /**
     * Optimiser les tables
     */
    private function optimizeTables(): void
    {
        $tables = [
            'users', 'game_rooms', 'games', 'game_moves',
            'transactions', 'wallets', 'user_stats', 'achievements'
        ];
        
        foreach ($tables as $table) {
            try {
                DB::statement("OPTIMIZE TABLE {$table}");
                Log::info("Table {$table} optimisée");
            } catch (\Exception $e) {
                Log::error("Erreur lors de l'optimisation de la table {$table}: " . $e->getMessage());
            }
        }
    }

    /**
     * Obtenir les statistiques de la base de données
     */
    private function getDatabaseStats(): array
    {
        $stats = DB::select('SHOW STATUS');
        $statusArray = [];
        
        foreach ($stats as $stat) {
            $statusArray[$stat->Variable_name] = $stat->Value;
        }
        
        return [
            'connections' => $statusArray['Threads_connected'] ?? 0,
            'queries' => $statusArray['Queries'] ?? 0,
            'slow_queries' => $statusArray['Slow_queries'] ?? 0,
            'uptime' => $statusArray['Uptime'] ?? 0,
        ];
    }

    /**
     * Obtenir les statistiques du cache
     */
    private function getCacheStats(): array
    {
        return [
            'driver' => config('cache.default'),
            'stores' => array_keys(config('cache.stores')),
            'hit_ratio' => $this->calculateCacheHitRatio(),
        ];
    }

    /**
     * Obtenir les statistiques de mémoire
     */
    private function getMemoryStats(): array
    {
        return [
            'usage' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * Obtenir les statistiques API
     */
    private function getApiStats(): array
    {
        return [
            'avg_response_time' => Cache::get('api_avg_response_time', 0),
            'requests_per_minute' => Cache::get('api_requests_per_minute', 0),
            'error_rate' => Cache::get('api_error_rate', 0),
        ];
    }

    /**
     * Calculer le taux de hit du cache
     */
    private function calculateCacheHitRatio(): float
    {
        $hits = Cache::get('cache_hits', 0);
        $misses = Cache::get('cache_misses', 0);
        $total = $hits + $misses;
        
        return $total > 0 ? ($hits / $total) * 100 : 0;
    }

    /**
     * Obtenir les recommandations d'optimisation
     */
    private function getRecommendations(): array
    {
        $recommendations = [];
        
        // Analyser les statistiques et générer des recommandations
        $dbStats = $this->getDatabaseStats();
        $memoryStats = $this->getMemoryStats();
        
        if ($dbStats['slow_queries'] > 10) {
            $recommendations[] = 'Optimiser les requêtes lentes';
        }
        
        if ($memoryStats['usage'] > $memoryStats['peak'] * 0.8) {
            $recommendations[] = 'Augmenter la limite de mémoire';
        }
        
        return $recommendations;
    }
}