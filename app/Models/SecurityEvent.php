<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Security Event Model
 * 
 * Tracks security events and threats detected by the security monitoring system.
 * Provides forensic analysis and incident response capabilities.
 */
class SecurityEvent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'event_type',
        'user_id',
        'ip_address',
        'user_agent',
        'context',
        'severity',
        'resolved',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user associated with this security event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who resolved this security event.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope to filter by severity level.
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope to filter unresolved events.
     */
    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }

    /**
     * Scope to filter resolved events.
     */
    public function scopeResolved($query)
    {
        return $query->where('resolved', true);
    }

    /**
     * Scope to filter by event type.
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter critical events.
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope to filter high severity events.
     */
    public function scopeHigh($query)
    {
        return $query->where('severity', 'high');
    }

    /**
     * Scope to filter by IP address.
     */
    public function scopeByIpAddress($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope to filter events from the last 24 hours.
     */
    public function scopeLastDay($query)
    {
        return $query->where('created_at', '>=', now()->subDay());
    }

    /**
     * Scope to filter events from the last week.
     */
    public function scopeLastWeek($query)
    {
        return $query->where('created_at', '>=', now()->subWeek());
    }

    /**
     * Mark this security event as resolved.
     */
    public function markAsResolved(User $resolvedBy, string $notes = null): void
    {
        $this->update([
            'resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy->id,
            'resolution_notes' => $notes,
        ]);
    }

    /**
     * Get the display name for the event type.
     */
    public function getEventTypeDisplayAttribute(): string
    {
        return match ($this->event_type) {
            'privilege_escalation_attempt' => 'Privilege Escalation Attempt',
            'suspicious_file_access' => 'Suspicious File Access',
            'genealogy_data_attack' => 'Genealogy Data Attack',
            'mass_data_extraction' => 'Mass Data Extraction',
            'gedcom_exploitation' => 'GEDCOM Exploitation',
            'slow_request_detected' => 'Slow Request (Potential DoS)',
            'multiple_failed_access' => 'Multiple Failed Access Attempts',
            default => ucwords(str_replace('_', ' ', $this->event_type)),
        };
    }

    /**
     * Get the severity badge color.
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

    /**
     * Check if this event requires immediate attention.
     */
    public function requiresImmediateAttention(): bool
    {
        return in_array($this->severity, ['critical', 'high']) && !$this->resolved;
    }

    /**
     * Get threat intelligence summary from context.
     */
    public function getThreatIntelligence(): array
    {
        $context = $this->context ?? [];
        
        return [
            'threat_type' => $this->event_type,
            'severity' => $this->severity,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'timestamp' => $this->created_at->toISOString(),
            'user_id' => $this->user_id,
            'context_summary' => $this->summarizeContext($context),
        ];
    }

    /**
     * Summarize context for threat intelligence.
     */
    private function summarizeContext(array $context): array
    {
        $summary = [];
        
        if (isset($context['attack_type'])) {
            $summary['attack_type'] = $context['attack_type'];
        }
        
        if (isset($context['pattern'])) {
            $summary['attack_pattern'] = $context['pattern'];
        }
        
        if (isset($context['execution_time'])) {
            $summary['execution_time'] = $context['execution_time'];
        }
        
        if (isset($context['failed_attempts'])) {
            $summary['failed_attempts'] = $context['failed_attempts'];
        }
        
        return $summary;
    }
}