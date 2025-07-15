<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;
    public $user;

    public function __construct(array $notification, User $user = null)
    {
        $this->notification = $notification;
        $this->user = $user;
    }

    public function broadcastOn()
    {
        $channels = [];
        
        if ($this->user) {
            // Notification privée pour un utilisateur spécifique
            $channels[] = new PrivateChannel('App.Models.User.' . $this->user->id);
        } else {
            // Notification publique
            $channels[] = new Channel('notifications');
        }
        
        return $channels;
    }

    public function broadcastAs()
    {
        return 'notification.sent';
    }

    public function broadcastWith()
    {
        return [
            'notification' => $this->notification,
            'timestamp' => now()->toISOString(),
        ];
    }
}