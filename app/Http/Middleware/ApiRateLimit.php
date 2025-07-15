<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $limiter = 'api'): Response
    {
        $key = $this->resolveRequestSignature($request);
        
        $limit = $this->getLimit($limiter, $request);
        $decay = $this->getDecay($limiter);
        
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return $this->buildResponse($key, $limit, $decay);
        }
        
        RateLimiter::hit($key, $decay);
        
        $response = $next($request);
        
        return $this->addHeaders($response, $key, $limit, $decay);
    }
    
    /**
     * Resolve request signature
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = Auth::user();
        
        if ($user) {
            return sha1($user->id . '|' . $request->ip());
        }
        
        return sha1($request->ip());
    }
    
    /**
     * Get rate limit based on limiter type
     */
    protected function getLimit(string $limiter, Request $request): int
    {
        $user = Auth::user();
        
        return match ($limiter) {
            'auth' => $user ? 10 : 5, // Login/Register
            'game' => $user ? 30 : 10, // Game actions
            'payment' => $user ? 5 : 2, // Payment operations
            'api' => $user ? 100 : 30, // General API
            default => 60,
        };
    }
    
    /**
     * Get decay time in seconds
     */
    protected function getDecay(string $limiter): int
    {
        return match ($limiter) {
            'auth' => 900, // 15 minutes
            'game' => 300, // 5 minutes
            'payment' => 3600, // 1 hour
            'api' => 60, // 1 minute
            default => 60,
        };
    }
    
    /**
     * Build rate limit response
     */
    protected function buildResponse(string $key, int $limit, int $decay): Response
    {
        $retryAfter = RateLimiter::availableIn($key);
        
        return response()->json([
            'message' => 'Trop de requêtes. Veuillez réessayer plus tard.',
            'retry_after' => $retryAfter,
            'limit' => $limit,
            'decay' => $decay,
        ], 429)->withHeaders([
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
            'Retry-After' => $retryAfter,
        ]);
    }
    
    /**
     * Add rate limit headers to response
     */
    protected function addHeaders(Response $response, string $key, int $limit, int $decay): Response
    {
        $remaining = RateLimiter::remaining($key, $limit);
        $resetTime = RateLimiter::availableIn($key);
        
        return $response->withHeaders([
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => now()->addSeconds($resetTime)->timestamp,
        ]);
    }
}