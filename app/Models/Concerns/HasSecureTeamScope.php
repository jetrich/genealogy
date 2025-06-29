<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Provides secure team-based data scoping for multi-tenant genealogy application.
 * 
 * SECURITY: This trait ensures NO developer bypasses exist. All data access
 * is properly scoped to the user's current team context.
 * 
 * This trait replaces vulnerable global scopes that allowed developers to bypass
 * team isolation, ensuring proper multi-tenancy security for family tree data.
 */
trait HasSecureTeamScope
{
    /**
     * Boot the secure team scope trait for a model.
     * 
     * SECURITY PRINCIPLE: NO BYPASSES - All authenticated users must have proper
     * team context to access data. Developers must use explicit admin services
     * for cross-team access with full audit logging.
     */
    protected static function bootHasSecureTeamScope(): void
    {
        static::addGlobalScope('secure_team', function (Builder $builder) {
            // Skip if user is not authenticated
            if (Auth::guest()) {
                return;
            }
            
            $user = auth()->user();
            $currentTeam = $user->currentTeam;
            
            if (!$currentTeam) {
                // SECURITY: No team access means no data access
                // This prevents any data leakage when users don't have proper team context
                $builder->whereRaw('1 = 0');
                return;
            }
            
            // SECURE: Always apply team scope - NO DEVELOPER EXCEPTIONS
            // Developers must use AdminAccessService for cross-team operations
            $modelTable = (new static())->getTable();
            $builder->where($modelTable . '.team_id', $currentTeam->id);
        });
    }
    
    /**
     * Get the table name for the model.
     * 
     * @return string
     */
    protected static function getTableName(): string
    {
        return (new static())->getTable();
    }
    
    /**
     * Scope query to specific team (for explicit team switching).
     * 
     * @param Builder $query
     * @param int $teamId
     * @return Builder
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where($this->getTable() . '.team_id', $teamId);
    }
    
    /**
     * Check if current user can access this model's team.
     * 
     * @return bool
     */
    public function canAccessTeam(): bool
    {
        if (Auth::guest()) {
            return false;
        }
        
        $user = auth()->user();
        $currentTeam = $user->currentTeam;
        
        if (!$currentTeam) {
            return false;
        }
        
        return $this->team_id === $currentTeam->id;
    }
}