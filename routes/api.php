<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\GameRoomController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\CallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Routes publiques
Route::group(['prefix' => 'auth'], function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {
    // Authentification
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });

    // Portefeuille
    Route::group(['prefix' => 'wallet'], function () {
        Route::get('/balance', [WalletController::class, 'balance']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
        Route::get('/transactions/{reference}', [WalletController::class, 'transactionDetails']);
        Route::post('/deposit', [WalletController::class, 'deposit']);
        Route::post('/withdraw', [WalletController::class, 'withdraw']);
    });

    // Salles de jeu
    Route::group(['prefix' => 'rooms'], function () {
        Route::get('/', [GameRoomController::class, 'index']);
        Route::post('/', [GameRoomController::class, 'create']);
        Route::get('/{code}', [GameRoomController::class, 'show']);
        Route::post('/{code}/join', [GameRoomController::class, 'join']);
        Route::post('/{code}/leave', [GameRoomController::class, 'leave']);
        Route::post('/{code}/ready', [GameRoomController::class, 'ready']);
    });

    // Jeux
    Route::group(['prefix' => 'games'], function () {
        Route::get('/{gameId}', [GameController::class, 'show']);
        Route::get('/{gameId}/state', [GameController::class, 'state']);
        Route::post('/{gameId}/play', [GameController::class, 'playCard']);
        Route::post('/{gameId}/pass', [GameController::class, 'pass']);
        Route::post('/{gameId}/forfeit', [GameController::class, 'forfeit']);
        Route::get('/{gameId}/moves', [GameController::class, 'moves']);
    });

    // Statistiques
    Route::group(['prefix' => 'stats'], function () {
        Route::get('/me', [StatsController::class, 'myStats']);
        Route::get('/leaderboard', [StatsController::class, 'leaderboard']);
        Route::get('/achievements', [StatsController::class, 'achievements']);
        Route::get('/user/{userId}', [StatsController::class, 'userStats']);
    });
});

// Routes de callback (sans authentification)
Route::group(['prefix' => 'callback'], function () {
    // E-Billing callbacks
    Route::post('/ebilling/notification', [CallbackController::class, 'ebillingNotification'])->name('ebilling.notification');
    Route::get('/ebilling/redirect', [CallbackController::class, 'ebillingRedirect'])->name('ebilling.redirect');

    // SHAP webhook
    Route::post('/shap/webhook', [CallbackController::class, 'shapWebhook'])->name('shap.webhook');
});

// Route de test
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'version' => '1.0.0'
    ]);
});
