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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_bot')->default(false)->after('status');
            $table->enum('bot_difficulty', ['easy', 'medium', 'hard'])->nullable()->after('is_bot');
            $table->json('bot_settings')->nullable()->after('bot_difficulty');
            $table->timestamp('last_bot_activity')->nullable()->after('bot_settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_bot', 'bot_difficulty', 'bot_settings', 'last_bot_activity']);
        });
    }
};