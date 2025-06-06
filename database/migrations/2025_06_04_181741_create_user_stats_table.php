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
        Schema::create('user_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('games_played')->default(0);
            $table->integer('games_won')->default(0);
            $table->integer('games_lost')->default(0);
            $table->integer('games_abandoned')->default(0);
            $table->integer('rounds_played')->default(0);
            $table->integer('rounds_won')->default(0);
            $table->decimal('total_bet', 12, 2)->default(0);
            $table->decimal('total_won', 12, 2)->default(0);
            $table->decimal('total_lost', 12, 2)->default(0);
            $table->decimal('biggest_win', 10, 2)->default(0);
            $table->integer('win_streak')->default(0);
            $table->integer('current_streak')->default(0);
            $table->integer('best_streak')->default(0);
            $table->json('achievements')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['games_won', 'total_won']); // Pour les classements
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_stats');
    }
};
