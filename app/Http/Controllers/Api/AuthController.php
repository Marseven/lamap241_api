<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\UserStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'pseudo' => 'required|string|max:50|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'pseudo' => $validated['pseudo'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        // Créer le portefeuille avec bonus de bienvenue
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'balance' => 0,
            'bonus_balance' => 1000, // Bonus de bienvenue
        ]);

        // Créer les stats
        UserStats::create([
            'user_id' => $user->id,
        ]);

        // Créer la transaction bonus
        $wallet->transactions()->create([
            'user_id' => $user->id,
            'reference' => 'BON-' . uniqid(),
            'type' => 'bonus',
            'amount' => 1000,
            'balance_before' => 0,
            'balance_after' => 0,
            'status' => 'completed',
            'processed_at' => now(),
            'description' => 'Bonus de bienvenue'
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $this->formatUser($user),
            'token' => $token,
            'message' => 'Inscription réussie ! Bonus de bienvenue de 1000 FCFA ajouté.'
        ], 201);
    }

    /**
     * Login user.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'login' => 'required|string', // Email ou pseudo
            'password' => 'required|string',
        ]);

        // Chercher par email ou pseudo
        $user = User::where('email', $validated['login'])
            ->orWhere('pseudo', $validated['login'])
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Identifiants incorrects.'],
            ]);
        }

        // Supprimer les anciens tokens
        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        // Mettre à jour last_seen
        $user->updateLastSeen();

        return response()->json([
            'user' => $this->formatUser($user),
            'token' => $token,
            'message' => 'Connexion réussie !'
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        $user->load(['wallet', 'stats']);

        return response()->json([
            'user' => $this->formatUser($user)
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'pseudo' => 'sometimes|string|max:50|unique:users,pseudo,' . $user->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'avatar' => 'sometimes|nullable|string|url',
            'settings' => 'sometimes|array',
            'settings.notifications' => 'boolean',
            'settings.sound' => 'boolean',
            'settings.theme' => 'in:light,dark',
        ]);

        $user->update($validated);

        return response()->json([
            'user' => $this->formatUser($user),
            'message' => 'Profil mis à jour'
        ]);
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mot de passe actuel incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password'])
        ]);

        // Révoquer tous les tokens sauf le courant
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Mot de passe modifié avec succès'
        ]);
    }

    /**
     * Format user data for response.
     */
    private function formatUser(User $user)
    {
        $user->load(['wallet', 'stats']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'pseudo' => $user->pseudo,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'status' => $user->status,
            'balance' => $user->wallet->balance ?? 0,
            'bonus_balance' => $user->wallet->bonus_balance ?? 0,
            'total_balance' => $user->wallet->total_balance ?? 0,
            'is_online' => $user->isOnline(),
            'last_seen_at' => $user->last_seen_at,
            'settings' => $user->settings ?? [
                'notifications' => true,
                'sound' => true,
                'theme' => 'dark'
            ],
            'stats' => [
                'games_played' => $user->stats->games_played ?? 0,
                'games_won' => $user->stats->games_won ?? 0,
                'win_rate' => $user->stats->win_rate ?? 0,
                'total_won' => $user->stats->total_won ?? 0,
                'current_streak' => $user->stats->current_streak ?? 0,
                'best_streak' => $user->stats->best_streak ?? 0,
                'rank' => $user->stats->getRank() ?? 0,
            ],
            'created_at' => $user->created_at,
        ];
    }
}
