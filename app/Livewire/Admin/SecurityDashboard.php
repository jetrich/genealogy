<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\SecurityEvent;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Security Dashboard Component
 * 
 * Provides comprehensive security monitoring interface for administrators.
 * Displays security events, threat analytics, and incident response tools.
 */
class SecurityDashboard extends Component
{
    use WithPagination;

    public string $severityFilter = 'all';
    public string $eventTypeFilter = 'all';
    public string $timeFilter = 'all';
    public bool $showResolvedOnly = false;
    public bool $showUnresolvedOnly = false;

    protected $queryString = [
        'severityFilter' => ['except' => 'all'],
        'eventTypeFilter' => ['except' => 'all'],
        'timeFilter' => ['except' => 'all'],
        'showResolvedOnly' => ['except' => false],
        'showUnresolvedOnly' => ['except' => false],
    ];

    /**
     * Mount the component with default filters.
     */
    public function mount(): void
    {
        // Default to showing unresolved events
        $this->showUnresolvedOnly = true;
    }

    /**
     * Render the security dashboard.
     */
    public function render()
    {
        return view('livewire.admin.security-dashboard', [
            'securityEvents' => $this->getSecurityEvents(),
            'securityMetrics' => $this->getSecurityMetrics(),
            'threatTrends' => $this->getThreatTrends(),
            'topThreats' => $this->getTopThreats(),
            'suspiciousIps' => $this->getSuspiciousIps(),
        ]);
    }

    /**
     * Get filtered security events.
     */
    private function getSecurityEvents()
    {
        $query = SecurityEvent::with(['user', 'resolvedBy'])
            ->orderBy('created_at', 'desc');

        // Apply severity filter
        if ($this->severityFilter !== 'all') {
            $query->where('severity', $this->severityFilter);
        }

        // Apply event type filter
        if ($this->eventTypeFilter !== 'all') {
            $query->where('event_type', $this->eventTypeFilter);
        }

        // Apply time filter
        switch ($this->timeFilter) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->where('created_at', '>=', now()->subWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->subMonth());
                break;
        }

        // Apply resolution filter
        if ($this->showResolvedOnly) {
            $query->where('resolved', true);
        } elseif ($this->showUnresolvedOnly) {
            $query->where('resolved', false);
        }

        return $query->paginate(20);
    }

    /**
     * Get security metrics summary.
     */
    private function getSecurityMetrics(): array
    {
        $today = today();
        $yesterday = today()->subDay();
        $thisWeek = now()->startOfWeek();
        $lastWeek = now()->subWeek()->startOfWeek();

        return [
            'total_events' => SecurityEvent::count(),
            'unresolved_events' => SecurityEvent::unresolved()->count(),
            'critical_events' => SecurityEvent::critical()->unresolved()->count(),
            'high_events' => SecurityEvent::high()->unresolved()->count(),
            'events_today' => SecurityEvent::whereDate('created_at', $today)->count(),
            'events_yesterday' => SecurityEvent::whereDate('created_at', $yesterday)->count(),
            'events_this_week' => SecurityEvent::where('created_at', '>=', $thisWeek)->count(),
            'events_last_week' => SecurityEvent::whereBetween('created_at', [$lastWeek, $thisWeek])->count(),
        ];
    }

    /**
     * Get threat trends over time.
     */
    private function getThreatTrends(): array
    {
        return SecurityEvent::selectRaw('DATE(created_at) as date, COUNT(*) as count, severity')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date', 'severity')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($events) {
                return $events->pluck('count', 'severity')->toArray();
            })
            ->toArray();
    }

    /**
     * Get top threat types.
     */
    private function getTopThreats(): array
    {
        return SecurityEvent::selectRaw('event_type, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('event_type')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get suspicious IP addresses.
     */
    private function getSuspiciousIps(): array
    {
        return SecurityEvent::selectRaw('ip_address, COUNT(*) as event_count, MAX(severity) as max_severity')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('ip_address')
            ->having('event_count', '>', 5)
            ->orderBy('event_count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Mark security event as resolved.
     */
    public function resolveEvent(int $eventId, string $notes = ''): void
    {
        $event = SecurityEvent::findOrFail($eventId);
        $event->markAsResolved(auth()->user(), $notes);

        $this->dispatch('eventResolved', eventId: $eventId);
        session()->flash('success', 'Security event marked as resolved.');
    }

    /**
     * Bulk resolve security events.
     */
    public function bulkResolveEvents(array $eventIds, string $notes = ''): void
    {
        $resolvedCount = 0;
        
        foreach ($eventIds as $eventId) {
            $event = SecurityEvent::find($eventId);
            if ($event && !$event->resolved) {
                $event->markAsResolved(auth()->user(), $notes);
                $resolvedCount++;
            }
        }

        $this->dispatch('bulkEventsResolved', count: $resolvedCount);
        session()->flash('success', "Resolved {$resolvedCount} security events.");
    }

    /**
     * Export security events to CSV.
     */
    public function exportEvents(): string
    {
        $events = $this->getSecurityEvents()->items();
        
        $csvData = [];
        $csvData[] = [
            'Timestamp',
            'Event Type',
            'Severity', 
            'IP Address',
            'User ID',
            'User Agent',
            'Resolved',
            'Resolution Notes'
        ];

        foreach ($events as $event) {
            $csvData[] = [
                $event->created_at->toDateTimeString(),
                $event->event_type_display,
                $event->severity,
                $event->ip_address,
                $event->user_id ?? 'N/A',
                $event->user_agent ?? 'N/A',
                $event->resolved ? 'Yes' : 'No',
                $event->resolution_notes ?? '',
            ];
        }

        $filename = 'security_events_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $filepath = storage_path('app/exports/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $file = fopen($filepath, 'w');
        foreach ($csvData as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        return $filename;
    }

    /**
     * Clear resolved events older than specified days.
     */
    public function clearOldEvents(int $daysOld = 90): void
    {
        $deletedCount = SecurityEvent::where('resolved', true)
            ->where('resolved_at', '<', now()->subDays($daysOld))
            ->delete();

        session()->flash('success', "Cleared {$deletedCount} old security events.");
    }

    /**
     * Get available event types for filter.
     */
    public function getEventTypesProperty(): array
    {
        return SecurityEvent::distinct()
            ->pluck('event_type')
            ->sort()
            ->toArray();
    }

    /**
     * Reset all filters.
     */
    public function resetFilters(): void
    {
        $this->severityFilter = 'all';
        $this->eventTypeFilter = 'all';
        $this->timeFilter = 'all';
        $this->showResolvedOnly = false;
        $this->showUnresolvedOnly = true;
        $this->resetPage();
    }

    /**
     * Updated hook for reactive updates.
     */
    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['severityFilter', 'eventTypeFilter', 'timeFilter', 'showResolvedOnly', 'showUnresolvedOnly'])) {
            $this->resetPage();
        }
    }
}