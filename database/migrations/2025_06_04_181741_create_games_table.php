<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->onDelete('cascade');
            $table->integer('round_number')->default(1);
            $table->enum('status', ['in_progress', 'completed', 'abandoned'])->default('in_progress');
            $table->foreignId('current_player_id')->nullable()->constrained('users');
            $table->json('deck')->nullable(); // Deck mélangé pour cette manche
            $table->json('player_cards')->nullable(); // Cartes de chaque joueur
            $table->json('table_cards')->nullable(); // Cartes sur la table
            $table->json('game_state')->nullable(); // État complet du jeu
            $table->foreignId('round_winner_id')->nullable()->constrained('users');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['game_room_id', 'round_number']);
            $table->index(['game_room_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
