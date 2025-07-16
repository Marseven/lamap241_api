<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GameTransitionService;
use App\Models\GameRoom;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class GameMaintenanceCommand extends Command
{
    protected $signature = 'game:maintenance 
                          {action : Action to perform (cleanup, timeout, stats, repair)}
                          {--room-code= : Specific room code to process}
                          {--force : Force action without confirmation}';

    protected $description = 'Perform game maintenance tasks (cleanup, timeout, stats, repair)';

    private GameTransitionService $transitionService;

    public function __construct(GameTransitionService $transitionService)
    {
        parent::__construct();
        $this->transitionService = $transitionService;
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'cleanup' => $this->cleanupStaleGames(),
            'timeout' => $this->timeoutLongGames(),
            'stats' => $this->showGameStats(),
            'repair' => $this->repairCorruptedGames(),
            'transitions' => $this->showTransitionStats(),
            default => $this->showHelp()
        };
    }

    private function cleanupStaleGames(): int
    {
        $roomCode = $this->option('room-code');
        
        if ($roomCode) {
            return $this->cleanupSpecificRoom($roomCode);
        }
        
        $this->info('Nettoyage des parties obsolètes...');
        
        // Parties abandonnées depuis plus de 24h
        $staleRooms = GameRoom::where('status', GameRoom::STATUS_PLAYING)
            ->where('started_at', '<', now()->subDay())
            ->get();
        
        $cleaned = 0;
        foreach ($staleRooms as $room) {
            if ($this->option('force') || $this->confirm("Nettoyer la salle {$room->code}?")) {
                try {
                    $this->transitionService->forceEndGame($room, 'maintenance_cleanup');
                    $this->transitionService->cleanupTransition($room);
                    $this->line("✓ Salle {$room->code} nettoyée");
                    $cleaned++;
                } catch (\Exception $e) {
                    $this->error("✗ Erreur pour la salle {$room->code}: {$e->getMessage()}");
                }
            }
        }
        
        // Nettoyer les caches de transition orphelins
        $this->cleanupOrphanedCaches();
        
        $this->info("Nettoyage terminé: {$cleaned} salles nettoyées");
        return 0;
    }

    private function cleanupSpecificRoom(string $roomCode): int
    {
        try {
            $room = GameRoom::where('code', $roomCode)->firstOrFail();
            
            $this->info("Nettoyage de la salle {$roomCode}...");
            
            if ($room->status === GameRoom::STATUS_PLAYING) {
                $this->transitionService->forceEndGame($room, 'manual_cleanup');
            }
            
            $this->transitionService->cleanupTransition($room);
            
            $this->info("✓ Salle {$roomCode} nettoyée avec succès");
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Erreur lors du nettoyage de la salle {$roomCode}: {$e->getMessage()}");
            return 1;
        }
    }

    private function timeoutLongGames(): int
    {
        $this->info('Recherche de parties trop longues...');
        
        // Parties qui dépassent leur limite de temps
        $longRooms = GameRoom::where('status', GameRoom::STATUS_PLAYING)
            ->whereNotNull('time_limit')
            ->get()
            ->filter(function ($room) {
                if (!$room->started_at) return false;
                
                $elapsed = now()->diffInMinutes($room->started_at);
                return $elapsed > $room->time_limit;
            });
        
        $timedOut = 0;
        foreach ($longRooms as $room) {
            if ($this->option('force') || $this->confirm("Timeout la salle {$room->code}?")) {
                try {
                    $this->transitionService->forceEndGame($room, 'timeout');
                    $this->line("✓ Salle {$room->code} terminée par timeout");
                    $timedOut++;
                } catch (\Exception $e) {
                    $this->error("✗ Erreur pour la salle {$room->code}: {$e->getMessage()}");
                }
            }
        }
        
        $this->info("Timeout terminé: {$timedOut} parties terminées");
        return 0;
    }

    private function showGameStats(): int
    {
        $this->info('Statistiques générales des parties:');
        $this->line('');
        
        // Statistiques des salles
        $totalRooms = GameRoom::count();
        $activeRooms = GameRoom::where('status', GameRoom::STATUS_PLAYING)->count();
        $waitingRooms = GameRoom::where('status', GameRoom::STATUS_WAITING)->count();
        $finishedRooms = GameRoom::where('status', GameRoom::STATUS_FINISHED)->count();
        
        $this->info('📊 Salles de jeu:');
        $this->line("   • Total: {$totalRooms}");
        $this->line("   • En cours: {$activeRooms}");
        $this->line("   • En attente: {$waitingRooms}");
        $this->line("   • Terminées: {$finishedRooms}");
        
        // Statistiques des jeux
        $totalGames = Game::count();
        $inProgressGames = Game::where('status', Game::STATUS_IN_PROGRESS)->count();
        $completedGames = Game::where('status', Game::STATUS_COMPLETED)->count();
        
        $this->info('🎮 Manches:');
        $this->line("   • Total: {$totalGames}");
        $this->line("   • En cours: {$inProgressGames}");
        $this->line("   • Terminées: {$completedGames}");
        
        // Statistiques des joueurs
        $totalPlayers = User::count();
        $botPlayers = User::where('is_bot', true)->count();
        $humanPlayers = $totalPlayers - $botPlayers;
        
        $this->info('👥 Joueurs:');
        $this->line("   • Total: {$totalPlayers}");
        $this->line("   • Humains: {$humanPlayers}");
        $this->line("   • Bots: {$botPlayers}");
        
        // Parties aujourd'hui
        $todayRooms = GameRoom::whereDate('created_at', today())->count();
        $todayGames = Game::whereDate('created_at', today())->count();
        
        $this->info('📅 Aujourd\'hui:');
        $this->line("   • Nouvelles salles: {$todayRooms}");
        $this->line("   • Nouvelles manches: {$todayGames}");
        
        return 0;
    }

    private function repairCorruptedGames(): int
    {
        $this->info('Recherche de parties corrompues...');
        
        $issues = [];
        
        // Jeux sans game_room
        $orphanGames = Game::whereDoesntHave('gameRoom')->get();
        if ($orphanGames->isNotEmpty()) {
            $issues[] = "Jeux orphelins: {$orphanGames->count()}";
        }
        
        // Salles avec status playing mais sans jeu actif
        $inconsistentRooms = GameRoom::where('status', GameRoom::STATUS_PLAYING)
            ->whereDoesntHave('games', function ($query) {
                $query->where('status', Game::STATUS_IN_PROGRESS);
            })
            ->get();
        
        if ($inconsistentRooms->isNotEmpty()) {
            $issues[] = "Salles incohérentes: {$inconsistentRooms->count()}";
        }
        
        // Jeux avec current_player_id invalide
        $invalidPlayerGames = Game::where('status', Game::STATUS_IN_PROGRESS)
            ->whereDoesntHave('currentPlayer')
            ->get();
        
        if ($invalidPlayerGames->isNotEmpty()) {
            $issues[] = "Jeux avec joueur invalide: {$invalidPlayerGames->count()}";
        }
        
        if (empty($issues)) {
            $this->info('✓ Aucun problème détecté');
            return 0;
        }
        
        $this->warn('Problèmes détectés:');
        foreach ($issues as $issue) {
            $this->line("   • {$issue}");
        }
        
        if (!$this->option('force') && !$this->confirm('Réparer ces problèmes?')) {
            $this->info('Réparation annulée');
            return 0;
        }
        
        $repaired = 0;
        
        // Supprimer les jeux orphelins
        foreach ($orphanGames as $game) {
            $game->delete();
            $repaired++;
        }
        
        // Réparer les salles incohérentes
        foreach ($inconsistentRooms as $room) {
            $room->update(['status' => GameRoom::STATUS_CANCELLED]);
            $repaired++;
        }
        
        // Terminer les jeux avec joueur invalide
        foreach ($invalidPlayerGames as $game) {
            $game->update(['status' => Game::STATUS_ABANDONED]);
            $repaired++;
        }
        
        $this->info("Réparation terminée: {$repaired} problèmes résolus");
        return 0;
    }

    private function showTransitionStats(): int
    {
        $this->info('Statistiques des transitions:');
        $this->line('');
        
        // Moyennes des durées de manche
        $avgGameDuration = Game::where('status', Game::STATUS_COMPLETED)
            ->whereNotNull('ended_at')
            ->get()
            ->avg('duration');
        
        $this->info('⏱️ Durées:');
        $this->line("   • Durée moyenne d'une manche: " . round($avgGameDuration, 2) . "s");
        
        // Statistiques par difficulté de bot
        $botStats = User::where('is_bot', true)
            ->selectRaw('bot_difficulty, COUNT(*) as count')
            ->groupBy('bot_difficulty')
            ->get();
        
        $this->info('🤖 Bots par difficulté:');
        foreach ($botStats as $stat) {
            $this->line("   • {$stat->bot_difficulty}: {$stat->count}");
        }
        
        // Manches par jour (7 derniers jours)
        $this->info('📈 Manches par jour (7 derniers jours):');
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Game::whereDate('created_at', $date)->count();
            $this->line("   • {$date->format('Y-m-d')}: {$count}");
        }
        
        return 0;
    }

    private function cleanupOrphanedCaches(): void
    {
        // Cette fonction pourrait être étendue pour nettoyer
        // les caches Redis orphelins si nécessaire
        $this->line('   • Nettoyage des caches orphelins...');
    }

    private function showHelp(): int
    {
        $this->info('Maintenance des parties La Map 241');
        $this->line('');
        $this->info('Actions disponibles:');
        $this->line('  cleanup     - Nettoyer les parties obsolètes');
        $this->line('  timeout     - Terminer les parties trop longues');
        $this->line('  stats       - Afficher les statistiques');
        $this->line('  repair      - Réparer les parties corrompues');
        $this->line('  transitions - Statistiques des transitions');
        $this->line('');
        $this->info('Exemples:');
        $this->line('  php artisan game:maintenance cleanup');
        $this->line('  php artisan game:maintenance timeout --force');
        $this->line('  php artisan game:maintenance cleanup --room-code=ABC123');
        $this->line('  php artisan game:maintenance stats');
        
        return 0;
    }
}