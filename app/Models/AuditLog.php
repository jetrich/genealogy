<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * AuditLog model for comprehensive audit trail management.
 */
class AuditLog extends Model
{
    protected $fillable = [
        'action',
        'category',
        'request_id',
        'user_id',
        'user_email',
        'session_id',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'referer',
        'team_id',
        'subject_type',
        'subject_id',
        'context',
        'severity',
        'requires_review',
        'reviewed',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'device_fingerprint',
        'suspicious_activity',
        'cross_team_access',
        'retention_until',
        'exported',
        'exported_at',
    ];

    protected $casts = [
        'context' => 'array',
        'requires_review' => 'boolean',
        'reviewed' => 'boolean',
        'reviewed_at' => 'datetime',
        'suspicious_activity' => 'boolean',
        'cross_team_access' => 'boolean',
        'retention_until' => 'datetime',
        'exported' => 'boolean',
        'exported_at' => 'datetime',
    ];

    /**
     * User who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * User who reviewed the audit entry.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Team associated with the action.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope for security events.
     */
    public function scopeSecurityEvents(Builder $query): Builder
    {
        return $query->whereIn('category', [
            'authentication',
            'authorization',
            'security_event',
        ]);
    }

    /**
     * Scope for admin actions.
     */
    public function scopeAdminActions(Builder $query): Builder
    {
        return $query->where('category', 'admin_action');
    }

    /**
     * Scope for genealogy data changes.
     */
    public function scopeGenealogyData(Builder $query): Builder
    {
        return $query->where('category', 'genealogy_data');
    }

    /**
     * Scope for high severity events.
     */
    public function scopeHighSeverity(Builder $query): Builder
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }

    /**
     * Scope for events requiring review.
     */
    public function scopeRequiresReview(Builder $query): Builder
    {
        return $query->where('requires_review', true);
    }

    /**
     * Scope for unreviewed events.
     */
    public function scopeUnreviewed(Builder $query): Builder
    {
        return $query->where('requires_review', true)
                    ->where('reviewed', false);
    }

    /**
     * Scope for suspicious activity.
     */
    public function scopeSuspicious(Builder $query): Builder
    {
        return $query->where('suspicious_activity', true);
    }

    /**
     * Scope for events from a specific IP.
     */
    public function scopeFromIp(Builder $query, string $ip): Builder
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Scope for events in date range.
     */
    public function scopeDateRange(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope for events by user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for events by team.
     */
    public function scopeByTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Mark audit entry as reviewed.
     */
    public function markAsReviewed(User $reviewer, string $notes = null): bool
    {
        return $this->update([
            'reviewed' => true,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'review_notes' => $notes,
        ]);
    }

    /**
     * Flag as suspicious activity.
     */
    public function flagAsSuspicious(): bool
    {
        return $this->update(['suspicious_activity' => true]);
    }

    /**
     * Get context value by key.
     */
    public function getContextValue(string $key, $default = null)
    {
        return data_get($this->context, $key, $default);
    }

    /**
     * Check if audit entry is expired based on retention policy.
     */
    public function isExpired(): bool
    {
        if (!$this->retention_until) {
            return false;
        }

        return $this->retention_until->isPast();
    }

    /**
     * Get formatted action description.
     */
    public function getFormattedActionAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->action));
    }

    /**
     * Get severity color for UI display.
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'gray',
        };
    }
}