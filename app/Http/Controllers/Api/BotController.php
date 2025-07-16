<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GameAIService;
use App\Models\GameRoom;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BotController extends Controller
{
    private GameAIService $aiService;

    public function __construct(GameAIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Ajouter un bot à une salle
     */
    public function addBotToRoom(Request $request, string $roomCode)
    {
        $request->validate([
            'difficulty' => 'required|in:easy,medium,hard'
        ]);

        try {
            $room = GameRoom::where('code', $roomCode)->firstOrFail();
            
            // Vérifier que la salle peut accueillir un bot
            if ($room->status !== 'waiting') {
                return response()->json([
                    'message' => 'Impossible d\'ajouter un bot à cette salle',
                    'error_code' => 'ROOM_NOT_WAITING'
                ], 400);
            }

            $playersCount = $room->players()->count();
            if ($playersCount >= $room->max_players) {
                return response()->json([
                    'message' => 'La salle est déjà complète',
                    'error_code' => 'ROOM_FULL'
                ], 400);
            }

            // Créer le bot
            $bot = $this->aiService->createBot($request->difficulty);
            
            // Ajouter le bot à la salle
            $room->players()->attach($bot->id, [
                'position' => $playersCount + 1,
                'is_ready' => true, // Les bots sont toujours prêts
                'joined_at' => now()
            ]);

            // Mettre à jour l'activité du bot
            $bot->update(['last_bot_activity' => now()]);

            Log::info("Bot ajouté à la salle {$roomCode}", [
                'bot_id' => $bot->id,
                'difficulty' => $request->difficulty,
                'room_code' => $roomCode
            ]);

            return response()->json([
                'message' => 'Bot ajouté avec succès',
                'bot' => [
                    'id' => $bot->id,
                    'pseudo' => $bot->pseudo,
                    'difficulty' => $bot->bot_difficulty,
                    'is_bot' => true
                ],
                'room' => [
                    'code' => $room->code,
                    'players_count' => $room->players()->count(),
                    'max_players' => $room->max_players
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'ajout du bot', [
                'room_code' => $roomCode,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erreur lors de l\'ajout du bot',
                'error_code' => 'BOT_ADD_ERROR'
            ], 500);
        }
    }

    /**
     * Faire jouer un bot
     */
    public function playBotMove(Request $request, int $gameId)
    {
        try {
            $game = Game::findOrFail($gameId);
            $currentPlayer = User::findOrFail($game->current_player_id);
            
            // Vérifier que le joueur actuel est un bot
            if (!$this->aiService->isBot($currentPlayer)) {
                return response()->json([
                    'message' => 'Le joueur actuel n\'est pas un bot',
                    'error_code' => 'NOT_A_BOT'
                ], 400);
            }

            // Vérifier que le jeu est en cours
            if ($game->status !== Game::STATUS_IN_PROGRESS) {
                return response()->json([
                    'message' => 'Le jeu n\'est pas en cours',
                    'error_code' => 'GAME_NOT_IN_PROGRESS'
                ], 400);
            }

            // Faire jouer le bot
            $result = $this->aiService->playMove($game, $currentPlayer, $currentPlayer->bot_difficulty);
            
            // Mettre à jour l'activité du bot
            $currentPlayer->update(['last_bot_activity' => now()]);

            return response()->json([
                'message' => 'Bot a joué avec succès',
                'move' => $result,
                'game_state' => $game->getGameStateForPlayer($currentPlayer->id)
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors du jeu du bot', [
                'game_id' => $gameId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erreur lors du jeu du bot',
                'error_code' => 'BOT_PLAY_ERROR',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques d'un bot
     */
    public function getBotStats(int $botId)
    {
        try {
            $bot = User::findOrFail($botId);
            
            if (!$this->aiService->isBot($bot)) {
                return response()->json([
                    'message' => 'L\'utilisateur n\'est pas un bot',
                    'error_code' => 'NOT_A_BOT'
                ], 400);
            }

            $stats = $this->aiService->getBotStats($bot);

            return response()->json([
                'bot' => [
                    'id' => $bot->id,
                    'pseudo' => $bot->pseudo,
                    'difficulty' => $bot->bot_difficulty,
                    'created_at' => $bot->created_at,
                    'last_activity' => $bot->last_bot_activity
                ],
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Bot non trouvé',
                'error_code' => 'BOT_NOT_FOUND'
            ], 404);
        }
    }

    /**
     * Lister les bots disponibles
     */
    public function listBots()
    {
        $bots = User::where('is_bot', true)
            ->with('stats')
            ->orderBy('created_at', 'desc')
            ->get();

        $botList = $bots->map(function ($bot) {
            return [
                'id' => $bot->id,
                'pseudo' => $bot->pseudo,
                'difficulty' => $bot->bot_difficulty,
                'stats' => $this->aiService->getBotStats($bot),
                'is_active' => $bot->last_bot_activity && $bot->last_bot_activity->isAfter(now()->subHours(24)),
                'created_at' => $bot->created_at
            ];
        });

        return response()->json([
            'bots' => $botList,
            'total' => $bots->count()
        ]);
    }

    /**
     * Supprimer un bot
     */
    public function deleteBot(int $botId)
    {
        try {
            $bot = User::findOrFail($botId);
            
            if (!$this->aiService->isBot($bot)) {
                return response()->json([
                    'message' => 'L\'utilisateur n\'est pas un bot',
                    'error_code' => 'NOT_A_BOT'
                ], 400);
            }

            // Vérifier que le bot n'est pas dans une partie active
            $activeGames = Game::where('current_player_id', $bot->id)
                ->where('status', Game::STATUS_IN_PROGRESS)
                ->count();

            if ($activeGames > 0) {
                return response()->json([
                    'message' => 'Impossible de supprimer un bot en cours de partie',
                    'error_code' => 'BOT_IN_ACTIVE_GAME'
                ], 400);
            }

            $bot->delete();

            Log::info("Bot supprimé", [
                'bot_id' => $botId,
                'pseudo' => $bot->pseudo
            ]);

            return response()->json([
                'message' => 'Bot supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression du bot',
                'error_code' => 'BOT_DELETE_ERROR'
            ], 500);
        }
    }

    /**
     * Créer un bot personnalisé
     */
    public function createBot(Request $request)
    {
        $request->validate([
            'difficulty' => 'required|in:easy,medium,hard',
            'pseudo' => 'sometimes|string|max:50|unique:users,pseudo',
            'settings' => 'sometimes|array'
        ]);

        try {
            $bot = $this->aiService->createBot($request->difficulty);
            
            // Personnaliser le pseudo si fourni
            if ($request->has('pseudo')) {
                $bot->update(['pseudo' => $request->pseudo]);
            }

            // Ajouter des paramètres personnalisés
            if ($request->has('settings')) {
                $bot->update(['bot_settings' => $request->settings]);
            }

            return response()->json([
                'message' => 'Bot créé avec succès',
                'bot' => [
                    'id' => $bot->id,
                    'pseudo' => $bot->pseudo,
                    'difficulty' => $bot->bot_difficulty,
                    'settings' => $bot->bot_settings,
                    'created_at' => $bot->created_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création du bot',
                'error_code' => 'BOT_CREATE_ERROR',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour les paramètres d'un bot
     */
    public function updateBot(Request $request, int $botId)
    {
        $request->validate([
            'difficulty' => 'sometimes|in:easy,medium,hard',
            'settings' => 'sometimes|array'
        ]);

        try {
            $bot = User::findOrFail($botId);
            
            if (!$this->aiService->isBot($bot)) {
                return response()->json([
                    'message' => 'L\'utilisateur n\'est pas un bot',
                    'error_code' => 'NOT_A_BOT'
                ], 400);
            }

            $updates = [];
            
            if ($request->has('difficulty')) {
                $updates['bot_difficulty'] = $request->difficulty;
            }
            
            if ($request->has('settings')) {
                $updates['bot_settings'] = $request->settings;
            }

            $bot->update($updates);

            return response()->json([
                'message' => 'Bot mis à jour avec succès',
                'bot' => [
                    'id' => $bot->id,
                    'pseudo' => $bot->pseudo,
                    'difficulty' => $bot->bot_difficulty,
                    'settings' => $bot->bot_settings,
                    'updated_at' => $bot->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du bot',
                'error_code' => 'BOT_UPDATE_ERROR'
            ], 500);
        }
    }
}