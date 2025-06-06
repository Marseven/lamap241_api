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
        Schema::create('game_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Code unique de la salle
            $table->string('name');
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->decimal('bet_amount', 10, 2);
            $table->decimal('pot_amount', 10, 2)->default(0);
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->integer('max_players')->default(2);
            $table->integer('current_players')->default(0);
            $table->integer('rounds_to_win')->default(3);
            $table->integer('time_limit')->default(300); // En secondes
            $table->boolean('allow_spectators')->default(false);
            $table->enum('status', ['waiting', 'ready', 'playing', 'finished', 'cancelled'])->default('waiting');
            $table->foreignId('winner_id')->nullable()->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('code');
            $table->index('creator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_rooms');
    }
};
