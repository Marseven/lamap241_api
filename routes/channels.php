<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel pour les salles de jeu
Broadcast::channel('room.{code}', function ($user, $code) {
    $room = \App\Models\GameRoom::where('code', $code)->first();
    return $room && $room->players->contains($user->id);
});

// Channel pour les parties en cours
Broadcast::channel('game.{code}', function ($user, $code) {
    $room = \App\Models\GameRoom::where('code', $code)->first();
    return $room && $room->players->contains($user->id);
});
