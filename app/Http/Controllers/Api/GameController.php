<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    /**
     * Get game details.
     */
    public function show(Request $request, $gameId)
    {
        $gameRoom = GameRoom::where('code', $gameId)->firstOrFail();
        $game = Game::with(['gameRoom', 'currentPlayer', 'roundWinner'])
            ->where('game_room_id', $gameRoom->id)->where('status', 'in_progress')->first();
            
        if (!$game) {
            return response()->json([
                'message' => 'Aucun jeu en cours dans cette salle',
                'room_status' => $gameRoom->status,
                'room_code' => $gameRoom->code,
                'suggestion' => $gameRoom->status === 'waiting' ? 'Attendez que tous les joueurs soient prêts' : 'La partie n\'a pas encore commencé'
            ], 404);
        }

        // Vérifier que l'utilisateur est dans la partie
        if (!$this->userInGame($request->user(), $game)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas dans cette partie'
            ], 403);
        }

        return response()->json([
            'game' => [
                'id' => $game->id,
                'room_code' => $game->gameRoom->code,
                'round_number' => $game->round_number,
                'status' => $game->status,
                'current_player' => $game->currentPlayer ? [
                    'id' => $game->currentPlayer->id,
                    'pseudo' => $game->currentPlayer->pseudo,
                ] : null,
                'round_winner' => $game->roundWinner ? [
                    'id' => $game->roundWinner->id,
                    'pseudo' => $game->roundWinner->pseudo,
                ] : null,
                'started_at' => $game->started_at,
                'ended_at' => $game->ended_at,
            ]
        ]);
    }

    /**
     * Get current game state for player.
     */
    public function state(Request $request, $gameId)
    {
        $gameRoom = GameRoom::where('code', $gameId)->firstOrFail();
        $game = Game::where('game_room_id', $gameRoom->id)->where('status', 'in_progress')->first();
        
        if (!$game) {
            return response()->json([
                'message' => 'Aucun jeu en cours dans cette salle',
                'room_status' => $gameRoom->status,
                'room_code' => $gameRoom->code,
                'suggestion' => $gameRoom->status === 'waiting' ? 'Attendez que tous les joueurs soient prêts' : 'La partie n\'a pas encore commencé'
            ], 404);
        }
        $user = $request->user();

        // Vérifier que l'utilisateur est dans la partie
        if (!$this->userInGame($user, $game)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas dans cette partie'
            ], 403);
        }

        $state = $game->getGameStateForPlayer($user->id);

        // Ajouter les infos de la salle
        $room = $game->gameRoom;
        $state['room'] = [
            'code' => $room->code,
            'name' => $room->name,
            'bet_amount' => $room->bet_amount,
            'pot_amount' => $room->pot_amount,
            'rounds_to_win' => $room->rounds_to_win,
        ];

        // Ajouter les scores globaux
        $scores = [];
        foreach ($room->players as $player) {
            $gamesWon = $room->games()
                ->where('round_winner_id', $player->id)
                ->count();

            $scores[$player->id] = [
                'player' => [
                    'id' => $player->id,
                    'pseudo' => $player->pseudo,
                    'avatar' => $player->avatar,
                ],
                'rounds_won' => $gamesWon,
                'is_online' => $player->isOnline(),
            ];
        }
        $state['scores'] = $scores;

        return response()->json([
            'state' => $state
        ]);
    }

    /**
     * Play a card.
     */
    public function playCard(Request $request, $gameId)
    {
        $validated = $request->validate([
            'card' => 'required|array',
            'card.value' => 'required|integer|min:3|max:10',
            'card.suit' => 'required|string|in:♠,♥,♣,♦',
        ]);

        $gameRoom = GameRoom::where('code', $gameId)->firstOrFail();
        $game = Game::where('game_room_id', $gameRoom->id)->where('status', 'in_progress')->first();
        
        if (!$game) {
            return response()->json([
                'message' => 'Aucun jeu en cours dans cette salle',
                'room_status' => $gameRoom->status,
                'room_code' => $gameRoom->code,
                'suggestion' => $gameRoom->status === 'waiting' ? 'Attendez que tous les joueurs soient prêts' : 'La partie n\'a pas encore commencé'
            ], 404);
        }
        $user = $request->user();

        // Vérifications
        if (!$this->userInGame($user, $game)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas dans cette partie'
            ], 403);
        }

        if ($game->status !== Game::STATUS_IN_PROGRESS) {
            return response()->json([
                'message' => 'La partie n\'est pas en cours'
            ], 400);
        }

        if ($game->current_player_id !== $user->id) {
            return response()->json([
                'message' => 'Ce n\'est pas votre tour'
            ], 400);
        }

        // Jouer la carte
        $success = DB::transaction(function () use ($game, $user, $validated) {
            return $game->playCard($user->id, $validated['card']);
        });

        if (!$success) {
            return response()->json([
                'message' => 'Coup invalide. Vérifiez que vous avez cette carte et qu\'elle respecte les règles.'
            ], 400);
        }

        // Vérifier si la manche est terminée
        if ($game->fresh()->status === Game::STATUS_COMPLETED) {
            $this->handleRoundEnd($game);
        }

        // Notifier les autres joueurs
        // broadcast(new CardPlayed($game, $user, $validated['card']))->toOthers();

        return response()->json([
            'message' => 'Carte jouée avec succès',
            'state' => $game->fresh()->getGameStateForPlayer($user->id)
        ]);
    }

    /**
     * Pass turn.
     */
    public function pass(Request $request, $gameId)
    {
        $gameRoom = GameRoom::where('code', $gameId)->firstOrFail();
        $game = Game::where('game_room_id', $gameRoom->id)->where('status', 'in_progress')->first();
        
        if (!$game) {
            return response()->json([
                'message' => 'Aucun jeu en cours dans cette salle',
                'room_status' => $gameRoom->status,
                'room_code' => $gameRoom->code,
                'suggestion' => $gameRoom->status === 'waiting' ? 'Attendez que tous les joueurs soient prêts' : 'La partie n\'a pas encore commencé'
            ], 404);
        }
        $user = $request->user();

        // Vérifications similaires à playCard
        if (!$this->userInGame($user, $game)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas dans cette partie'
            ], 403);
        }

        if ($game->status !== Game::STATUS_IN_PROGRESS) {
            return response()->json([
                'message' => 'La partie n\'est pas en cours'
            ], 400);
        }

        if ($game->current_player_id !== $user->id) {
            return response()->json([
                'message' => 'Ce n\'est pas votre tour'
            ], 400);
        }

        // Passer le tour
        $success = $game->pass($user->id);

        if (!$success) {
            return response()->json([
                'message' => 'Impossible de passer'
            ], 400);
        }

        // Notifier les autres joueurs
        // broadcast(new PlayerPassed($game, $user))->toOthers();

        return response()->json([
            'message' => 'Tour passé',
            'state' => $game->fresh()->getGameStateForPlayer($user->id)
        ]);
    }

    /**
     * Forfeit game.
     */
    public function forfeit(Request $request, $gameId)
    {
        $gameRoom = GameRoom::where('code', $gameId)->firstOrFail();
        $game = Game::where('game_room_id', $gameRoom->id)->where('status', 'in_progress')->first();
        
        if (!$game) {
            return response()->json([
                'message' => 'Aucun jeu en cours dans cette salle',
                'room_status' => $gameRoom->status,
                'room_code' => $gameRoom->code,
                'suggestion' => $gameRoom->status === 'waiting' ? 'Attendez que tous les joueurs soient prêts' : 'La partie n\'a pas encore commencé'
            ], 404);
        }
        $user = $request->user();
        $room = $game->gameRoom;

        if (!$this->userInGame($user, $game)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas dans cette partie'
            ], 403);
        }

        if ($game->status !== Game::STATUS_IN_PROGRESS) {
            return response()->json([
                'message' => 'La partie n\'est pas en cours'
            ], 400);
        }

        DB::transaction(function () use ($game, $user, $room) {
            // Marquer le jeu comme abandonné
            $game->status = Game::STATUS_ABANDONED;
            $game->ended_at = now();
            $game->save();

            // Trouver l'autre joueur
            $winner = $room->players->where('id', '!=', $user->id)->first();

            if ($winner) {
                // Terminer la partie avec l'autre joueur comme gagnant
                $room->endGame($winner);
            }

            // Marquer le joueur comme ayant quitté
            $room->players()->updateExistingPivot($user->id, [
                'status' => 'left',
                'left_at' => now()
            ]);

            // Mettre à jour les stats
            $stats = $user->getOrCreateStats();
            $stats->games_abandoned++;
            $stats->current_streak = 0;
            $stats->save();
        });

        return response()->json([
            'message' => 'Vous avez abandonné la partie'
        ]);
    }

    /**
     * Get game moves history.
     */
    public function moves(Request $request, $gameId)
    {
        $gameRoom = GameRoom::where('code', $gameId)->firstOrFail();
        $game = Game::where('game_room_id', $gameRoom->id)->first();
        
        if (!$game) {
            return response()->json([
                'message' => 'Aucun jeu dans cette salle',
                'room_status' => $gameRoom->status,
                'room_code' => $gameRoom->code,
                'moves' => []
            ], 404);
        }

        // Vérifier l'accès
        if (
            !$this->userInGame($request->user(), $game) &&
            !($game->gameRoom->allow_spectators && $game->status === Game::STATUS_COMPLETED)
        ) {
            return response()->json([
                'message' => 'Accès refusé'
            ], 403);
        }

        $moves = $game->moves()
            ->with('player:id,pseudo')
            ->orderBy('move_number')
            ->get()
            ->map(function ($move) {
                return [
                    'move_number' => $move->move_number,
                    'player' => [
                        'id' => $move->player->id,
                        'pseudo' => $move->player->pseudo,
                    ],
                    'type' => $move->move_type,
                    'card' => $move->card_played,
                    'played_at' => $move->played_at,
                    'duration' => $move->duration,
                ];
            });

        return response()->json([
            'moves' => $moves
        ]);
    }

    /**
     * Handle round end.
     */
    private function handleRoundEnd(Game $game)
    {
        $room = $game->gameRoom;
        $winner = $game->roundWinner;

        // Compter les manches gagnées
        $winnerRounds = $room->games()
            ->where('round_winner_id', $winner->id)
            ->count();

        // Vérifier si la partie est terminée
        if ($winnerRounds >= $room->rounds_to_win) {
            // Fin de la partie complète
            $room->endGame($winner);

            // Notifier les joueurs
            // broadcast(new GameEnded($room, $winner))->toOthers();
        } else {
            // Créer une nouvelle manche
            $newGame = $room->games()->create([
                'round_number' => $game->round_number + 1,
                'status' => 'in_progress',
                'started_at' => now()
            ]);

            $newGame->dealCards();

            // Notifier les joueurs
            // broadcast(new NewRoundStarted($room, $newGame))->toOthers();
        }
    }

    /**
     * Check if user is in the game.
     */
    private function userInGame($user, Game $game): bool
    {
        return $game->gameRoom->players->contains($user->id);
    }
}
