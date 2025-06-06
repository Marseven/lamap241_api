<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Wallet;
use App\Models\UserStats;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Créer des utilisateurs de test
        $users = [
            [
                'name' => 'Joueur Test',
                'pseudo' => 'player1',
                'email' => 'player1@test.com',
                'phone' => '+241074000001',
                'password' => Hash::make('password'),
                'balance' => 50000
            ],
            [
                'name' => 'Adversaire Test',
                'pseudo' => 'player2',
                'email' => 'player2@test.com',
                'phone' => '+241074000002',
                'password' => Hash::make('password'),
                'balance' => 75000
            ],
            [
                'name' => 'Demo User',
                'pseudo' => 'demo',
                'email' => 'demo@test.com',
                'phone' => '+241074000003',
                'password' => Hash::make('demo123'),
                'balance' => 10000
            ]
        ];

        foreach ($users as $userData) {
            $user = User::create($userData);

            // Créer le portefeuille
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'balance' => $user->balance,
                'bonus_balance' => 1000, // Bonus de bienvenue
                'total_deposited' => $user->balance,
            ]);

            // Créer les stats
            UserStats::create([
                'user_id' => $user->id,
                'games_played' => rand(0, 20),
                'games_won' => rand(0, 10),
                'games_lost' => rand(0, 10),
                'total_bet' => rand(10000, 100000),
                'total_won' => rand(5000, 50000),
                'total_lost' => rand(5000, 50000),
                'biggest_win' => rand(5000, 25000),
                'current_streak' => rand(0, 3),
                'best_streak' => rand(0, 5),
            ]);

            // Ajouter quelques transactions d'exemple
            $wallet->transactions()->create([
                'user_id' => $user->id,
                'reference' => 'DEP-' . uniqid(),
                'type' => 'deposit',
                'amount' => $user->balance,
                'balance_before' => 0,
                'balance_after' => $user->balance,
                'status' => 'completed',
                'payment_method' => 'airtel',
                'phone_number' => $user->phone,
                'processed_at' => now()->subDays(rand(1, 30)),
                'description' => 'Dépôt initial'
            ]);

            // Transaction bonus
            $wallet->transactions()->create([
                'user_id' => $user->id,
                'reference' => 'BON-' . uniqid(),
                'type' => 'bonus',
                'amount' => 1000,
                'balance_before' => $user->balance,
                'balance_after' => $user->balance,
                'status' => 'completed',
                'processed_at' => now()->subDays(rand(1, 30)),
                'description' => 'Bonus de bienvenue'
            ]);
        }

        $this->command->info('✅ Utilisateurs de test créés');
        $this->command->info('📧 Emails: player1@test.com, player2@test.com, demo@test.com');
        $this->command->info('🔑 Mot de passe: password (ou demo123 pour demo)');
    }
}
