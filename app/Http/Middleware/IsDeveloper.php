<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IsDeveloper
{
    /**
     * Handle an incoming request.
     * 
     * DEPRECATED: This middleware is being phased out in favor of granular permissions.
     * Use HasPermission middleware instead for new implementations.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            abort(403, 'Authentication required');
        }
        
        // Check for legacy developer access OR modern developer permissions
        $hasAccess = $user->is_developer || 
                    $user->hasPermission('admin.developer.database') ||
                    $user->hasPermission('admin.developer.debug');
        
        if (!$hasAccess) {
            abort(403, 'Insufficient privileges');
        }

        return $next($request);
    }
}
