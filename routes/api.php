<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\GameRoomController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\CallbackController;
use App\Http\Controllers\Api\BotController;
use App\Http\Controllers\Api\GameTransitionController;
use App\Http\Controllers\Api\EnhancedStatsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Routes publiques avec rate limiting renforcé
Route::group(['prefix' => 'auth', 'middleware' => 'api.rate:auth'], function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {
    // Broadcasting authentication  
    Route::post('/broadcasting/auth', function (Request $request) {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Pour les canaux Reverb, on retourne simplement les infos utilisateur
        return response()->json([
            'user_id' => $user->id,
            'user_info' => [
                'id' => $user->id,
                'pseudo' => $user->pseudo,
                'name' => $user->name,
            ]
        ]);
    });

    // Authentification
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });

    // Portefeuille avec rate limiting pour les paiements
    Route::group(['prefix' => 'wallet'], function () {
        Route::get('/balance', [WalletController::class, 'balance']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
        Route::get('/transactions/{reference}', [WalletController::class, 'transactionDetails']);
        Route::get('/transaction/{reference}/status', [WalletController::class, 'checkTransactionStatus']);
        
        // Routes de paiement avec rate limiting strict
        Route::group(['middleware' => 'api.rate:payment'], function () {
            Route::post('/deposit', [WalletController::class, 'deposit']);
            Route::post('/withdraw', [WalletController::class, 'withdraw']);
        });
    });

    // Salles de jeu avec rate limiting
    Route::group(['prefix' => 'rooms', 'middleware' => 'api.rate:game'], function () {
        Route::get('/', [GameRoomController::class, 'index']);
        Route::post('/', [GameRoomController::class, 'create']);
        Route::get('/{code}', [GameRoomController::class, 'show']);
        Route::post('/{code}/join', [GameRoomController::class, 'join']);
        Route::post('/{code}/leave', [GameRoomController::class, 'leave']);
        Route::post('/{code}/ready', [GameRoomController::class, 'ready']);
    });

    // Jeux avec rate limiting pour les actions de jeu
    Route::group(['prefix' => 'games', 'middleware' => 'api.rate:game'], function () {
        Route::get('/{gameId}', [GameController::class, 'show']);
        Route::get('/{gameId}/state', [GameController::class, 'state']);
        Route::post('/{gameId}/play', [GameController::class, 'playCard']);
        Route::post('/{gameId}/pass', [GameController::class, 'pass']);
        Route::post('/{gameId}/forfeit', [GameController::class, 'forfeit']);
        Route::get('/{gameId}/moves', [GameController::class, 'moves']);
    });

    // Statistiques de base
    Route::group(['prefix' => 'stats'], function () {
        Route::get('/me', [StatsController::class, 'myStats']);
        Route::get('/leaderboard', [StatsController::class, 'leaderboard']);
        Route::get('/achievements', [StatsController::class, 'achievements']);
        Route::get('/user/{userId}', [StatsController::class, 'userStats']);
    });

    // Statistiques avancées
    Route::group(['prefix' => 'enhanced-stats'], function () {
        Route::get('/me/detailed', [EnhancedStatsController::class, 'getMyDetailedStats']);
        Route::get('/user/{userId}/detailed', [EnhancedStatsController::class, 'getUserDetailedStats']);
        Route::get('/leaderboards', [EnhancedStatsController::class, 'getAllLeaderboards']);
        Route::get('/leaderboard/{type}', [EnhancedStatsController::class, 'getLeaderboard']);
        Route::get('/me/achievements', [EnhancedStatsController::class, 'getMyAchievements']);
        Route::get('/user/{userId}/achievements', [EnhancedStatsController::class, 'getUserAchievements']);
        Route::get('/achievements/leaderboard', [EnhancedStatsController::class, 'getAchievementLeaderboard']);
        Route::get('/achievements/global', [EnhancedStatsController::class, 'getGlobalAchievementStats']);
        Route::get('/global', [EnhancedStatsController::class, 'getGlobalStats']);
        Route::get('/compare/{userId1}/{userId2}', [EnhancedStatsController::class, 'compareUsers']);
        Route::get('/me/progress', [EnhancedStatsController::class, 'getProgressStats']);
    });

    // Bots/IA
    Route::group(['prefix' => 'bots', 'middleware' => 'api.rate:game'], function () {
        Route::get('/', [BotController::class, 'listBots']);
        Route::post('/', [BotController::class, 'createBot']);
        Route::get('/{botId}', [BotController::class, 'getBotStats']);
        Route::put('/{botId}', [BotController::class, 'updateBot']);
        Route::delete('/{botId}', [BotController::class, 'deleteBot']);
        Route::post('/rooms/{roomCode}/add', [BotController::class, 'addBotToRoom']);
        Route::post('/games/{gameId}/play', [BotController::class, 'playBotMove']);
    });

    // Transitions de jeu
    Route::group(['prefix' => 'transitions', 'middleware' => 'api.rate:game'], function () {
        Route::get('/rooms/{roomCode}/state', [GameTransitionController::class, 'getTransitionState']);
        Route::post('/rooms/{roomCode}/next-round', [GameTransitionController::class, 'forceNextRound']);
        Route::post('/rooms/{roomCode}/timeout', [GameTransitionController::class, 'timeoutGame']);
        Route::delete('/rooms/{roomCode}/cleanup', [GameTransitionController::class, 'cleanupTransition']);
        Route::get('/rooms/{roomCode}/history', [GameTransitionController::class, 'getTransitionHistory']);
        Route::get('/rooms/{roomCode}/stats', [GameTransitionController::class, 'getTransitionStats']);
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
