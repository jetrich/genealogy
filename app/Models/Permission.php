<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'description', 
        'category',
        'is_sensitive',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
    ];

    /**
     * Users that have this permission.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions')
                    ->withPivot(['granted_at', 'granted_by', 'expires_at', 'justification'])
                    ->withTimestamps();
    }

    /**
     * Audit log entries for this permission.
     */
    public function auditLog(): HasMany
    {
        return $this->hasMany('App\Models\PermissionAuditLog');
    }

    /**
     * Scope permissions by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope sensitive permissions.
     */
    public function scopeSensitive($query)
    {
        return $query->where('is_sensitive', true);
    }

    /**
     * Scope non-sensitive permissions.
     */
    public function scopeNonSensitive($query)
    {
        return $query->where('is_sensitive', false);
    }

    /**
     * Get permissions grouped by category.
     */
    public static function getByCategory(): array
    {
        return self::orderBy('category')
                  ->orderBy('name')
                  ->get()
                  ->groupBy('category')
                  ->toArray();
    }
}