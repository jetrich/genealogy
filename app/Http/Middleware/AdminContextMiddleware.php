<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Middleware to ensure administrative context is properly set for cross-team operations.
 * 
 * SECURITY: This middleware enforces that administrative operations requiring
 * cross-team access must provide proper authorization headers with justification.
 * 
 * Replaces dangerous developer bypasses with controlled access requiring
 * explicit authorization and audit trail.
 */
class AdminContextMiddleware
{
    /**
     * Handle an incoming request.
     * 
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated and is developer
        if (!auth()->check() || !auth()->user()->is_developer) {
            return response()->json([
                'error' => 'Administrative access denied',
                'message' => 'Only developers can access administrative functions'
            ], 403);
        }
        
        // Verify admin context headers are properly set
        if (!$this->hasValidAdminContext($request)) {
            return response()->json([
                'error' => 'Administrative context required',
                'message' => 'Administrative operations require proper authorization headers',
                'required_headers' => [
                    'X-Admin-Context' => 'Must be "authorized"',
                    'X-Admin-Justification' => 'Reason for administrative access (required)'
                ],
                'example' => [
                    'X-Admin-Context' => 'authorized',
                    'X-Admin-Justification' => 'System maintenance: fixing data integrity issues'
                ]
            ], 403);
        }
        
        // Log the administrative access attempt
        $this->logAdminAccess($request);
        
        return $next($request);
    }
    
    /**
     * Check if request has valid admin context headers.
     * 
     * @param Request $request
     * @return bool
     */
    private function hasValidAdminContext(Request $request): bool
    {
        $adminContext = $request->header('X-Admin-Context');
        $justification = $request->header('X-Admin-Justification');
        
        // Both headers must be present and non-empty
        if (empty($adminContext) || empty($justification)) {
            return false;
        }
        
        // Admin context must be exactly "authorized"
        if ($adminContext !== 'authorized') {
            return false;
        }
        
        // Justification must be meaningful (at least 10 characters)
        if (strlen(trim($justification)) < 10) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Log administrative access for audit trail.
     * 
     * @param Request $request
     * @return void
     */
    private function logAdminAccess(Request $request): void
    {
        $user = auth()->user();
        
        $logData = [
            'event' => 'admin_context_middleware_passed',
            'user_id' => $user->id,
            'user_email' => $user->email,
            'route' => $request->route()?->getName() ?? $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'justification' => $request->header('X-Admin-Justification'),
            'timestamp' => now()->toISOString(),
            'session_id' => session()->getId(),
        ];
        
        // Log to Laravel log for security audit
        Log::info('Administrative middleware access granted', $logData);
        
        // Also log using activity log if available
        if (function_exists('activity')) {
            activity('security')
                ->by($user)
                ->withProperties($logData)
                ->log('Administrative middleware access granted for: ' . $request->path());
        }
    }
}