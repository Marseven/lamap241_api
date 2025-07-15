<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GameException extends Exception
{
    protected $errorCode;
    protected $errorData;
    
    public function __construct(string $message, string $errorCode = 'GAME_ERROR', array $errorData = [], int $httpCode = 400)
    {
        parent::__construct($message, $httpCode);
        $this->errorCode = $errorCode;
        $this->errorData = $errorData;
    }
    
    /**
     * Render the exception as an HTTP response
     */
    public function render(): JsonResponse
    {
        // Logger l'erreur
        Log::error("Game Exception: {$this->errorCode}", [
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'data' => $this->errorData,
            'trace' => $this->getTraceAsString()
        ]);
        
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'error_data' => $this->errorData,
            'status' => 'error',
            'timestamp' => now()->toISOString(),
        ], $this->getCode());
    }
    
    /**
     * Game-specific error methods
     */
    public static function roomNotFound(string $roomCode): self
    {
        return new self(
            "Salle de jeu introuvable: {$roomCode}",
            'ROOM_NOT_FOUND',
            ['room_code' => $roomCode],
            404
        );
    }
    
    public static function roomFull(string $roomCode, int $maxPlayers): self
    {
        return new self(
            "La salle {$roomCode} est complète ({$maxPlayers} joueurs maximum)",
            'ROOM_FULL',
            ['room_code' => $roomCode, 'max_players' => $maxPlayers],
            400
        );
    }
    
    public static function gameInProgress(string $roomCode): self
    {
        return new self(
            "La partie dans la salle {$roomCode} a déjà commencé",
            'GAME_IN_PROGRESS',
            ['room_code' => $roomCode],
            400
        );
    }
    
    public static function insufficientFunds(int $required, int $available): self
    {
        return new self(
            "Solde insuffisant. Requis: {$required} FCFA, Disponible: {$available} FCFA",
            'INSUFFICIENT_FUNDS',
            ['required' => $required, 'available' => $available],
            400
        );
    }
    
    public static function notPlayerTurn(int $playerId, int $currentPlayerId): self
    {
        return new self(
            "Ce n'est pas votre tour de jouer",
            'NOT_PLAYER_TURN',
            ['player_id' => $playerId, 'current_player_id' => $currentPlayerId],
            400
        );
    }
    
    public static function invalidCard(array $card, string $reason): self
    {
        return new self(
            "Carte invalide: {$reason}",
            'INVALID_CARD',
            ['card' => $card, 'reason' => $reason],
            400
        );
    }
    
    public static function gameNotStarted(string $roomCode): self
    {
        return new self(
            "La partie dans la salle {$roomCode} n'a pas encore commencé",
            'GAME_NOT_STARTED',
            ['room_code' => $roomCode],
            400
        );
    }
    
    public static function gameCompleted(string $roomCode): self
    {
        return new self(
            "La partie dans la salle {$roomCode} est terminée",
            'GAME_COMPLETED',
            ['room_code' => $roomCode],
            400
        );
    }
    
    public static function playerNotInRoom(int $playerId, string $roomCode): self
    {
        return new self(
            "Vous n'êtes pas dans la salle {$roomCode}",
            'PLAYER_NOT_IN_ROOM',
            ['player_id' => $playerId, 'room_code' => $roomCode],
            403
        );
    }
    
    public static function cannotLeaveActiveGame(string $roomCode): self
    {
        return new self(
            "Impossible de quitter la salle {$roomCode} pendant une partie en cours",
            'CANNOT_LEAVE_ACTIVE_GAME',
            ['room_code' => $roomCode],
            400
        );
    }
}