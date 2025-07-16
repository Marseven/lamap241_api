<?php

namespace App\Jobs;

use App\Models\Game;
use App\Models\User;
use App\Services\GameAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BotPlayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Game $game;
    protected User $bot;
    protected int $delaySeconds;

    /**
     * Create a new job instance.
     */
    public function __construct(Game $game, User $bot, int $delaySeconds = 0)
    {
        $this->game = $game;
        $this->bot = $bot;
        $this->delaySeconds = $delaySeconds;
        
        // Délai d'exécution selon la difficulté
        if ($delaySeconds > 0) {
            $this->delay(now()->addSeconds($delaySeconds));
        }
    }

    /**
     * Execute the job.
     */
    public function handle(GameAIService $aiService): void
    {
        try {
            // Vérifier que le jeu est toujours valide
            $this->game->refresh();
            
            if ($this->game->status !== Game::STATUS_IN_PROGRESS) {
                Log::info("Jeu terminé, bot ne joue pas", [
                    'game_id' => $this->game->id,
                    'bot_id' => $this->bot->id
                ]);
                return;
            }

            // Vérifier que c'est toujours le tour du bot
            if ($this->game->current_player_id !== $this->bot->id) {
                Log::info("Ce n'est plus le tour du bot", [
                    'game_id' => $this->game->id,
                    'bot_id' => $this->bot->id,
                    'current_player_id' => $this->game->current_player_id
                ]);
                return;
            }

            // Faire jouer le bot
            $result = $aiService->playMove($this->game, $this->bot, $this->bot->bot_difficulty);

            Log::info("Bot a joué via job", [
                'game_id' => $this->game->id,
                'bot_id' => $this->bot->id,
                'action' => $result['action'],
                'reasoning' => $result['reasoning']
            ]);

            // Diffuser l'événement (optionnel - selon l'implémentation WebSocket)
            $this->broadcastBotMove($result);

        } catch (\Exception $e) {
            Log::error("Erreur lors du jeu du bot via job", [
                'game_id' => $this->game->id,
                'bot_id' => $this->bot->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Réessayer une seule fois en cas d'erreur
            $this->fail($e);
        }
    }

    /**
     * Diffuser le mouvement du bot
     */
    private function broadcastBotMove(array $result): void
    {
        // Ici, vous pourriez ajouter la logique pour diffuser via WebSocket
        // Par exemple avec Laravel Broadcasting ou Reverb
        
        /* Exemple:
        broadcast(new GameUpdated([
            'game_id' => $this->game->id,
            'action' => $result['action'],
            'player_id' => $this->bot->id,
            'is_bot' => true,
            'game_state' => $this->game->getGameStateForPlayer($this->bot->id)
        ]))->toOthers();
        */
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("BotPlayJob failed", [
            'game_id' => $this->game->id,
            'bot_id' => $this->bot->id,
            'error' => $exception->getMessage()
        ]);
    }
}