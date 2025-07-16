<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\GameAIService;
use App\Services\AchievementService;
use App\Jobs\BotPlayJob;
use App\Events\GameTransitionEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GameTransitionService
{
    private GameAIService $aiService;
    private AchievementService $achievementService;

    public function __construct(GameAIService $aiService, AchievementService $achievementService)
    {
        $this->aiService = $aiService;
        $this->achievementService = $achievementService;
    }

    /**
     * Gérer la transition après la fin d'une manche
     */
    public function handleRoundEnd(Game $game): array
    {
        DB::beginTransaction();
        
        try {
            $gameRoom = $game->gameRoom;
            $winner = $game->roundWinner;
            
            Log::info("Fin de manche", [
                'game_id' => $game->id,
                'round_number' => $game->round_number,
                'winner_id' => $winner->id,
                'room_code' => $gameRoom->code
            ]);

            // Mettre à jour les scores
            $scores = $this->updateRoundScores($game, $winner);
            
            // Vérifier si quelqu'un a gagné la partie complète
            $gameWinner = $this->checkGameWinner($gameRoom, $scores);
            
            if ($gameWinner) {
                // Fin de partie
                $result = $this->endGame($gameRoom, $gameWinner, $scores);
            } else {
                // Préparer la manche suivante
                $result = $this->prepareNextRound($gameRoom, $scores);
            }
            
            DB::commit();
            
            // Diffuser l'événement de transition
            $this->broadcastTransition($gameRoom, $result);
            
            // Programmer les bots pour la prochaine manche si nécessaire
            if (!$gameWinner && isset($result['next_game'])) {
                $this->scheduleBotMoves($result['next_game']);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Erreur lors de la transition de manche", [
                'game_id' => $game->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Mettre à jour les scores après une manche
     */
    private function updateRoundScores(Game $game, User $winner): array
    {
        $gameState = $game->game_state;
        $scores = $gameState['player_scores'] ?? [];
        
        // Ajouter un point au gagnant
        $scores[$winner->id] = ($scores[$winner->id] ?? 0) + 1;
        
        // Sauvegarder les scores dans le cache pour la transition
        Cache::put("game_scores_{$game->gameRoom->code}", $scores, 300); // 5 minutes
        
        return $scores;
    }

    /**
     * Vérifier s'il y a un gagnant de la partie complète
     */
    private function checkGameWinner(GameRoom $gameRoom, array $scores): ?User
    {
        $roundsToWin = $gameRoom->rounds_to_win;
        
        foreach ($scores as $playerId => $score) {
            if ($score >= $roundsToWin) {
                return User::find($playerId);
            }
        }
        
        return null;
    }

    /**
     * Terminer la partie complète
     */
    private function endGame(GameRoom $gameRoom, User $winner, array $scores): array
    {
        // Mettre à jour la salle
        $gameRoom->update([
            'status' => GameRoom::STATUS_FINISHED,
            'winner_id' => $winner->id,
            'finished_at' => now()
        ]);

        // Distribuer les gains si ce n'est pas une exhibition
        if (!$gameRoom->is_exhibition) {
            $this->distributeWinnings($gameRoom, $winner);
        }

        // Mettre à jour les statistiques
        $this->updatePlayerStats($gameRoom, $winner, $scores);
        
        // Vérifier les achievements
        $this->checkAchievements($gameRoom, $winner);

        Log::info("Partie terminée", [
            'room_code' => $gameRoom->code,
            'winner_id' => $winner->id,
            'final_scores' => $scores
        ]);

        return [
            'type' => 'game_end',
            'winner' => $winner,
            'final_scores' => $scores,
            'room_status' => $gameRoom->status,
            'winnings' => !$gameRoom->is_exhibition ? $gameRoom->pot_amount : 0
        ];
    }

    /**
     * Préparer la manche suivante
     */
    private function prepareNextRound(GameRoom $gameRoom, array $scores): array
    {
        // Trouver le dernier jeu
        $lastGame = $gameRoom->games()->latest()->first();
        $nextRoundNumber = $lastGame ? $lastGame->round_number + 1 : 1;

        // Créer le nouveau jeu
        $newGame = Game::create([
            'game_room_id' => $gameRoom->id,
            'round_number' => $nextRoundNumber,
            'status' => Game::STATUS_IN_PROGRESS,
            'started_at' => now()
        ]);

        // Distribuer les cartes
        $newGame->dealCards();

        Log::info("Nouvelle manche préparée", [
            'room_code' => $gameRoom->code,
            'round_number' => $nextRoundNumber,
            'game_id' => $newGame->id,
            'current_scores' => $scores
        ]);

        return [
            'type' => 'next_round',
            'next_game' => $newGame,
            'round_number' => $nextRoundNumber,
            'current_scores' => $scores,
            'players' => $this->getPlayersData($gameRoom, $scores)
        ];
    }

    /**
     * Distribuer les gains
     */
    private function distributeWinnings(GameRoom $gameRoom, User $winner): void
    {
        $winAmount = $gameRoom->pot_amount;
        
        if ($winAmount > 0) {
            $winner->increment('balance', $winAmount);
            
            // Créer une transaction de gain
            $winner->transactions()->create([
                'type' => 'game_win',
                'amount' => $winAmount,
                'status' => 'completed',
                'reference' => 'WIN_' . $gameRoom->code . '_' . time(),
                'metadata' => [
                    'game_room_code' => $gameRoom->code,
                    'round_number' => $gameRoom->games()->count(),
                    'players_count' => $gameRoom->players()->count()
                ]
            ]);

            Log::info("Gains distribués", [
                'winner_id' => $winner->id,
                'amount' => $winAmount,
                'room_code' => $gameRoom->code
            ]);
        }
    }

    /**
     * Mettre à jour les statistiques des joueurs
     */
    private function updatePlayerStats(GameRoom $gameRoom, User $winner, array $scores): void
    {
        $players = $gameRoom->players;
        
        foreach ($players as $player) {
            $stats = $player->stats ?? $player->stats()->create([]);
            $isWinner = $player->id === $winner->id;
            $playerScore = $scores[$player->id] ?? 0;
            
            // Statistiques de base
            $stats->increment('games_played');
            $stats->increment('rounds_played', $playerScore);
            
            if ($isWinner) {
                $stats->increment('games_won');
                $stats->increment('current_streak');
                $stats->best_streak = max($stats->best_streak, $stats->current_streak);
                
                if (!$gameRoom->is_exhibition) {
                    $stats->increment('total_won', $gameRoom->pot_amount);
                    $stats->biggest_win = max($stats->biggest_win, $gameRoom->pot_amount);
                }
            } else {
                $stats->update(['current_streak' => 0]);
                $stats->increment('games_lost');
                
                if (!$gameRoom->is_exhibition) {
                    $stats->increment('total_lost', $gameRoom->bet_amount);
                }
            }
            
            if (!$gameRoom->is_exhibition) {
                $stats->increment('total_bet', $gameRoom->bet_amount);
            }
        }
    }

    /**
     * Obtenir les données des joueurs
     */
    private function getPlayersData(GameRoom $gameRoom, array $scores): array
    {
        $players = [];
        
        foreach ($gameRoom->players as $player) {
            $players[] = [
                'id' => $player->id,
                'pseudo' => $player->pseudo,
                'is_bot' => $player->is_bot ?? false,
                'score' => $scores[$player->id] ?? 0,
                'needed_to_win' => $gameRoom->rounds_to_win - ($scores[$player->id] ?? 0)
            ];
        }
        
        return $players;
    }

    /**
     * Programmer les mouvements des bots
     */
    private function scheduleBotMoves(Game $game): void
    {
        $currentPlayer = $game->currentPlayer;
        
        if ($currentPlayer && $this->aiService->isBot($currentPlayer)) {
            // Délai basé sur la difficulté
            $delay = match($currentPlayer->bot_difficulty) {
                'easy' => rand(2, 4),
                'medium' => rand(3, 6),
                'hard' => rand(4, 8),
                default => 3
            };
            
            BotPlayJob::dispatch($game, $currentPlayer, $delay);
            
            Log::info("Bot programmé pour jouer", [
                'game_id' => $game->id,
                'bot_id' => $currentPlayer->id,
                'delay' => $delay
            ]);
        }
    }

    /**
     * Obtenir l'état de transition d'une salle
     */
    public function getTransitionState(GameRoom $gameRoom): array
    {
        $scores = Cache::get("game_scores_{$gameRoom->code}", []);
        $currentGame = $gameRoom->games()->latest()->first();
        
        return [
            'room_code' => $gameRoom->code,
            'status' => $gameRoom->status,
            'current_round' => $currentGame ? $currentGame->round_number : 0,
            'rounds_to_win' => $gameRoom->rounds_to_win,
            'current_scores' => $scores,
            'players' => $this->getPlayersData($gameRoom, $scores),
            'is_exhibition' => $gameRoom->is_exhibition,
            'pot_amount' => $gameRoom->pot_amount,
            'time_remaining' => $this->calculateTimeRemaining($gameRoom)
        ];
    }

    /**
     * Calculer le temps restant
     */
    private function calculateTimeRemaining(GameRoom $gameRoom): ?int
    {
        if (!$gameRoom->time_limit || $gameRoom->status !== GameRoom::STATUS_PLAYING) {
            return null;
        }
        
        $elapsed = now()->diffInSeconds($gameRoom->started_at);
        $remaining = ($gameRoom->time_limit * 60) - $elapsed;
        
        return max(0, $remaining);
    }

    /**
     * Forcer la fin d'une partie (timeout)
     */
    public function forceEndGame(GameRoom $gameRoom, string $reason = 'timeout'): array
    {
        Log::info("Fin forcée de partie", [
            'room_code' => $gameRoom->code,
            'reason' => $reason
        ]);

        $scores = Cache::get("game_scores_{$gameRoom->code}", []);
        
        // Trouver le joueur avec le meilleur score
        $winnerId = null;
        $maxScore = -1;
        
        foreach ($scores as $playerId => $score) {
            if ($score > $maxScore) {
                $maxScore = $score;
                $winnerId = $playerId;
            }
        }
        
        if ($winnerId) {
            $winner = User::find($winnerId);
            return $this->endGame($gameRoom, $winner, $scores);
        }
        
        // Aucun gagnant, partie annulée
        $gameRoom->update([
            'status' => GameRoom::STATUS_CANCELLED,
            'finished_at' => now()
        ]);
        
        // Rembourser les joueurs si ce n'est pas une exhibition
        if (!$gameRoom->is_exhibition) {
            $this->refundPlayers($gameRoom);
        }
        
        return [
            'type' => 'game_cancelled',
            'reason' => $reason,
            'refunded' => !$gameRoom->is_exhibition
        ];
    }

    /**
     * Rembourser les joueurs
     */
    private function refundPlayers(GameRoom $gameRoom): void
    {
        foreach ($gameRoom->players as $player) {
            $player->increment('balance', $gameRoom->bet_amount);
            
            $player->transactions()->create([
                'type' => 'refund',
                'amount' => $gameRoom->bet_amount,
                'status' => 'completed',
                'reference' => 'REFUND_' . $gameRoom->code . '_' . time(),
                'metadata' => [
                    'game_room_code' => $gameRoom->code,
                    'reason' => 'game_cancelled'
                ]
            ]);
        }
        
        Log::info("Joueurs remboursés", [
            'room_code' => $gameRoom->code,
            'amount_per_player' => $gameRoom->bet_amount
        ]);
    }

    /**
     * Nettoyer les données de transition
     */
    public function cleanupTransition(GameRoom $gameRoom): void
    {
        Cache::forget("game_scores_{$gameRoom->code}");
        
        Log::info("Nettoyage transition", [
            'room_code' => $gameRoom->code
        ]);
    }

    /**
     * Diffuser l'événement de transition
     */
    private function broadcastTransition(GameRoom $gameRoom, array $result): void
    {
        try {
            $transitionType = $result['type'] ?? 'unknown';
            
            // Préparer les données pour la diffusion
            $broadcastData = [
                'room_code' => $gameRoom->code,
                'transition_type' => $transitionType,
                'data' => $result
            ];
            
            // Diffuser l'événement
            event(new GameTransitionEvent($gameRoom->code, $transitionType, $broadcastData));
            
            Log::info("Événement de transition diffusé", [
                'room_code' => $gameRoom->code,
                'type' => $transitionType
            ]);
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la diffusion de transition", [
                'room_code' => $gameRoom->code,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Vérifier les achievements après une partie
     */
    private function checkAchievements(GameRoom $gameRoom, User $winner): void
    {
        $lastGame = $gameRoom->games()->latest()->first();
        if (!$lastGame) return;

        foreach ($gameRoom->players as $player) {
            try {
                $unlockedAchievements = $this->achievementService->checkAchievements($player, $lastGame);
                
                if (!empty($unlockedAchievements)) {
                    Log::info("Achievements débloqués", [
                        'player_id' => $player->id,
                        'achievements' => array_column($unlockedAchievements, 'key'),
                        'game_id' => $lastGame->id
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Erreur lors de la vérification des achievements", [
                    'player_id' => $player->id,
                    'game_id' => $lastGame->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}