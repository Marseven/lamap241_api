<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel pour les salles de jeu (optimisé avec cache)
Broadcast::channel('room.{code}', function ($user, $code) {
    $cacheKey = "room_access_{$code}_{$user->id}";
    
    return Cache::remember($cacheKey, 300, function () use ($user, $code) {
        $room = \App\Models\GameRoom::where('code', $code)->first();
        
        if (!$room) {
            return false;
        }
        
        // Vérifier si l'utilisateur est dans la salle
        $isPlayer = $room->players->contains($user->id);
        
        // Permettre aussi aux spectateurs (si activé)
        $canSpectate = $room->allow_spectators && $room->status !== 'waiting';
        
        return $isPlayer || $canSpectate;
    });
});

// Channel pour les parties en cours (optimisé avec cache)
Broadcast::channel('game.{code}', function ($user, $code) {
    $cacheKey = "game_access_{$code}_{$user->id}";
    
    return Cache::remember($cacheKey, 300, function () use ($user, $code) {
        $room = \App\Models\GameRoom::where('code', $code)->first();
        
        if (!$room) {
            return false;
        }
        
        // Vérifier si l'utilisateur est dans la salle
        $isPlayer = $room->players->contains($user->id);
        
        // Permettre aussi aux spectateurs pour les parties en cours
        $canSpectate = $room->allow_spectators && $room->status === 'in_progress';
        
        return $isPlayer || $canSpectate;
    });
});

// Channel pour les notifications globales
Broadcast::channel('notifications', function ($user) {
    return true; // Tous les utilisateurs connectés peuvent recevoir les notifications
});

// Channel pour les classements en temps réel
Broadcast::channel('leaderboard', function ($user) {
    return true; // Public pour tous les utilisateurs connectés
});
