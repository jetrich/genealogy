<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Comprehensive audit dashboard for security monitoring and compliance.
 */
class AuditDashboard extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $category = '';
    public string $severity = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $userId = '';
    public string $ipAddress = '';
    public bool $onlyUnreviewed = false;
    public bool $onlySuspicious = false;

    // View settings
    public int $perPage = 25;
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    // Statistics
    public array $stats = [];

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->loadStatistics();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedSeverity(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
        $this->loadStatistics();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
        $this->loadStatistics();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'category', 'severity', 'userId', 'ipAddress', 'onlyUnreviewed', 'onlySuspicious']);
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function markAsReviewed(int $auditLogId, string $notes = ''): void
    {
        $auditLog = AuditLog::find($auditLogId);
        if ($auditLog && $auditLog->requires_review && !$auditLog->reviewed) {
            $auditLog->markAsReviewed(auth()->user(), $notes);
            $this->dispatch('audit-reviewed', ['id' => $auditLogId]);
        }
    }

    public function flagAsSuspicious(int $auditLogId): void
    {
        $auditLog = AuditLog::find($auditLogId);
        if ($auditLog) {
            $auditLog->flagAsSuspicious();
            $this->dispatch('audit-flagged', ['id' => $auditLogId]);
        }
    }

    private function loadStatistics(): void
    {
        $dateFrom = $this->dateFrom ? Carbon::parse($this->dateFrom) : now()->subDays(7);
        $dateTo = $this->dateTo ? Carbon::parse($this->dateTo)->endOfDay() : now()->endOfDay();

        $this->stats = [
            'total_events' => AuditLog::dateRange($dateFrom, $dateTo)->count(),
            'security_events' => AuditLog::securityEvents()->dateRange($dateFrom, $dateTo)->count(),
            'admin_actions' => AuditLog::adminActions()->dateRange($dateFrom, $dateTo)->count(),
            'high_severity' => AuditLog::highSeverity()->dateRange($dateFrom, $dateTo)->count(),
            'unreviewed' => AuditLog::unreviewed()->dateRange($dateFrom, $dateTo)->count(),
            'suspicious' => AuditLog::suspicious()->dateRange($dateFrom, $dateTo)->count(),
            'unique_users' => AuditLog::dateRange($dateFrom, $dateTo)->distinct('user_id')->count('user_id'),
            'unique_ips' => AuditLog::dateRange($dateFrom, $dateTo)->distinct('ip_address')->count('ip_address'),
        ];
    }

    private function getAuditLogsQuery(): Builder
    {
        $query = AuditLog::with(['user', 'team', 'reviewer']);

        // Apply filters
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('action', 'like', '%' . $this->search . '%')
                  ->orWhere('user_email', 'like', '%' . $this->search . '%')
                  ->orWhere('ip_address', 'like', '%' . $this->search . '%')
                  ->orWhereJsonContains('context->action', $this->search);
            });
        }

        if ($this->category) {
            $query->where('category', $this->category);
        }

        if ($this->severity) {
            $query->where('severity', $this->severity);
        }

        if ($this->dateFrom) {
            $query->where('created_at', '>=', Carbon::parse($this->dateFrom));
        }

        if ($this->dateTo) {
            $query->where('created_at', '<=', Carbon::parse($this->dateTo)->endOfDay());
        }

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        if ($this->ipAddress) {
            $query->where('ip_address', 'like', '%' . $this->ipAddress . '%');
        }

        if ($this->onlyUnreviewed) {
            $query->unreviewed();
        }

        if ($this->onlySuspicious) {
            $query->suspicious();
        }

        return $query->orderBy($this->sortBy, $this->sortDirection);
    }

    public function render()
    {
        $auditLogs = $this->getAuditLogsQuery()->paginate($this->perPage);
        
        $categories = [
            'authentication' => 'Authentication',
            'authorization' => 'Authorization',
            'genealogy_data' => 'Genealogy Data',
            'admin_action' => 'Admin Action',
            'security_event' => 'Security Event',
            'system_change' => 'System Change',
            'permission_change' => 'Permission Change',
            'team_access' => 'Team Access',
            'gedcom_operation' => 'GEDCOM Operation',
            'file_operation' => 'File Operation',
        ];

        $severities = [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ];

        $users = User::select('id', 'name', 'email')->orderBy('name')->get();

        return view('livewire.admin.audit-dashboard', [
            'auditLogs' => $auditLogs,
            'categories' => $categories,
            'severities' => $severities,
            'users' => $users,
        ]);
    }
}