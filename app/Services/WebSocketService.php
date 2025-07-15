<?php

namespace App\Services;

use App\Events\NotificationSent;
use App\Events\RoomUpdated;
use App\Events\LeaderboardUpdated;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebSocketService
{
    /**
     * Envoyer une notification à tous les utilisateurs connectés
     */
    public function sendGlobalNotification(string $type, string $title, string $message, string $icon = '📢')
    {
        $notification = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'timestamp' => now()->toISOString(),
        ];

        broadcast(new NotificationSent($notification));
        
        Log::info('Global notification sent', ['notification' => $notification]);
    }

    /**
     * Envoyer une notification à un utilisateur spécifique
     */
    public function sendUserNotification(User $user, string $type, string $title, string $message, string $icon = '👤')
    {
        $notification = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'timestamp' => now()->toISOString(),
        ];

        broadcast(new NotificationSent($notification, $user));
        
        Log::info('User notification sent', [
            'user_id' => $user->id,
            'notification' => $notification
        ]);
    }

    /**
     * Notifier la mise à jour d'une salle
     */
    public function updateRoom(GameRoom $room, string $updateType = 'general')
    {
        // Invalider le cache d'accès pour cette salle
        $this->invalidateRoomCache($room->code);
        
        broadcast(new RoomUpdated($room, $updateType));
        
        Log::info('Room updated', [
            'room_code' => $room->code,
            'update_type' => $updateType,
            'players_count' => $room->players->count()
        ]);
    }

    /**
     * Mettre à jour le classement en temps réel
     */
    public function updateLeaderboard(array $leaderboardData, string $type = 'general')
    {
        broadcast(new LeaderboardUpdated($leaderboardData, $type));
        
        Log::info('Leaderboard updated', [
            'type' => $type,
            'entries_count' => count($leaderboardData)
        ]);
    }

    /**
     * Invalider le cache d'accès pour une salle
     */
    private function invalidateRoomCache(string $roomCode)
    {
        // Invalider tous les caches d'accès pour cette salle
        $pattern = "room_access_{$roomCode}_*";
        $keys = Cache::get($pattern, []);
        
        if (!empty($keys)) {
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
        
        // Invalider aussi le cache de jeu
        $pattern = "game_access_{$roomCode}_*";
        $keys = Cache::get($pattern, []);
        
        if (!empty($keys)) {
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }

    /**
     * Obtenir les statistiques des connexions WebSocket
     */
    public function getConnectionStats()
    {
        // En production, cela devrait interroger Reverb pour les vraies statistiques
        return [
            'active_channels' => $this->getActiveChannels(),
            'total_connections' => $this->getTotalConnections(),
            'server_status' => $this->getServerStatus(),
            'last_activity' => now()->toISOString(),
        ];
    }

    /**
     * Obtenir les canaux actifs
     */
    private function getActiveChannels()
    {
        // Simulation - en production, interroger Reverb
        $rooms = GameRoom::where('status', '!=', 'finished')->count();
        $games = GameRoom::where('status', 'in_progress')->count();
        
        return [
            'rooms' => $rooms,
            'games' => $games,
            'notifications' => 1,
            'leaderboard' => 1,
        ];
    }

    /**
     * Obtenir le nombre total de connexions
     */
    private function getTotalConnections()
    {
        // Simulation - en production, interroger Reverb
        return User::whereNotNull('last_activity')->where('last_activity', '>', now()->subMinutes(5))->count();
    }

    /**
     * Obtenir le statut du serveur
     */
    private function getServerStatus()
    {
        // Vérifier si le serveur WebSocket est configuré
        return [
            'configured' => !empty(env('REVERB_APP_KEY')),
            'connection' => env('BROADCAST_CONNECTION', 'null'),
            'host' => env('REVERB_HOST', 'localhost'),
            'port' => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
        ];
    }

    /**
     * Tester la connectivité WebSocket
     */
    public function testConnection()
    {
        try {
            $this->sendGlobalNotification('info', 'Test Connection', 'Testing WebSocket connectivity', '🧪');
            return true;
        } catch (\Exception $e) {
            Log::error('WebSocket test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}