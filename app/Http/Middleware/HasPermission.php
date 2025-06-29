<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modern permission-based middleware to replace IsDeveloper.
 * 
 * Usage: 
 * - Route::middleware('permission:admin.user_management.view')
 * - Route::middleware('permission:admin.system.logs,admin.developer.debug')
 */
final class HasPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        
        if (!$user) {
            abort(403, 'Authentication required');
        }
        
        // If no permissions specified, deny access
        if (empty($permissions)) {
            abort(500, 'No permissions specified for middleware');
        }
        
        // Check if user has any of the required permissions
        $hasPermission = false;
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                $hasPermission = true;
                break;
            }
        }
        
        // Also check for legacy developer access as fallback
        if (!$hasPermission && $user->is_developer) {
            $hasPermission = true;
        }
        
        if (!$hasPermission) {
            abort(403, 'Insufficient permissions. Required: ' . implode(' OR ', $permissions));
        }
        
        // Log permission usage for audit trail
        $this->logPermissionUsage($user, $permissions, $request);

        return $next($request);
    }
    
    /**
     * Log permission usage for audit trail.
     */
    private function logPermissionUsage($user, array $permissions, Request $request): void
    {
        // Find which permission was actually used
        $usedPermission = null;
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                $usedPermission = $permission;
                break;
            }
        }
        
        // If no specific permission found but user is developer, log that
        if (!$usedPermission && $user->is_developer) {
            $usedPermission = 'legacy_developer_access';
        }
        
        if ($usedPermission) {
            activity('permission_usage')
                ->by($user)
                ->withProperties([
                    'permission_used' => $usedPermission,
                    'requested_permissions' => $permissions,
                    'route' => $request->route()?->getName(),
                    'url' => $request->url(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log("Permission '{$usedPermission}' used for access");
        }
    }
}