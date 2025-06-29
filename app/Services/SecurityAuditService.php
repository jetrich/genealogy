<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\DB;
use App\Models\User;

/**
 * Comprehensive security audit logging service for genealogy application.
 * Provides detailed logging for security events, user actions, and system changes.
 */
class SecurityAuditService
{
    /**
     * Audit event categories for genealogy application.
     */
    public const AUDIT_CATEGORIES = [
        'AUTHENTICATION' => 'authentication',
        'AUTHORIZATION' => 'authorization', 
        'GENEALOGY_DATA' => 'genealogy_data',
        'ADMIN_ACTION' => 'admin_action',
        'SECURITY_EVENT' => 'security_event',
        'SYSTEM_CHANGE' => 'system_change',
        'PERMISSION_CHANGE' => 'permission_change',
        'TEAM_ACCESS' => 'team_access',
        'GEDCOM_OPERATION' => 'gedcom_operation',
        'FILE_OPERATION' => 'file_operation',
    ];

    /**
     * Log security-sensitive user actions with comprehensive context.
     */
    public static function logUserAction(string $action, array $context = []): void
    {
        $auditData = self::buildBaseAuditData($action, $context);
        
        // Enhanced context for user actions
        $userContext = array_merge($auditData, [
            'category' => self::AUDIT_CATEGORIES['AUTHENTICATION'],
            'team_id' => auth()->user()?->currentTeam?->id,
            'team_name' => auth()->user()?->currentTeam?->name,
            'user_permissions' => self::getCurrentUserPermissions(),
            'request_fingerprint' => self::generateRequestFingerprint(),
        ], $context);
        
        // Log to security channel
        Log::channel('security')->info("User action: {$action}", $userContext);
        
        // Store in activity log with genealogy context
        activity('security')
            ->by(auth()->user())
            ->withProperties($userContext)
            ->log($action);
            
        // Store in audit database for compliance
        self::storeAuditRecord($action, self::AUDIT_CATEGORIES['AUTHENTICATION'], $userContext);
    }
    
    /**
     * Log genealogy-specific actions with family context.
     */
    public static function logGenealogyAction(string $action, $subject = null, array $properties = []): void
    {
        $auditData = self::buildBaseAuditData($action, $properties);
        
        $genealogyContext = array_merge($auditData, [
            'category' => self::AUDIT_CATEGORIES['GENEALOGY_DATA'],
            'action_type' => 'genealogy_data_operation',
            'team_id' => auth()->user()?->currentTeam?->id,
            'team_name' => auth()->user()?->currentTeam?->name,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id ?? null,
            'family_tree_context' => self::getFamilyTreeContext($subject),
        ], $properties);
        
        // Log genealogy operations
        Log::channel('genealogy')->info("Genealogy action: {$action}", $genealogyContext);
        
        activity('genealogy')
            ->by(auth()->user())
            ->performedOn($subject)
            ->withProperties($genealogyContext)
            ->log($action);
            
        self::storeAuditRecord($action, self::AUDIT_CATEGORIES['GENEALOGY_DATA'], $genealogyContext);
    }
    
    /**
     * Log administrative actions with enhanced security detail.
     */
    public static function logAdminAction(string $action, array $context = []): void
    {
        $auditData = self::buildBaseAuditData($action, $context);
        
        $adminContext = array_merge($auditData, [
            'category' => self::AUDIT_CATEGORIES['ADMIN_ACTION'],
            'admin_level' => 'high_privilege',
            'requires_review' => true,
            'security_level' => 'critical',
            'admin_permissions' => self::getCurrentUserPermissions(),
            'cross_team_access' => self::isCrossTeamAction(),
        ], $context);
        
        // Critical admin logging
        Log::channel('admin')->critical("Administrative action: {$action}", $adminContext);
        
        activity('admin')
            ->by(auth()->user())
            ->withProperties($adminContext)
            ->log("ADMIN: {$action}");
            
        self::storeAuditRecord($action, self::AUDIT_CATEGORIES['ADMIN_ACTION'], $adminContext);
        
        // Send immediate notification for critical admin actions
        self::notifyAdminAction($action, $adminContext);
    }
    
