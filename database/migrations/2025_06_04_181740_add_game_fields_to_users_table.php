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
            $table->string('pseudo')->unique()->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->decimal('balance', 10, 2)->default(0)->after('password');
            $table->string('avatar')->nullable();
            $table->enum('status', ['active', 'suspended', 'banned'])->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->json('settings')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'pseudo',
                'phone',
                'balance',
                'avatar',
                'status',
                'last_seen_at',
                'settings'
            ]);
        });
    }
};
