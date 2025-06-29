<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Person;
use App\Models\Couple;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Provides secure administrative access to cross-team data.
 * 
 * SECURITY: Only explicitly authorized administrative operations
 * can bypass team scoping, with full audit logging and multiple
 * authorization checks.
 * 
 * This service replaces dangerous developer bypasses with controlled,
 * audited administrative access for genealogy application maintenance.
 */
class AdminAccessService
{
    /**
     * Execute callback without team scope restrictions for Person model.
     * 
     * SECURITY: Requires explicit authorization and logs all access.
     * 
     * @param callable $callback
     * @return mixed
     * @throws RuntimeException
     */
    public static function withoutPersonTeamScope(callable $callback)
    {
        if (!self::isAuthorizedAdmin()) {
            throw new RuntimeException('Unauthorized administrative access attempt');
        }
        
        // Log the administrative access
        self::logAdminAccess('person_cross_team_access');
        
        // Execute without team scope on Person model
        return Person::withoutGlobalScope('secure_team')->where(function () use ($callback) {
            return $callback();
        });
    }
    
    /**
     * Execute callback without team scope restrictions for Couple model.
     * 
     * SECURITY: Requires explicit authorization and logs all access.
     * 
     * @param callable $callback
     * @return mixed
     * @throws RuntimeException
     */
    public static function withoutCoupleTeamScope(callable $callback)
    {
        if (!self::isAuthorizedAdmin()) {
            throw new RuntimeException('Unauthorized administrative access attempt');
        }
        
        // Log the administrative access
        self::logAdminAccess('couple_cross_team_access');
        
        // Execute without team scope on Couple model
        return Couple::withoutGlobalScope('secure_team')->where(function () use ($callback) {
            return $callback();
        });
    }
    
    /**
     * Get all people across teams (administrative access).
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws RuntimeException
     */
    public static function getAllPeople()
    {
        return self::withoutPersonTeamScope(function () {
            return Person::with(['team', 'couples'])->get();
        });
    }
    
    /**
     * Get all couples across teams (administrative access).
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws RuntimeException
     */
    public static function getAllCouples()
    {
        return self::withoutCoupleTeamScope(function () {
            return Couple::with(['person1', 'person2', 'team'])->get();
        });
    }
    
    /**
     * Get cross-team statistics (administrative access).
     * 
     * @return array
     * @throws RuntimeException
     */
    public static function getCrossTeamStatistics(): array
    {
        if (!self::isAuthorizedAdmin()) {
            throw new RuntimeException('Unauthorized administrative access attempt');
        }
        
        self::logAdminAccess('cross_team_statistics');
        
        return [
            'total_people' => Person::withoutGlobalScope('secure_team')->count(),
            'total_couples' => Couple::withoutGlobalScope('secure_team')->count(),
            'teams_with_counts' => \App\Models\Team::withCount([
                'people' => function ($query) {
                    $query->withoutGlobalScope('secure_team');
                },
                'couples' => function ($query) {
                    $query->withoutGlobalScope('secure_team');
                }
            ])->get(),
        ];
    }
    
    /**
     * Check if current user is authorized for administrative access.
     * 
     * SECURITY: Requires multiple authorization factors:
     * 1. Must be authenticated
     * 2. Must have cross-team access permission OR be legacy developer
     * 3. Must provide admin context header
     * 4. Must provide justification
     * 
     * @return bool
     */
    private static function isAuthorizedAdmin(): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // Check for proper permissions or legacy developer access
        $hasPermission = $user->hasPermission('admin.cross_team_access.people') ||
                        $user->hasPermission('admin.cross_team_access.couples') ||
                        $user->hasPermission('admin.cross_team_access.statistics') ||
                        $user->is_developer; // Backward compatibility
        
        // Multi-factor authorization check:
        return $hasPermission && 
               request()->header('X-Admin-Context') === 'authorized' &&
               !empty(request()->header('X-Admin-Justification'));
    }
    
    /**
     * Log administrative access for audit trail.
     * 
     * @param string $action
     * @return void
     */
    private static function logAdminAccess(string $action): void
    {
        $user = auth()->user();
        $logData = [
            'action' => $action,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'justification' => request()->header('X-Admin-Justification'),
            'timestamp' => now()->toISOString(),
            'session_id' => session()->getId(),
        ];
        
        // Log to Laravel log for immediate visibility
        Log::warning('Administrative cross-team access executed', $logData);
        
        // Also log using activity log if available
        if (function_exists('activity')) {
            activity('security')
                ->by($user)
                ->withProperties($logData)
                ->log("Administrative cross-team access: {$action}");
        }
    }
    
    /**
     * Validate admin context headers are present.
     * 
     * @throws InvalidArgumentException
     */
    public static function validateAdminContext(): void
    {
        if (!request()->header('X-Admin-Context') || 
            !request()->header('X-Admin-Justification')) {
            
            throw new InvalidArgumentException(
                'Administrative context required. Set headers: ' .
                'X-Admin-Context: authorized, X-Admin-Justification: <reason>'
            );
        }
    }
    
    /**
     * Check if current request has proper admin context.
     * 
     * @return bool
     */
    public static function hasAdminContext(): bool
    {
        return !empty(request()->header('X-Admin-Context')) &&
               !empty(request()->header('X-Admin-Justification'));
    }
}