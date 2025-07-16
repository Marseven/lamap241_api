<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'api.rate' => \App\Http\Middleware\ApiRateLimit::class,
            'cors' => \App\Http\Middleware\CorsMiddleware::class,
        ]);
        
        $middleware->api(prepend: [
            \App\Http\Middleware\CorsMiddleware::class,
            \App\Http\Middleware\ApiRateLimit::class . ':api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\App\Exceptions\GameException $e) {
            return $e->render();
        });
        
        $exceptions->render(function (\App\Exceptions\PaymentException $e) {
            return $e->render();
        });
        
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ressource non trouvée',
                'error_code' => 'RESOURCE_NOT_FOUND',
                'status' => 'error',
                'timestamp' => now()->toISOString(),
            ], 404);
        });
        
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json([
                'message' => 'Non authentifié',
                'error_code' => 'UNAUTHENTICATED',
                'status' => 'error',
                'timestamp' => now()->toISOString(),
            ], 401);
        });
        
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'message' => 'Accès non autorisé',
                'error_code' => 'UNAUTHORIZED',
                'status' => 'error',
                'timestamp' => now()->toISOString(),
            ], 403);
        });
        
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Erreur HTTP',
                'error_code' => 'HTTP_ERROR',
                'status' => 'error',
                'timestamp' => now()->toISOString(),
            ], $e->getStatusCode());
        });
        
        $exceptions->render(function (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Unhandled exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => app()->environment('production') ? 'Erreur interne du serveur' : $e->getMessage(),
                'error_code' => 'INTERNAL_SERVER_ERROR',
                'status' => 'error',
                'timestamp' => now()->toISOString(),
            ], 500);
        });
    })->create();
