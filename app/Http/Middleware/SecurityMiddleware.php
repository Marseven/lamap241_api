<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Validation des headers de sécurité
        $this->validateSecurityHeaders($request);
        
        // Détection des attaques basiques
        $this->detectBasicAttacks($request);
        
        // Validation de l'IP
        $this->validateIpAddress($request);
        
        // Validation de l'User-Agent
        $this->validateUserAgent($request);
        
        $response = $next($request);
        
        // Ajouter les headers de sécurité à la réponse
        $this->addSecurityHeaders($response);
        
        return $response;
    }
    
    /**
     * Valider les headers de sécurité
     */
    private function validateSecurityHeaders(Request $request): void
    {
        // Vérifier la présence de headers suspects
        $suspiciousHeaders = [
            'x-forwarded-for',
            'x-real-ip',
            'x-cluster-client-ip',
            'x-forwarded',
            'forwarded-for',
            'forwarded',
        ];
        
        foreach ($suspiciousHeaders as $header) {
            if ($request->hasHeader($header)) {
                $value = $request->header($header);
                if ($this->containsMaliciousContent($value)) {
                    $this->logSecurityEvent('suspicious_header', [
                        'header' => $header,
                        'value' => $value,
                        'ip' => $request->ip()
                    ]);
                }
            }
        }
    }
    
    /**
     * Détecter les attaques basiques
     */
    private function detectBasicAttacks(Request $request): void
    {
        $maliciousPatterns = [
            // SQL Injection
            '/(\b(select|insert|update|delete|drop|create|alter|exec|union|script)\b)/i',
            // XSS
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            // Path Traversal
            '/\.\.[\/\\\\]/',
            // Command Injection
            '/[;&|`$\(\)]/i',
        ];
        
        $input = json_encode($request->all());
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->logSecurityEvent('malicious_input', [
                    'pattern' => $pattern,
                    'input' => $input,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                
                abort(400, 'Requête malveillante détectée');
            }
        }
    }
    
    /**
     * Valider l'adresse IP
     */
    private function validateIpAddress(Request $request): void
    {
        $ip = $request->ip();
        
        // Vérifier les IP privées en production
        if (app()->environment('production')) {
            $privateRanges = [
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
                '127.0.0.0/8',
            ];
            
            foreach ($privateRanges as $range) {
                if ($this->ipInRange($ip, $range)) {
                    $this->logSecurityEvent('private_ip_access', [
                        'ip' => $ip,
                        'range' => $range
                    ]);
                }
            }
        }
        
        // Vérifier les IP blacklistées
        $blacklistedIps = config('security.blacklisted_ips', []);
        if (in_array($ip, $blacklistedIps)) {
            $this->logSecurityEvent('blacklisted_ip', ['ip' => $ip]);
            abort(403, 'Accès interdit');
        }
    }
    
    /**
     * Valider l'User-Agent
     */
    private function validateUserAgent(Request $request): void
    {
        $userAgent = $request->userAgent();
        
        if (empty($userAgent)) {
            $this->logSecurityEvent('empty_user_agent', ['ip' => $request->ip()]);
            return;
        }
        
        // Détecter les bots malveillants
        $maliciousBots = [
            'nikto',
            'sqlmap',
            'nmap',
            'masscan',
            'zap',
            'burp',
            'grabber',
            'w3af',
        ];
        
        foreach ($maliciousBots as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                $this->logSecurityEvent('malicious_bot', [
                    'user_agent' => $userAgent,
                    'ip' => $request->ip()
                ]);
                
                abort(403, 'Bot malveillant détecté');
            }
        }
    }
    
    /**
     * Ajouter les headers de sécurité à la réponse
     */
    private function addSecurityHeaders(Response $response): void
    {
        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
        
        foreach ($securityHeaders as $header => $value) {
            $response->headers->set($header, $value);
        }
    }
    
    /**
     * Vérifier si le contenu est malveillant
     */
    private function containsMaliciousContent(string $content): bool
    {
        $maliciousPatterns = [
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', // Caractères de contrôle
            '/\0/', // Null bytes
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/data:text\/html/i',
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Vérifier si une IP est dans une plage
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        [$subnet, $bits] = explode('/', $range);
        
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        
        return ($ip & $mask) == ($subnet & $mask);
    }
    
    /**
     * Logger les événements de sécurité
     */
    private function logSecurityEvent(string $event, array $data): void
    {
        Log::warning("Security event: {$event}", array_merge($data, [
            'timestamp' => now()->toISOString(),
            'request_id' => request()->id ?? 'unknown',
        ]));
    }
}