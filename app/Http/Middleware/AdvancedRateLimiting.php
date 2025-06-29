<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Advanced rate limiting for genealogy application endpoints.
 */
class AdvancedRateLimiting
{
    /**
     * Rate limiting rules for different endpoint types.
     */
    private const RATE_LIMITS = [
        'auth' => ['attempts' => 5, 'decay' => 300], // 5 attempts per 5 minutes
        'api' => ['attempts' => 60, 'decay' => 60], // 60 requests per minute
        'gedcom' => ['attempts' => 3, 'decay' => 3600], // 3 GEDCOM operations per hour
        'export' => ['attempts' => 5, 'decay' => 1800], // 5 exports per 30 minutes
        'admin' => ['attempts' => 30, 'decay' => 60], // 30 admin actions per minute
        'search' => ['attempts' => 100, 'decay' => 60], // 100 searches per minute
        'upload' => ['attempts' => 10, 'decay' => 600], // 10 uploads per 10 minutes
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $limitType = $this->determineLimitType($request);
        
        if ($limitType) {
            $key = $this->buildRateLimitKey($request, $limitType);
            $config = self::RATE_LIMITS[$limitType];
            
            if (RateLimiter::tooManyAttempts($key, $config['attempts'])) {
                $seconds = RateLimiter::availableIn($key);
                
                // Log rate limiting event
                \Log::channel('security')->warning('Rate limit exceeded', [
                    'ip_address' => $request->ip(),
                    'user_id' => auth()->id(),
                    'limit_type' => $limitType,
                    'path' => $request->path(),
                    'retry_after' => $seconds,
                ]);
                
                return response()->json([
                    'error' => 'Too many requests',
                    'retry_after' => $seconds,
                ], 429);
            }
            
            RateLimiter::hit($key, $config['decay']);
        }
        
        return $next($request);
    }
    
    /**
     * Determine the rate limit type based on request.
     */
    private function determineLimitType(Request $request): ?string
    {
        $path = $request->path();
        
        // Authentication endpoints
        if (str_contains($path, 'login') || str_contains($path, 'register') || str_contains($path, 'password')) {
            return 'auth';
        }
        
        // API endpoints
        if (str_starts_with($path, 'api/')) {
            return 'api';
        }
        
        // GEDCOM operations
        if (str_contains($path, 'gedcom') || str_contains($path, 'import') || str_contains($path, 'export')) {
            return 'gedcom';
        }
        
        // Export operations
        if (str_contains($path, 'export') || str_contains($path, 'download') || str_contains($path, 'backup')) {
            return 'export';
        }
        
        // Admin operations
        if (str_contains($path, 'admin/') || str_contains($path, 'developer/')) {
            return 'admin';
        }
        
        // Search operations
        if (str_contains($path, 'search') || $request->has('search')) {
            return 'search';
        }
        
        // Upload operations
        if ($request->hasFile('photo') || $request->hasFile('file') || str_contains($path, 'upload')) {
            return 'upload';
        }
        
        return null;
    }
    
    /**
     * Build rate limit key for the request.
     */
    private function buildRateLimitKey(Request $request, string $limitType): string
    {
        $identifier = auth()->check() 
            ? 'user:' . auth()->id() 
            : 'ip:' . $request->ip();
            
        return "rate_limit:{$limitType}:{$identifier}";
    }
}