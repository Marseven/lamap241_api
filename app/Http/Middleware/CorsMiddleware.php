<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:5173',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:5173',
            'https://lamap241.vercel.app',
            'https://lamap241-git-main-lamap241.vercel.app',
            'https://www.lamap241.com',
            'https://lamap241.com',
        ];

        // Ajouter les URLs d'environnement
        if (env('FRONTEND_URL_PROD')) {
            $allowedOrigins[] = env('FRONTEND_URL_PROD');
        }

        // Ajouter les URLs multiples depuis l'environnement
        if (env('FRONTEND_URLS')) {
            $envUrls = explode(',', env('FRONTEND_URLS'));
            $allowedOrigins = array_merge($allowedOrigins, array_map('trim', $envUrls));
        }

        $origin = $request->header('Origin');

        // Vérifier si l'origine est autorisée
        $isAllowed = false;
        foreach ($allowedOrigins as $allowedOrigin) {
            if ($origin === $allowedOrigin) {
                $isAllowed = true;
                break;
            }
        }

        // Vérifier les patterns Vercel
        if (!$isAllowed && $origin) {
            if (preg_match('#^https://lamap241.*\.vercel\.app$#', $origin)) {
                $isAllowed = true;
            }
        }

        // Traiter les requêtes preflight
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200);
        } else {
            $response = $next($request);
        }

        // Ajouter les headers CORS si l'origine est autorisée
        if ($isAllowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '3600');
        }

        return $response;
    }
}