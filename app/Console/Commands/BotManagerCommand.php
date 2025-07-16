<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GameAIService;
use App\Models\User;
use App\Models\Game;
use App\Models\GameRoom;
use Illuminate\Support\Facades\Log;

class BotManagerCommand extends Command
{
    protected $signature = 'bot:manage 
                          {action : Action to perform (create, play, clean, stats)}
                          {--difficulty=medium : Bot difficulty (easy, medium, hard)}
                          {--count=1 : Number of bots to create}
                          {--game-id= : Game ID for bot to play}
                          {--room-code= : Room code to add bot to}';

    protected $description = 'Manage game bots (create, play, clean, stats)';

    private GameAIService $aiService;

    public function __construct(GameAIService $aiService)
    {
        parent::__construct();
        $this->aiService = $aiService;
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'create' => $this->createBots(),
            'play' => $this->playBotMove(),
            'clean' => $this->cleanInactiveBots(),
            'stats' => $this->showBotStats(),
            'auto-play' => $this->autoPlayBots(),
            default => $this->showHelp()
        };
    }

    private function createBots(): int
    {
        $difficulty = $this->option('difficulty');
        $count = (int) $this->option('count');

        if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
            $this->error('Difficulté invalide. Utilisez: easy, medium, hard');
            return 1;
        }

        $this->info("Création de {$count} bot(s) avec difficulté '{$difficulty}'...");

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            try {
                $bot = $this->aiService->createBot($difficulty);
                $this->line("✓ Bot créé: {$bot->pseudo} (ID: {$bot->id})");
                $created++;
            } catch (\Exception $e) {
                $this->error("✗ Erreur lors de la création du bot: {$e->getMessage()}");
            }
        }

        $this->info("Création terminée: {$created}/{$count} bots créés");
        return 0;
    }

    private function playBotMove(): int
    {
        $gameId = $this->option('game-id');

        if (!$gameId) {
            $this->error('Spécifiez un game-id avec --game-id=');
            return 1;
        }

        try {
            $game = Game::findOrFail($gameId);
            $currentPlayer = User::findOrFail($game->current_player_id);

            if (!$this->aiService->isBot($currentPlayer)) {
                $this->error("Le joueur actuel n'est pas un bot");
                return 1;
            }

            $this->info("Bot {$currentPlayer->pseudo} en train de jouer...");
            
            $result = $this->aiService->playMove($game, $currentPlayer, $currentPlayer->bot_difficulty);

            if ($result['action'] === 'play') {
                $card = $result['card'];
                $this->info("✓ Bot a joué: {$card['value']}{$card['suit']} - {$result['reasoning']}");
            } else {
                $this->info("✓ Bot a passé - {$result['reasoning']}");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Erreur: {$e->getMessage()}");
            return 1;
        }
    }

    private function cleanInactiveBots(): int
    {
        $this->info('Nettoyage des bots inactifs...');

        // Bots inactifs depuis plus de 7 jours
        $inactiveBots = User::where('is_bot', true)
            ->where('last_bot_activity', '<', now()->subDays(7))
            ->get();

        $deleted = 0;
        foreach ($inactiveBots as $bot) {
            // Vérifier qu'il n'est pas dans une partie active
            $activeGame = Game::where('current_player_id', $bot->id)
                ->where('status', Game::STATUS_IN_PROGRESS)
                ->exists();

            if (!$activeGame) {
                $this->line("Suppression du bot inactif: {$bot->pseudo}");
                $bot->delete();
                $deleted++;
            }
        }

        $this->info("Nettoyage terminé: {$deleted} bots supprimés");
        return 0;
    }

    private function showBotStats(): int
    {
        $bots = User::where('is_bot', true)->with('stats')->get();

        if ($bots->isEmpty()) {
            $this->info('Aucun bot trouvé');
            return 0;
        }

        $this->info('Statistiques des bots:');
        $this->line('');

        $headers = ['ID', 'Pseudo', 'Difficulté', 'Parties', 'Victoires', 'Taux', 'Dernière activité'];
        $rows = [];

        foreach ($bots as $bot) {
            $stats = $this->aiService->getBotStats($bot);
            $rows[] = [
                $bot->id,
                $bot->pseudo,
                $bot->bot_difficulty,
                $stats['total_games'] ?? 0,
                $bot->stats->games_won ?? 0,
                $stats['win_rate'] . '%',
                $bot->last_bot_activity ? $bot->last_bot_activity->diffForHumans() : 'Jamais'
            ];
        }

        $this->table($headers, $rows);

        // Statistiques générales
        $totalBots = $bots->count();
        $activeBots = $bots->where('last_bot_activity', '>', now()->subDay())->count();
        $easyBots = $bots->where('bot_difficulty', 'easy')->count();
        $mediumBots = $bots->where('bot_difficulty', 'medium')->count();
        $hardBots = $bots->where('bot_difficulty', 'hard')->count();

        $this->info('');
        $this->info("Total bots: {$totalBots}");
        $this->info("Bots actifs (24h): {$activeBots}");
        $this->info("Facile: {$easyBots} | Moyen: {$mediumBots} | Difficile: {$hardBots}");

        return 0;
    }

    private function autoPlayBots(): int
    {
        $this->info('Recherche de bots en attente de jouer...');

        // Trouver les jeux où c'est le tour d'un bot
        $games = Game::where('status', Game::STATUS_IN_PROGRESS)
            ->whereHas('currentPlayer', function ($query) {
                $query->where('is_bot', true);
            })
            ->with('currentPlayer')
            ->get();

        if ($games->isEmpty()) {
            $this->info('Aucun bot en attente de jouer');
            return 0;
        }

        $played = 0;
        foreach ($games as $game) {
            try {
                $bot = $game->currentPlayer;
                $this->line("Bot {$bot->pseudo} joue dans le jeu {$game->id}...");

                $result = $this->aiService->playMove($game, $bot, $bot->bot_difficulty);

                if ($result['action'] === 'play') {
                    $card = $result['card'];
                    $this->line("  ✓ Carte jouée: {$card['value']}{$card['suit']}");
                } else {
                    $this->line("  ✓ A passé");
                }

                $played++;
                
                // Petite pause entre les coups
                sleep(1);

            } catch (\Exception $e) {
                $this->error("  ✗ Erreur pour le bot {$bot->pseudo}: {$e->getMessage()}");
            }
        }

        $this->info("Auto-play terminé: {$played} bots ont joué");
        return 0;
    }

    private function showHelp(): int
    {
        $this->info('Gestionnaire de bots La Map 241');
        $this->info('');
        $this->info('Actions disponibles:');
        $this->line('  create    - Créer des bots');
        $this->line('  play      - Faire jouer un bot');
        $this->line('  clean     - Nettoyer les bots inactifs');
        $this->line('  stats     - Afficher les statistiques');
        $this->line('  auto-play - Faire jouer automatiquement tous les bots');
        $this->info('');
        $this->info('Exemples:');
        $this->line('  php artisan bot:manage create --difficulty=hard --count=5');
        $this->line('  php artisan bot:manage play --game-id=123');
        $this->line('  php artisan bot:manage stats');
        $this->line('  php artisan bot:manage auto-play');

        return 0;
    }
}