    /**
     * Log permission changes with detailed tracking.
     */
    public static function logPermissionChange(string $action, User $targetUser, string $permission, User $performedBy, array $context = []): void
    {
        $auditData = self::buildBaseAuditData($action, $context);
        
        $permissionContext = array_merge($auditData, [
            'category' => self::AUDIT_CATEGORIES['PERMISSION_CHANGE'],
            'target_user_id' => $targetUser->id,
            'target_user_email' => $targetUser->email,
            'permission_name' => $permission,
            'performed_by_id' => $performedBy->id,
            'performed_by_email' => $performedBy->email,
            'permission_sensitive' => self::isPermissionSensitive($permission),
            'before_permissions' => self::getUserPermissions($targetUser),
        ], $context);
        
        Log::channel('admin')->warning("Permission change: {$action}", $permissionContext);
        
        activity('permission')
            ->by($performedBy)
            ->performedOn($targetUser)
            ->withProperties($permissionContext)
            ->log("Permission {$action}: {$permission}");
            
        self::storeAuditRecord($action, self::AUDIT_CATEGORIES['PERMISSION_CHANGE'], $permissionContext);
    }
    
    /**
     * Log GEDCOM operations with security analysis.
     */
    public static function logGedcomOperation(string $operation, array $context = []): void
    {
        $auditData = self::buildBaseAuditData($operation, $context);
        
        $gedcomContext = array_merge($auditData, [
            'category' => self::AUDIT_CATEGORIES['GEDCOM_OPERATION'],
            'operation_type' => $operation,
            'file_size' => $context['file_size'] ?? null,
            'file_type' => $context['file_type'] ?? null,
            'records_count' => $context['records_count'] ?? null,
            'security_scan_result' => $context['security_scan_result'] ?? 'not_scanned',
            'team_id' => auth()->user()?->currentTeam?->id,
        ], $context);
        
        Log::channel('gedcom')->info("GEDCOM operation: {$operation}", $gedcomContext);
        
        activity('gedcom')
            ->by(auth()->user())
            ->withProperties($gedcomContext)
            ->log("GEDCOM {$operation}");
            
        self::storeAuditRecord($operation, self::AUDIT_CATEGORIES['GEDCOM_OPERATION'], $gedcomContext);
    }
    
    /**
     * Log file operations with security context.
     */
    public static function logFileOperation(string $operation, array $context = []): void
    {
        $auditData = self::buildBaseAuditData($operation, $context);
        
        $fileContext = array_merge($auditData, [
            'category' => self::AUDIT_CATEGORIES['FILE_OPERATION'],
            'file_operation' => $operation,
            'file_path' => $context['file_path'] ?? null,
            'file_size' => $context['file_size'] ?? null,
            'mime_type' => $context['mime_type'] ?? null,
            'security_scan' => $context['security_scan'] ?? false,
            'virus_scan_result' => $context['virus_scan_result'] ?? 'not_scanned',
        ], $context);
        
        Log::channel('files')->info("File operation: {$operation}", $fileContext);
        
        activity('files')
            ->by(auth()->user())
            ->withProperties($fileContext)
            ->log("File {$operation}");
            
        self::storeAuditRecord($operation, self::AUDIT_CATEGORIES['FILE_OPERATION'], $fileContext);
    }
    
    /**
     * Build base audit data for all logging operations.
     */
    private static function buildBaseAuditData(string $action, array $context = []): array
    {
        return [
            'action' => $action,
            'timestamp' => now()->toISOString(),
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'referer' => request()->header('referer'),
            'x_forwarded_for' => request()->header('x-forwarded-for'),
            'device_fingerprint' => self::generateDeviceFingerprint(),
            'geolocation' => self::getGeolocation(),
            'request_id' => self::generateRequestId(),
        ];
    }
    
