<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GameTransitionService;
use App\Models\GameRoom;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GameTransitionController extends Controller
{
    private GameTransitionService $transitionService;

    public function __construct(GameTransitionService $transitionService)
    {
        $this->transitionService = $transitionService;
    }

    /**
     * Obtenir l'état de transition d'une salle
     */
    public function getTransitionState(string $roomCode)
    {
        try {
            $room = GameRoom::where('code', $roomCode)->firstOrFail();
            
            $state = $this->transitionService->getTransitionState($room);
            
            return response()->json([
                'message' => 'État de transition récupéré',
                'transition_state' => $state
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de l\'état de transition', [
                'room_code' => $roomCode,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération de l\'état',
                'error_code' => 'TRANSITION_STATE_ERROR'
            ], 500);
        }
    }

    /**
     * Forcer la transition vers la manche suivante
     */
    public function forceNextRound(Request $request, string $roomCode)
    {
        try {
            $room = GameRoom::where('code', $roomCode)->firstOrFail();
            
            // Vérifier que l'utilisateur est dans la salle
            if (!$room->players()->where('user_id', auth()->id())->exists()) {
                return response()->json([
                    'message' => 'Vous n\'êtes pas dans cette salle',
                    'error_code' => 'NOT_IN_ROOM'
                ], 403);
            }
            
            // Vérifier que la partie est en cours
            if ($room->status !== GameRoom::STATUS_PLAYING) {
                return response()->json([
                    'message' => 'La partie n\'est pas en cours',
                    'error_code' => 'GAME_NOT_PLAYING'
                ], 400);
            }
            
            // Obtenir le jeu actuel
            $currentGame = $room->games()->latest()->first();
            
            if (!$currentGame || $currentGame->status !== Game::STATUS_COMPLETED) {
                return response()->json([
                    'message' => 'La manche actuelle n\'est pas terminée',
                    'error_code' => 'ROUND_NOT_COMPLETED'
                ], 400);
            }
            
            // Déclencher la transition
            $result = $this->transitionService->handleRoundEnd($currentGame);
            
            return response()->json([
                'message' => 'Transition effectuée avec succès',
                'transition_result' => $result
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la transition forcée', [
                'room_code' => $roomCode,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la transition',
                'error_code' => 'TRANSITION_ERROR'
            ], 500);
        }
    }

    /**
     * Terminer une partie par timeout
     */
    public function timeoutGame(Request $request, string $roomCode)
    {
        try {
            $room = GameRoom::where('code', $roomCode)->firstOrFail();
            
            // Vérifier que l'utilisateur est dans la salle ou est un admin
            if (!$room->players()->where('user_id', auth()->id())->exists() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'message' => 'Non autorisé',
                    'error_code' => 'UNAUTHORIZED'
                ], 403);
            }
            
            // Vérifier que la partie est en cours
            if ($room->status !== GameRoom::STATUS_PLAYING) {
                return response()->json([
                    'message' => 'La partie n\'est pas en cours',
                    'error_code' => 'GAME_NOT_PLAYING'
                ], 400);
            }
            
            // Forcer la fin de partie
            $result = $this->transitionService->forceEndGame($room, 'timeout');
            
            return response()->json([
                'message' => 'Partie terminée par timeout',
                'end_result' => $result
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors du timeout de partie', [
                'room_code' => $roomCode,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors du timeout',
                'error_code' => 'TIMEOUT_ERROR'
            ], 500);
        }
    }

    /**
     * Nettoyer les données de transition
     */
    public function cleanupTransition(string $roomCode)
    {
        try {
            $room = GameRoom::where('code', $roomCode)->firstOrFail();
            
            // Seul le créateur ou un admin peut nettoyer
            if ($room->creator_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'message' => 'Non autorisé',
                    'error_code' => 'UNAUTHORIZED'
                ], 403);
            }
            
            $this->transitionService->cleanupTransition($room);
            
            return response()->json([
                'message' => 'Nettoyage effectué avec succès'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors du nettoyage de transition', [
                'room_code' => $roomCode,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors du nettoyage',
                'error_code' => 'CLEANUP_ERROR'
            ], 500);
        }
    }

    /**
     * Obtenir l'historique des transitions d'une salle
     */
    public function getTransitionHistory(string $roomCode)
    {
        try {
            $room = GameRoom::where('code', $roomCode)->firstOrFail();
            
            // Vérifier que l'utilisateur est dans la salle
            if (!$room->players()->where('user_id', auth()->id())->exists()) {
                return response()->json([
                    'message' => 'Vous n\'êtes pas dans cette salle',
                    'error_code' => 'NOT_IN_ROOM'
                ], 403);
            }
            
            // Obtenir l'historique des jeux
            $games = $room->games()
                ->with(['roundWinner', 'moves'])
                ->orderBy('round_number')
                ->get();
            
            $history = [];
            
            foreach ($games as $game) {
                $history[] = [
                    'round_number' => $game->round_number,
                    'status' => $game->status,
                    'winner' => $game->roundWinner ? [
                        'id' => $game->roundWinner->id,
                        'pseudo' => $game->roundWinner->pseudo,
                        'is_bot' => $game->roundWinner->is_bot ?? false
                    ] : null,
                    'duration' => $game->duration,
                    'moves_count' => $game->moves->count(),
                    'started_at' => $game->started_at,
                    'ended_at' => $game->ended_at
                ];
            }
            
            return response()->json([
                'message' => 'Historique des transitions récupéré',
                'room_code' => $roomCode,
                'total_rounds' => count($history),
                'history' => $history
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de l\'historique', [
                'room_code' => $roomCode,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération de l\'historique',
                'error_code' => 'HISTORY_ERROR'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques de performance des transitions
     */
    public function getTransitionStats(string $roomCode)
    {
        try {
            $room = GameRoom::where('code', $roomCode)->firstOrFail();
            
            // Vérifier que l'utilisateur est dans la salle
            if (!$room->players()->where('user_id', auth()->id())->exists()) {
                return response()->json([
                    'message' => 'Vous n\'êtes pas dans cette salle',
                    'error_code' => 'NOT_IN_ROOM'
                ], 403);
            }
            
            $games = $room->games;
            $totalRounds = $games->count();
            $completedRounds = $games->where('status', Game::STATUS_COMPLETED)->count();
            
            if ($totalRounds === 0) {
                return response()->json([
                    'message' => 'Aucune statistique disponible',
                    'stats' => []
                ]);
            }
            
            // Calculer les statistiques
            $avgDuration = $games->where('ended_at', '!=', null)->avg('duration') ?? 0;
            $shortestRound = $games->where('ended_at', '!=', null)->min('duration') ?? 0;
            $longestRound = $games->where('ended_at', '!=', null)->max('duration') ?? 0;
            
            $stats = [
                'total_rounds' => $totalRounds,
                'completed_rounds' => $completedRounds,
                'completion_rate' => $totalRounds > 0 ? ($completedRounds / $totalRounds) * 100 : 0,
                'avg_round_duration' => round($avgDuration, 2),
                'shortest_round' => $shortestRound,
                'longest_round' => $longestRound,
                'total_moves' => $games->sum(function($game) {
                    return $game->moves->count();
                }),
                'avg_moves_per_round' => $totalRounds > 0 ? round($games->sum(function($game) {
                    return $game->moves->count();
                }) / $totalRounds, 1) : 0
            ];
            
            return response()->json([
                'message' => 'Statistiques de transition récupérées',
                'room_code' => $roomCode,
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des stats de transition', [
                'room_code' => $roomCode,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error_code' => 'STATS_ERROR'
            ], 500);
        }
    }
}