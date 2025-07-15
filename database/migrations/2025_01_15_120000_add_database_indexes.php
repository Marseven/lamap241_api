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
        // Index pour les utilisateurs
        Schema::table('users', function (Blueprint $table) {
            $table->index(['email']);
            $table->index(['phone']);
            $table->index(['status']);
            $table->index(['created_at']);
            $table->index(['updated_at']);
        });

        // Index pour les game_rooms
        Schema::table('game_rooms', function (Blueprint $table) {
            $table->index(['code']);
            $table->index(['status']);
            $table->index(['creator_id']);
            $table->index(['is_exhibition']);
            $table->index(['created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['status', 'is_exhibition']);
        });

        // Index pour les games
        Schema::table('games', function (Blueprint $table) {
            $table->index(['game_room_id']);
            $table->index(['status']);
            $table->index(['current_player_id']);
            $table->index(['round_winner_id']);
            $table->index(['created_at']);
            $table->index(['updated_at']);
            $table->index(['status', 'created_at']);
        });

        // Index pour les game_moves
        Schema::table('game_moves', function (Blueprint $table) {
            $table->index(['game_id']);
            $table->index(['player_id']);
            $table->index(['move_type']);
            $table->index(['created_at']);
            $table->index(['game_id', 'created_at']);
            $table->index(['game_id', 'player_id']);
        });

        // Index pour les wallets
        Schema::table('wallets', function (Blueprint $table) {
            $table->index(['user_id']);
            $table->index(['balance']);
            $table->index(['updated_at']);
        });

        // Index pour les transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id']);
            $table->index(['type']);
            $table->index(['status']);
            $table->index(['reference']);
            $table->index(['created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        // Index pour les user_stats
        Schema::table('user_stats', function (Blueprint $table) {
            $table->index(['user_id']);
            $table->index(['games_played']);
            $table->index(['games_won']);
            $table->index(['win_rate']);
            $table->index(['total_winnings']);
            $table->index(['win_rate', 'games_played']);
            $table->index(['total_winnings', 'games_won']);
        });

        // Index pour les achievements
        Schema::table('achievements', function (Blueprint $table) {
            $table->index(['user_id']);
            $table->index(['type']);
            $table->index(['achieved_at']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'achieved_at']);
        });

        // Index pour game_room_user (pivot table)
        Schema::table('game_room_user', function (Blueprint $table) {
            $table->index(['game_room_id']);
            $table->index(['user_id']);
            $table->index(['is_ready']);
            $table->index(['joined_at']);
            $table->index(['game_room_id', 'user_id']);
            $table->index(['game_room_id', 'is_ready']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer les index des utilisateurs
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['phone']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['updated_at']);
        });

        // Supprimer les index des game_rooms
        Schema::table('game_rooms', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->dropIndex(['status']);
            $table->dropIndex(['creator_id']);
            $table->dropIndex(['is_exhibition']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['status', 'is_exhibition']);
        });

        // Supprimer les index des games
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['game_room_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['current_player_id']);
            $table->dropIndex(['round_winner_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['updated_at']);
            $table->dropIndex(['status', 'created_at']);
        });

        // Supprimer les index des game_moves
        Schema::table('game_moves', function (Blueprint $table) {
            $table->dropIndex(['game_id']);
            $table->dropIndex(['player_id']);
            $table->dropIndex(['move_type']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['game_id', 'created_at']);
            $table->dropIndex(['game_id', 'player_id']);
        });

        // Supprimer les index des wallets
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['balance']);
            $table->dropIndex(['updated_at']);
        });

        // Supprimer les index des transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['type']);
            $table->dropIndex(['status']);
            $table->dropIndex(['reference']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['user_id', 'type']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['status', 'created_at']);
        });

        // Supprimer les index des user_stats
        Schema::table('user_stats', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['games_played']);
            $table->dropIndex(['games_won']);
            $table->dropIndex(['win_rate']);
            $table->dropIndex(['total_winnings']);
            $table->dropIndex(['win_rate', 'games_played']);
            $table->dropIndex(['total_winnings', 'games_won']);
        });

        // Supprimer les index des achievements
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['type']);
            $table->dropIndex(['achieved_at']);
            $table->dropIndex(['user_id', 'type']);
            $table->dropIndex(['user_id', 'achieved_at']);
        });

        // Supprimer les index de game_room_user
        Schema::table('game_room_user', function (Blueprint $table) {
            $table->dropIndex(['game_room_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['is_ready']);
            $table->dropIndex(['joined_at']);
            $table->dropIndex(['game_room_id', 'user_id']);
            $table->dropIndex(['game_room_id', 'is_ready']);
        });
    }
};