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
        Schema::create('game_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('player_id')->constrained('users')->onDelete('cascade');
            $table->integer('move_number');
            $table->json('card_played'); // {value: 7, suit: '♥'}
            $table->json('game_state_before')->nullable();
            $table->json('game_state_after')->nullable();
            $table->string('move_type')->default('play_card'); // play_card, pass, etc.
            $table->timestamp('played_at');
            $table->timestamps();

            $table->index(['game_id', 'move_number']);
            $table->index(['game_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_moves');
    }
};
