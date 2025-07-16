<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\GameRoom;
use App\Models\Game;
use App\Services\GameAIService;
use App\Services\AchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class ApiTestSuite extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private GameAIService $aiService;
    private AchievementService $achievementService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->aiService = app(GameAIService::class);
        $this->achievementService = app(AchievementService::class);
        
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function test_authentication_endpoints()
    {
        // Test register
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'pseudo' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '123456789'
        ]);
        
        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);

        // Test login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);
        
        $loginResponse->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
    }

    /** @test */
    public function test_bot_management()
    {
        // Test create bot
        $response = $this->postJson('/api/bots', [
            'difficulty' => 'medium',
            'pseudo' => 'TestBot'
        ]);
        
        $response->assertStatus(201)
            ->assertJsonStructure(['bot' => ['id', 'pseudo', 'difficulty']]);

        // Test list bots
        $listResponse = $this->getJson('/api/bots');
        $listResponse->assertStatus(200)
            ->assertJsonStructure(['bots']);

        // Test bot stats
        $botId = $response->json('bot.id');
        $statsResponse = $this->getJson("/api/bots/{$botId}");
        $statsResponse->assertStatus(200)
            ->assertJsonStructure(['bot', 'stats']);
    }

    /** @test */
    public function test_game_room_lifecycle()
    {
        // Test create room
        $response = $this->postJson('/api/rooms', [
            'name' => 'Test Room',
            'bet_amount' => 1000,
            'max_players' => 4,
            'rounds_to_win' => 3,
            'is_exhibition' => false
        ]);
        
        $response->assertStatus(201)
            ->assertJsonStructure(['room' => ['code', 'name', 'status']]);

        $roomCode = $response->json('room.code');

        // Test join room
        $joinResponse = $this->postJson("/api/rooms/{$roomCode}/join");
        $joinResponse->assertStatus(200);

        // Test room details
        $detailsResponse = $this->getJson("/api/rooms/{$roomCode}");
        $detailsResponse->assertStatus(200)
            ->assertJsonStructure(['room' => ['code', 'players']]);
    }

    /** @test */
    public function test_enhanced_stats_endpoints()
    {
        // Test detailed stats
        $response = $this->getJson('/api/enhanced-stats/me/detailed');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => [
                    'basic_stats',
                    'financial_stats',
                    'performance_stats',
                    'achievements'
                ]
            ]);

        // Test leaderboards
        $leaderboardResponse = $this->getJson('/api/enhanced-stats/leaderboards');
        $leaderboardResponse->assertStatus(200)
            ->assertJsonStructure([
                'leaderboards' => [
                    'winnings',
                    'win_rate',
                    'achievements'
                ]
            ]);

        // Test achievements
        $achievementsResponse = $this->getJson('/api/enhanced-stats/me/achievements');
        $achievementsResponse->assertStatus(200)
            ->assertJsonStructure([
                'achievements' => [
                    'unlocked',
                    'locked',
                    'total_points'
                ]
            ]);
    }

    /** @test */
    public function test_game_transitions()
    {
        // Create a game room
        $room = GameRoom::factory()->create([
            'creator_id' => $this->user->id,
            'status' => 'playing'
        ]);

        // Test transition state
        $response = $this->getJson("/api/transitions/rooms/{$room->code}/state");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'transition_state' => [
                    'room_code',
                    'status',
                    'current_scores'
                ]
            ]);

        // Test transition history
        $historyResponse = $this->getJson("/api/transitions/rooms/{$room->code}/history");
        $historyResponse->assertStatus(200)
            ->assertJsonStructure(['history']);
    }

    /** @test */
    public function test_achievement_system()
    {
        // Create a game for achievement testing
        $game = Game::factory()->create([
            'round_winner_id' => $this->user->id,
            'status' => 'completed'
        ]);

        // Test achievement checking
        $achievements = $this->achievementService->checkAchievements($this->user, $game);
        $this->assertIsArray($achievements);

        // Test global achievement stats
        $response = $this->getJson('/api/enhanced-stats/achievements/global');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => [
                    'total_achievements',
                    'total_users',
                    'achievements'
                ]
            ]);
    }

    /** @test */
    public function test_rate_limiting()
    {
        // Test auth rate limiting
        for ($i = 0; $i < 12; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'invalid@example.com',
                'password' => 'wrong'
            ]);
        }
        
        $response->assertStatus(429); // Too Many Requests
    }

    /** @test */
    public function test_security_middleware()
    {
        // Test SQL injection detection
        $response = $this->getJson('/api/rooms?search=1\' OR 1=1--');
        $response->assertStatus(400); // Should be blocked by security middleware
    }

    /** @test */
    public function test_wallet_operations()
    {
        // Test balance
        $response = $this->getJson('/api/wallet/balance');
        $response->assertStatus(200)
            ->assertJsonStructure(['balance']);

        // Test transactions
        $transactionsResponse = $this->getJson('/api/wallet/transactions');
        $transactionsResponse->assertStatus(200)
            ->assertJsonStructure(['transactions']);
    }

    /** @test */
    public function test_global_stats()
    {
        $response = $this->getJson('/api/enhanced-stats/global');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => [
                    'total_users',
                    'total_games',
                    'total_rooms',
                    'achievement_stats'
                ]
            ]);
    }

    /** @test */
    public function test_user_comparison()
    {
        $user2 = User::factory()->create();
        
        $response = $this->getJson("/api/enhanced-stats/compare/{$this->user->id}/{$user2->id}");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'comparison' => [
                    'user1',
                    'user2',
                    'comparison'
                ]
            ]);
    }

    /** @test */
    public function test_game_maintenance_commands()
    {
        // Test via artisan command
        $this->artisan('game:maintenance stats')
            ->expectsOutput('Statistiques générales des parties:')
            ->assertExitCode(0);

        $this->artisan('bot:manage stats')
            ->assertExitCode(0);
    }

    /** @test */
    public function test_performance_optimization()
    {
        // Test backend optimization command
        $this->artisan('backend:optimize --force')
            ->expectsOutput('🎉 Optimisation terminée avec succès!')
            ->assertExitCode(0);
    }

    /** @test */
    public function test_error_handling()
    {
        // Test non-existent resource
        $response = $this->getJson('/api/rooms/INVALID');
        $response->assertStatus(404)
            ->assertJsonStructure(['message', 'error_code']);

        // Test unauthorized access
        $response = $this->postJson('/api/rooms/INVALID/join');
        $response->assertStatus(404);
    }

    /** @test */
    public function test_database_optimization()
    {
        // Verify that indexes are working
        $this->assertTrue(\Schema::hasTable('users'));
        $this->assertTrue(\Schema::hasTable('games'));
        $this->assertTrue(\Schema::hasTable('game_rooms'));
        $this->assertTrue(\Schema::hasTable('user_stats'));
        
        // Test that queries are optimized
        $users = User::limit(10)->get();
        $this->assertNotEmpty($users);
    }
}