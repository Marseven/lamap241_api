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
            if (!Schema::hasIndex('users', 'users_email_index')) {
                $table->index(['email']);
            }
            if (!Schema::hasIndex('users', 'users_phone_index')) {
                $table->index(['phone']);
            }
            if (!Schema::hasIndex('users', 'users_status_index')) {
                $table->index(['status']);
            }
            if (!Schema::hasIndex('users', 'users_created_at_index')) {
                $table->index(['created_at']);
            }
            if (!Schema::hasIndex('users', 'users_updated_at_index')) {
                $table->index(['updated_at']);
            }
        });

        // Index pour les game_rooms
        Schema::table('game_rooms', function (Blueprint $table) {
            if (!Schema::hasIndex('game_rooms', 'game_rooms_code_index')) {
                $table->index(['code']);
            }
            if (!Schema::hasIndex('game_rooms', 'game_rooms_status_index')) {
                $table->index(['status']);
            }
            if (!Schema::hasIndex('game_rooms', 'game_rooms_creator_id_index')) {
                $table->index(['creator_id']);
            }
            if (Schema::hasColumn('game_rooms', 'is_exhibition') && !Schema::hasIndex('game_rooms', 'game_rooms_is_exhibition_index')) {
                $table->index(['is_exhibition']);
            }
            if (!Schema::hasIndex('game_rooms', 'game_rooms_created_at_index')) {
                $table->index(['created_at']);
            }
            if (!Schema::hasIndex('game_rooms', 'game_rooms_status_created_at_index')) {
                $table->index(['status', 'created_at']);
            }
            if (Schema::hasColumn('game_rooms', 'is_exhibition') && !Schema::hasIndex('game_rooms', 'game_rooms_status_is_exhibition_index')) {
                $table->index(['status', 'is_exhibition']);
            }
        });

        // Index pour les games
        Schema::table('games', function (Blueprint $table) {
            if (!Schema::hasIndex('games', 'games_game_room_id_index')) {
                $table->index(['game_room_id']);
            }
            if (!Schema::hasIndex('games', 'games_status_index')) {
                $table->index(['status']);
            }
            if (!Schema::hasIndex('games', 'games_current_player_id_index')) {
                $table->index(['current_player_id']);
            }
            if (!Schema::hasIndex('games', 'games_round_winner_id_index')) {
                $table->index(['round_winner_id']);
            }
            if (!Schema::hasIndex('games', 'games_created_at_index')) {
                $table->index(['created_at']);
            }
            if (!Schema::hasIndex('games', 'games_updated_at_index')) {
                $table->index(['updated_at']);
            }
            if (!Schema::hasIndex('games', 'games_status_created_at_index')) {
                $table->index(['status', 'created_at']);
            }
        });

        // Index pour les game_moves
        Schema::table('game_moves', function (Blueprint $table) {
            if (!Schema::hasIndex('game_moves', 'game_moves_game_id_index')) {
                $table->index(['game_id']);
            }
            if (!Schema::hasIndex('game_moves', 'game_moves_player_id_index')) {
                $table->index(['player_id']);
            }
            if (!Schema::hasIndex('game_moves', 'game_moves_move_type_index')) {
                $table->index(['move_type']);
            }
            if (!Schema::hasIndex('game_moves', 'game_moves_created_at_index')) {
                $table->index(['created_at']);
            }
            if (!Schema::hasIndex('game_moves', 'game_moves_game_id_created_at_index')) {
                $table->index(['game_id', 'created_at']);
            }
            if (!Schema::hasIndex('game_moves', 'game_moves_game_id_player_id_index')) {
                $table->index(['game_id', 'player_id']);
            }
        });

        // Index pour les wallets
        Schema::table('wallets', function (Blueprint $table) {
            if (!Schema::hasIndex('wallets', 'wallets_user_id_index')) {
                $table->index(['user_id']);
            }
            if (!Schema::hasIndex('wallets', 'wallets_balance_index')) {
                $table->index(['balance']);
            }
            if (!Schema::hasIndex('wallets', 'wallets_updated_at_index')) {
                $table->index(['updated_at']);
            }
        });

        // Index pour les transactions
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasIndex('transactions', 'transactions_user_id_index')) {
                $table->index(['user_id']);
            }
            if (!Schema::hasIndex('transactions', 'transactions_type_index')) {
                $table->index(['type']);
            }
            if (!Schema::hasIndex('transactions', 'transactions_status_index')) {
                $table->index(['status']);
            }
            if (!Schema::hasIndex('transactions', 'transactions_reference_index')) {
                $table->index(['reference']);
            }
            if (!Schema::hasIndex('transactions', 'transactions_created_at_index')) {
                $table->index(['created_at']);
            }
            if (!Schema::hasIndex('transactions', 'transactions_user_id_created_at_index')) {
                $table->index(['user_id', 'created_at']);
            }
            if (!Schema::hasIndex('transactions', 'transactions_user_id_type_index')) {
                $table->index(['user_id', 'type']);
            }
            if (!Schema::hasIndex('transactions', 'transactions_user_id_status_index')) {
                $table->index(['user_id', 'status']);
            }
            if (!Schema::hasIndex('transactions', 'transactions_status_created_at_index')) {
                $table->index(['status', 'created_at']);
            }
        });

        // Index pour les user_stats
        Schema::table('user_stats', function (Blueprint $table) {
            if (!Schema::hasIndex('user_stats', 'user_stats_user_id_index')) {
                $table->index(['user_id']);
            }
            if (!Schema::hasIndex('user_stats', 'user_stats_games_played_index')) {
                $table->index(['games_played']);
            }
            if (!Schema::hasIndex('user_stats', 'user_stats_games_won_index')) {
                $table->index(['games_won']);
            }
            if (!Schema::hasIndex('user_stats', 'user_stats_total_won_index')) {
                $table->index(['total_won']);
            }
            if (!Schema::hasIndex('user_stats', 'user_stats_biggest_win_index')) {
                $table->index(['biggest_win']);
            }
            if (!Schema::hasIndex('user_stats', 'user_stats_best_streak_index')) {
                $table->index(['best_streak']);
            }
            if (!Schema::hasIndex('user_stats', 'user_stats_total_won_games_won_index')) {
                $table->index(['total_won', 'games_won']);
            }
        });

        // Index pour les achievements (si la table existe)
        if (Schema::hasTable('achievements')) {
            Schema::table('achievements', function (Blueprint $table) {
                if (!Schema::hasIndex('achievements', 'achievements_user_id_index')) {
                    $table->index(['user_id']);
                }
                if (!Schema::hasIndex('achievements', 'achievements_type_index')) {
                    $table->index(['type']);
                }
                if (!Schema::hasIndex('achievements', 'achievements_achieved_at_index')) {
                    $table->index(['achieved_at']);
                }
                if (!Schema::hasIndex('achievements', 'achievements_user_id_type_index')) {
                    $table->index(['user_id', 'type']);
                }
                if (!Schema::hasIndex('achievements', 'achievements_user_id_achieved_at_index')) {
                    $table->index(['user_id', 'achieved_at']);
                }
            });
        }

        // Index pour game_room_players (pivot table)
        if (Schema::hasTable('game_room_players')) {
            Schema::table('game_room_players', function (Blueprint $table) {
                if (!Schema::hasIndex('game_room_players', 'game_room_players_game_room_id_index')) {
                    $table->index(['game_room_id']);
                }
                if (!Schema::hasIndex('game_room_players', 'game_room_players_user_id_index')) {
                    $table->index(['user_id']);
                }
                if (!Schema::hasIndex('game_room_players', 'game_room_players_is_ready_index')) {
                    $table->index(['is_ready']);
                }
                if (!Schema::hasIndex('game_room_players', 'game_room_players_joined_at_index')) {
                    $table->index(['joined_at']);
                }
                if (!Schema::hasIndex('game_room_players', 'game_room_players_game_room_id_user_id_index')) {
                    $table->index(['game_room_id', 'user_id']);
                }
                if (!Schema::hasIndex('game_room_players', 'game_room_players_game_room_id_is_ready_index')) {
                    $table->index(['game_room_id', 'is_ready']);
                }
            });
        }
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
            $table->dropIndex(['total_won']);
            $table->dropIndex(['biggest_win']);
            $table->dropIndex(['best_streak']);
            $table->dropIndex(['total_won', 'games_won']);
        });

        // Supprimer les index des achievements
        if (Schema::hasTable('achievements')) {
            Schema::table('achievements', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
                $table->dropIndex(['type']);
                $table->dropIndex(['achieved_at']);
                $table->dropIndex(['user_id', 'type']);
                $table->dropIndex(['user_id', 'achieved_at']);
            });
        }

        // Supprimer les index de game_room_players
        if (Schema::hasTable('game_room_players')) {
            Schema::table('game_room_players', function (Blueprint $table) {
                $table->dropIndex(['game_room_id']);
                $table->dropIndex(['user_id']);
                $table->dropIndex(['is_ready']);
                $table->dropIndex(['joined_at']);
                $table->dropIndex(['game_room_id', 'user_id']);
                $table->dropIndex(['game_room_id', 'is_ready']);
            });
        }
    }
};