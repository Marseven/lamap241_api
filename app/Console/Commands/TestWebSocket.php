<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Events\NotificationSent;
use App\Events\RoomUpdated;
use App\Events\LeaderboardUpdated;
use App\Models\GameRoom;
use App\Models\User;

class TestWebSocket extends Command
{
    protected $signature = 'websocket:test {type=all}';
    protected $description = 'Test WebSocket events';

    public function handle()
    {
        $type = $this->argument('type');

        $this->info("🚀 Testing WebSocket events...");

        switch ($type) {
            case 'notification':
                $this->testNotification();
                break;
            case 'room':
                $this->testRoomUpdate();
                break;
            case 'leaderboard':
                $this->testLeaderboard();
                break;
            case 'all':
            default:
                $this->testNotification();
                $this->testRoomUpdate();
                $this->testLeaderboard();
                break;
        }

        $this->info("✅ WebSocket tests completed!");
    }

    private function testNotification()
    {
        $this->info("📢 Testing notification event...");

        $notification = [
            'type' => 'info',
            'title' => 'Test Notification',
            'message' => 'This is a test notification from WebSocket',
            'icon' => '🧪',
        ];

        broadcast(new NotificationSent($notification));
        $this->line("   ✓ Global notification sent");

        // Test notification pour un utilisateur spécifique
        $user = User::first();
        if ($user) {
            $userNotification = [
                'type' => 'success',
                'title' => 'Personal Notification',
                'message' => "Hello {$user->pseudo}! This is a personal notification.",
                'icon' => '👋',
            ];

            broadcast(new NotificationSent($userNotification, $user));
            $this->line("   ✓ Personal notification sent to {$user->pseudo}");
        }
    }

    private function testRoomUpdate()
    {
        $this->info("🎮 Testing room update event...");

        $room = GameRoom::with('players')->first();
        if ($room) {
            broadcast(new RoomUpdated($room, 'test_update'));
            $this->line("   ✓ Room update sent for room: {$room->code}");
        } else {
            $this->warn("   ⚠️ No rooms found for testing");
        }
    }

    private function testLeaderboard()
    {
        $this->info("🏆 Testing leaderboard event...");

        $leaderboardData = [
            'top_players' => [
                ['pseudo' => 'Player1', 'score' => 1500, 'games_won' => 25],
                ['pseudo' => 'Player2', 'score' => 1200, 'games_won' => 18],
                ['pseudo' => 'Player3', 'score' => 900, 'games_won' => 12],
            ],
            'updated_at' => now()->toISOString(),
        ];

        broadcast(new LeaderboardUpdated($leaderboardData, 'wins'));
        $this->line("   ✓ Leaderboard update sent");
    }
}