    /**
     * Store audit record in dedicated audit table.
     */
    private static function storeAuditRecord(string $action, string $category, array $context): void
    {
        try {
            DB::table('audit_logs')->insert([
                'action' => $action,
                'category' => $category,
                'user_id' => auth()->id(),
                'ip_address' => $context['ip_address'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                'context' => json_encode($context),
                'session_id' => $context['session_id'] ?? null,
                'request_id' => $context['request_id'] ?? null,
                'team_id' => $context['team_id'] ?? null,
                'severity' => self::determineSeverity($category, $action),
                'requires_review' => self::requiresReview($category, $action),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store audit record: ' . $e->getMessage(), [
                'action' => $action,
                'category' => $category,
                'context' => $context
            ]);
        }
    }
    
    /**
     * Get current user permissions for audit context.
     */
    private static function getCurrentUserPermissions(): array
    {
        if (!auth()->check()) {
            return [];
        }
        
        // Check if user has permissions relationship, otherwise return basic info
        if (method_exists(auth()->user(), 'permissions')) {
            return auth()->user()->permissions()
                ->pluck('name')
                ->toArray();
        }
        
        // Fallback for basic user roles
        $user = auth()->user();
        $permissions = [];
        
        if ($user->is_developer ?? false) {
            $permissions[] = 'developer_access';
        }
        
        if ($user->currentTeam) {
            $membership = $user->teamRole($user->currentTeam);
            if ($membership) {
                $permissions[] = 'team_' . strtolower($membership->role);
            }
        }
        
        return $permissions;
    }
    
    /**
     * Get user permissions for specific user.
     */
    private static function getUserPermissions(User $user): array
    {
        // Check if user has permissions relationship, otherwise return basic info
        if (method_exists($user, 'permissions')) {
            return $user->permissions()
                ->pluck('name')
                ->toArray();
        }
        
        // Fallback for basic user roles
        $permissions = [];
        
        if ($user->is_developer ?? false) {
            $permissions[] = 'developer_access';
        }
        
        return $permissions;
    }
    
    /**
     * Get family tree context for genealogy operations.
     */
    private static function getFamilyTreeContext($subject): array
    {
        if (!$subject || !method_exists($subject, 'team')) {
            return [];
        }
        
        return [
            'family_tree_id' => $subject->team_id ?? null,
            'subject_type' => get_class($subject),
            'subject_name' => $subject->name ?? $subject->firstname ?? null,
        ];
    }
    
    /**
     * Check if current action is cross-team access.
     */
    private static function isCrossTeamAction(): bool
    {
        // Check if user is accessing data from multiple teams
        return auth()->check() && 
               request()->header('X-Admin-Context') === 'authorized';
    }
    
    /**
     * Check if permission is sensitive.
     */
    private static function isPermissionSensitive(string $permission): bool
    {
        $sensitivePermissions = [
            'admin.user_management.edit',
            'admin.user_management.delete',
            'admin.cross_team_access',
            'admin.system.backup',
            'admin.developer.database',
        ];
        
        return in_array($permission, $sensitivePermissions);
    }
    
    /**
     * Generate request fingerprint for tracking.
     */
    private static function generateRequestFingerprint(): string
    {
        $data = [
            request()->ip(),
            request()->userAgent(),
            request()->headers->get('accept-language'),
            request()->headers->get('accept-encoding'),
        ];
        
        return hash('sha256', implode('|', array_filter($data)));
    }
    
    /**
     * Generate device fingerprint.
     */
    private static function generateDeviceFingerprint(): string
    {
        $data = [
            request()->userAgent(),
            request()->headers->get('accept'),
            request()->headers->get('accept-language'),
            request()->headers->get('accept-encoding'),
            request()->headers->get('dnt'),
        ];
        
        return hash('md5', implode('|', array_filter($data)));
    }
    
    /**
     * Get geolocation data (if available).
     */
    private static function getGeolocation(): ?array
    {
        // Placeholder for geolocation service integration
        // Could integrate with MaxMind GeoIP or similar service
        return null;
    }
    
    /**
     * Generate unique request ID.
     */
    private static function generateRequestId(): string
    {
        return uniqid('req_', true);
    }
    
    /**
     * Determine severity based on category and action.
     */
    private static function determineSeverity(string $category, string $action): string
    {
        $highSeverityCategories = [
            self::AUDIT_CATEGORIES['ADMIN_ACTION'],
            self::AUDIT_CATEGORIES['PERMISSION_CHANGE'],
            self::AUDIT_CATEGORIES['SECURITY_EVENT'],
        ];
        
        if (in_array($category, $highSeverityCategories)) {
            return 'high';
        }
        
        if (str_contains(strtolower($action), 'delete') || 
            str_contains(strtolower($action), 'destroy')) {
            return 'high';
        }
        
        return 'medium';
    }
    
    /**
     * Determine if action requires manual review.
     */
    private static function requiresReview(string $category, string $action): bool
    {
        $reviewCategories = [
            self::AUDIT_CATEGORIES['ADMIN_ACTION'],
            self::AUDIT_CATEGORIES['PERMISSION_CHANGE'],
        ];
        
        return in_array($category, $reviewCategories);
    }
    
    /**
     * Send notification for critical admin actions.
     */
    private static function notifyAdminAction(string $action, array $context): void
    {
        // Queue notification for admin action
        try {
            \App\Jobs\NotifyAdminAction::dispatch($action, $context);
        } catch (\Exception $e) {
            Log::error('Failed to queue admin action notification: ' . $e->getMessage());
        }
    }
}