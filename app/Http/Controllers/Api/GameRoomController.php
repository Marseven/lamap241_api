<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameRoomController extends Controller
{
    /**
     * Get list of game rooms.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:waiting,ready,playing,finished',
            'bet_min' => 'sometimes|numeric|min:0',
            'bet_max' => 'sometimes|numeric|min:0',
            'my_rooms' => 'sometimes|boolean',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = GameRoom::with(['creator:id,pseudo,avatar', 'players:id,pseudo,avatar']);

        // Filtres
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        } else {
            // Par défaut, montrer les salles en attente et prêtes
            $query->whereIn('status', ['waiting', 'ready']);
        }

        if (isset($validated['bet_min'])) {
            $query->where('bet_amount', '>=', $validated['bet_min']);
        }

        if (isset($validated['bet_max'])) {
            $query->where('bet_amount', '<=', $validated['bet_max']);
        }

        if (isset($validated['my_rooms']) && $validated['my_rooms']) {
            $query->where(function ($q) use ($request) {
                $q->where('creator_id', $request->user()->id)
                    ->orWhereHas('players', function ($q) use ($request) {
                        $q->where('users.id', $request->user()->id);
                    });
            });
        }

        $rooms = $query->orderBy('created_at', 'desc')
            ->limit($validated['limit'] ?? 20)
            ->get();

        return response()->json([
            'rooms' => $rooms->map(function ($room) {
                return $this->formatRoom($room);
            })
        ]);
    }

    /**
     * Create a new game room.
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'bet_amount' => 'required|numeric|min:500|max:100000',
            'max_players' => 'sometimes|integer|min:2|max:4',
            'rounds_to_win' => 'sometimes|integer|min:1|max:5',
            'time_limit' => 'sometimes|integer|min:60|max:600',
            'allow_spectators' => 'sometimes|boolean',
        ]);

        $user = $request->user();

        // Vérifier le solde
        if (!$user->canAfford($validated['bet_amount'])) {
            return response()->json([
                'message' => 'Solde insuffisant',
                'required' => $validated['bet_amount'],
                'balance' => $user->wallet->balance,
            ], 400);
        }

        // Créer la salle
        $room = DB::transaction(function () use ($validated, $user) {
            $room = GameRoom::create([
                'name' => $validated['name'],
                'creator_id' => $user->id,
                'bet_amount' => $validated['bet_amount'],
                'max_players' => $validated['max_players'] ?? 2,
                'rounds_to_win' => $validated['rounds_to_win'] ?? 3,
                'time_limit' => $validated['time_limit'] ?? 300,
                'allow_spectators' => $validated['allow_spectators'] ?? false,
                'current_players' => 0,
                'pot_amount' => 0,
            ]);

            // Ajouter le créateur comme premier joueur
            $room->addPlayer($user);

            return $room;
        });

        $room->load(['creator', 'players']);

        return response()->json([
            'room' => $this->formatRoom($room),
            'message' => 'Salle créée avec succès'
        ], 201);
    }

    /**
     * Get room details.
     */
    public function show(Request $request, $code)
    {
        $room = GameRoom::where('code', $code)
            ->with(['creator', 'players', 'games'])
            ->firstOrFail();

        return response()->json([
            'room' => $this->formatRoom($room, true)
        ]);
    }

    /**
     * Join a game room.
     */
    public function join(Request $request, $code)
    {
        $room = GameRoom::where('code', $code)->firstOrFail();
        $user = $request->user();

        // Vérifications
        if ($room->isFull()) {
            return response()->json([
                'message' => 'La salle est pleine'
            ], 400);
        }

        if ($room->status !== GameRoom::STATUS_WAITING) {
            return response()->json([
                'message' => 'La partie a déjà commencé'
            ], 400);
        }

        // Vérifier si déjà dans la salle
        if ($room->players->contains($user->id)) {
            return response()->json([
                'message' => 'Vous êtes déjà dans cette salle'
            ], 400);
        }

        // Vérifier le solde
        if (!$user->canAfford($room->bet_amount)) {
            return response()->json([
                'message' => 'Solde insuffisant',
                'required' => $room->bet_amount,
                'balance' => $user->wallet->balance,
            ], 400);
        }

        // Rejoindre la salle
        $success = $room->addPlayer($user);

        if (!$success) {
            return response()->json([
                'message' => 'Impossible de rejoindre la salle'
            ], 400);
        }

        $room->load(['players']);

        // Notifier les autres joueurs (via WebSocket en production)
        // broadcast(new PlayerJoinedRoom($room, $user))->toOthers();

        return response()->json([
            'room' => $this->formatRoom($room),
            'message' => 'Vous avez rejoint la salle'
        ]);
    }

    /**
     * Leave a game room.
     */
    public function leave(Request $request, $code)
    {
        $room = GameRoom::where('code', $code)->firstOrFail();
        $user = $request->user();

        // Vérifier si le joueur est dans la salle
        if (!$room->players->contains($user->id)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas dans cette salle'
            ], 400);
        }

        // Ne pas permettre de quitter une partie en cours
        if ($room->status === GameRoom::STATUS_PLAYING) {
            return response()->json([
                'message' => 'Impossible de quitter une partie en cours'
            ], 400);
        }

        // Quitter la salle
        $success = $room->removePlayer($user);

        if (!$success) {
            return response()->json([
                'message' => 'Impossible de quitter la salle'
            ], 400);
        }

        return response()->json([
            'message' => 'Vous avez quitté la salle'
        ]);
    }

    /**
     * Mark player as ready.
     */
    public function ready(Request $request, $code)
    {
        $room = GameRoom::where('code', $code)->firstOrFail();
        $user = $request->user();

        // Vérifier si le joueur est dans la salle
        $player = $room->players()->find($user->id);
        if (!$player) {
            return response()->json([
                'message' => 'Vous n\'êtes pas dans cette salle'
            ], 400);
        }

        // Marquer comme prêt
        $room->players()->updateExistingPivot($user->id, [
            'is_ready' => true,
            'status' => 'ready'
        ]);

        // Vérifier si tous les joueurs sont prêts
        if ($room->canStart()) {
            $game = $room->startGame();

            if ($game) {
                $game->dealCards();

                // Notifier les joueurs que la partie commence
                // broadcast(new GameStarted($room, $game))->toOthers();

                return response()->json([
                    'message' => 'La partie commence !',
                    'game_id' => $game->id,
                    'room' => $this->formatRoom($room->fresh())
                ]);
            }
        }

        return response()->json([
            'message' => 'Vous êtes prêt',
            'room' => $this->formatRoom($room->fresh())
        ]);
    }

    /**
     * Format room data.
     */
    private function formatRoom(GameRoom $room, $detailed = false)
    {
        $data = [
            'id' => $room->id,
            'code' => $room->code,
            'name' => $room->name,
            'creator' => [
                'id' => $room->creator->id,
                'pseudo' => $room->creator->pseudo,
                'avatar' => $room->creator->avatar,
            ],
            'bet_amount' => $room->bet_amount,
            'pot_amount' => $room->pot_amount,
            'commission_amount' => $room->commission_amount,
            'max_players' => $room->max_players,
            'current_players' => $room->current_players,
            'rounds_to_win' => $room->rounds_to_win,
            'time_limit' => $room->time_limit,
            'allow_spectators' => $room->allow_spectators,
            'status' => $room->status,
            'status_label' => $room->status_label,
            'status_color' => $room->status_color,
            'players' => $room->players->map(function ($player) {
                return [
                    'id' => $player->id,
                    'pseudo' => $player->pseudo,
                    'avatar' => $player->avatar,
                    'is_ready' => $player->pivot->is_ready,
                    'status' => $player->pivot->status,
                    'position' => $player->pivot->position,
                ];
            }),
            'created_at' => $room->created_at,
        ];

        if ($detailed) {
            $data['started_at'] = $room->started_at;
            $data['finished_at'] = $room->finished_at;
            $data['winner'] = $room->winner ? [
                'id' => $room->winner->id,
                'pseudo' => $room->winner->pseudo,
                'avatar' => $room->winner->avatar,
            ] : null;
            $data['current_game'] = $room->currentGame() ? [
                'id' => $room->currentGame()->id,
                'round_number' => $room->currentGame()->round_number,
                'status' => $room->currentGame()->status,
            ] : null;
        }

        return $data;
    }
}
