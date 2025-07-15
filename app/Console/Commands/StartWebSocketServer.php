<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class StartWebSocketServer extends Command
{
    protected $signature = 'websocket:start {--port=8080} {--host=0.0.0.0} {--debug}';
    protected $description = 'Start WebSocket server (Reverb) with production settings';

    public function handle()
    {
        $port = $this->option('port');
        $host = $this->option('host');
        $debug = $this->option('debug');

        $this->info("🚀 Starting WebSocket server...");
        $this->info("   Host: {$host}");
        $this->info("   Port: {$port}");
        
        if ($debug) {
            $this->info("   Debug mode: enabled");
        }

        // Vérifier la configuration
        if (!env('REVERB_APP_KEY')) {
            $this->error("❌ REVERB_APP_KEY not configured");
            return 1;
        }

        if (!env('REVERB_APP_SECRET')) {
            $this->error("❌ REVERB_APP_SECRET not configured");
            return 1;
        }

        // Construire la commande
        $command = ['php', 'artisan', 'reverb:start'];
        
        if ($debug) {
            $command[] = '--debug';
        }

        $this->info("✅ Configuration validated");
        $this->info("🔌 WebSocket server starting on {$host}:{$port}");
        $this->info("📡 Broadcasting channel: " . env('BROADCAST_CONNECTION', 'null'));
        $this->newLine();

        // Afficher les canaux disponibles
        $this->displayChannels();

        $this->newLine();
        $this->info("🎮 Server ready! Players can now connect to real-time features.");
        $this->info("   Use Ctrl+C to stop the server");
        $this->newLine();

        // Démarrer le serveur
        Process::run($command, function (string $type, string $output) {
            if ($type === Process::OUT) {
                $this->line($output);
            } else {
                $this->error($output);
            }
        });

        return 0;
    }

    private function displayChannels()
    {
        $this->info("📺 Available channels:");
        $this->line("   🔐 Private channels:");
        $this->line("     • App.Models.User.{id} - User notifications");
        $this->line("   👥 Presence channels:");
        $this->line("     • room.{code} - Game room updates");
        $this->line("     • game.{code} - Game state updates");
        $this->line("   📢 Public channels:");
        $this->line("     • notifications - Global notifications");
        $this->line("     • leaderboard - Leaderboard updates");
    }
}