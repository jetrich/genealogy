<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;

/**
 * Advanced security monitoring for genealogy application.
 * Monitors and prevents real-time security threats.
 */
class SecurityMonitoring
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        // Pre-request security checks
        $this->performPreRequestChecks($request);
        
        $response = $next($request);
        
        // Post-request security analysis
        $this->performPostRequestAnalysis($request, $response, $startTime);
        
        return $response;
    }
    
    /**
     * Perform security checks before processing request.
     */
    private function performPreRequestChecks(Request $request): void
    {
        // 1. Privilege escalation detection
        if ($this->detectPrivilegeEscalation($request)) {
            $this->logSecurityThreat('privilege_escalation_attempt', $request, [
                'severity' => 'critical',
                'action' => 'blocked',
                'request_data' => $this->sanitizeRequestData($request->all()),
            ]);
            
            RateLimiter::hit('security_violation:' . $request->ip(), 3600);
            abort(403, 'Security policy violation: Privilege escalation attempt detected');
        }
        
        // 2. Suspicious file access detection
        if ($this->detectSuspiciousFileAccess($request)) {
            $this->logSecurityThreat('suspicious_file_access', $request, [
                'severity' => 'high',
                'path' => $request->path(),
                'query_params' => $request->query(),
            ]);
        }
        
        // 3. Genealogy data attack detection
        if ($this->detectGenealogyDataAttack($request)) {
            $this->logSecurityThreat('genealogy_data_attack', $request, [
                'severity' => 'high',
                'attack_type' => 'unauthorized_family_data_access',
            ]);
            
            abort(403, 'Access violation: Unauthorized genealogy data access attempt');
        }
        
        // 4. Mass data extraction prevention
        if ($this->detectMassDataExtraction($request)) {
            $this->logSecurityThreat('mass_data_extraction', $request, [
                'severity' => 'medium',
                'pattern' => 'bulk_download_attempt',
            ]);
            
            RateLimiter::hit('bulk_access:' . $request->ip(), 1800);
        }
        
        // 5. GEDCOM exploitation detection
        if ($this->detectGedcomExploitation($request)) {
            $this->logSecurityThreat('gedcom_exploitation', $request, [
                'severity' => 'high',
                'exploit_type' => 'malicious_gedcom_upload',
            ]);
            
            abort(403, 'GEDCOM security violation detected');
        }
    }
    
    /**
     * Analyze request/response for security insights.
     */
    private function performPostRequestAnalysis(Request $request, $response, float $startTime): void
    {
        $executionTime = microtime(true) - $startTime;
        
        // Log unusually slow requests (potential DoS)
        if ($executionTime > 5.0) {
            $this->logSecurityThreat('slow_request_detected', $request, [
                'severity' => 'medium',
                'execution_time' => $executionTime,
                'potential_dos' => true,
            ]);
        }
        
        // Monitor failed authentication attempts
        if ($response->getStatusCode() === 401 || $response->getStatusCode() === 403) {
            $this->trackFailedAccess($request);
        }
    }
    
    /**
     * Detect privilege escalation attempts.
     */
    private function detectPrivilegeEscalation(Request $request): bool
    {
        $requestData = json_encode($request->all());
        
        // Check for direct developer flag manipulation
        if (str_contains($requestData, 'is_developer') && 
            !$this->isAuthorizedAdminRequest($request)) {
            return true;
        }
        
        // Check for permission system manipulation
        $permissionPatterns = [
            'admin.', 'permission', 'grant', 'revoke',
            'user_permissions', 'permissions.name'
        ];
        
        foreach ($permissionPatterns as $pattern) {
            if (str_contains($requestData, $pattern) && 
                !$this->isAuthorizedPermissionRequest($request)) {
                return true;
            }
        }
        
        // Check for SQL injection targeting privileges
        $sqlPatterns = [
            'UPDATE users SET is_developer',
            'INSERT INTO user_permissions',
            'DELETE FROM user_permissions',
            'OR 1=1', 'UNION SELECT', 'DROP TABLE'
        ];
        
        foreach ($sqlPatterns as $pattern) {
            if (stripos($requestData, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect suspicious file access patterns.
     */
    private function detectSuspiciousFileAccess(Request $request): bool
    {
        $suspiciousPatterns = [
            // System files
            '/.env', '/.git/', '/.htaccess', '/etc/passwd', '/etc/shadow',
            // Credentials and keys
            '.pem', '.key', '.crt', '.p12', 'id_rsa', 'authorized_keys',
            // System directories
            '/proc/', '/var/log/', '/tmp/', '/etc/',
            // Application configs
            'wp-config', 'config.php', 'settings.php',
            // Path traversal
            '../', '..\\', '%2e%2e%2f', '%2e%2e%5c'
        ];
        
        $path = $request->path();
        $fullUrl = $request->fullUrl();
        $query = $request->getQueryString();
        
        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($path, $pattern) || 
                str_contains($fullUrl, $pattern) ||
                ($query && str_contains($query, $pattern))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect genealogy-specific attacks.
     */
    private function detectGenealogyDataAttack(Request $request): bool
    {
        // Check unauthorized admin access
        if (str_contains($request->path(), '/admin/') && !auth()->check()) {
            return true;
        }
        
        // Skip security checks for GEDCOM import operations
        if ($this->isGedcomImportOperation($request)) {
            return false;
        }
        
        // Check cross-team data access attempts
        if (auth()->check() && $this->detectCrossTeamAccess($request)) {
            return true;
        }
        
        // Check for team_id manipulation
        $requestData = $request->all();
        if (isset($requestData['team_id']) && auth()->check()) {
            $userTeamId = auth()->user()->currentTeam?->id;
            if ($userTeamId && $requestData['team_id'] != $userTeamId) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect mass data extraction attempts.
     */
    private function detectMassDataExtraction(Request $request): bool
    {
        $dataEndpoints = [
            'api/people', 'api/couples', 'api/teams', 'api/users',
            'export', 'download', 'backup', 'gedcom'
        ];
        
        foreach ($dataEndpoints as $endpoint) {
            if (str_contains($request->path(), $endpoint)) {
                $key = 'data_access:' . $request->ip() . ':' . $endpoint;
                
                // Allow 5 requests per 10 minutes for data endpoints
                if (RateLimiter::tooManyAttempts($key, 5)) {
                    return true;
                }
                
                RateLimiter::hit($key, 600); // 10 minutes
            }
        }
        
        return false;
    }
    
    /**
     * Detect GEDCOM exploitation attempts.
     */
    private function detectGedcomExploitation(Request $request): bool
    {
        // Check for malicious GEDCOM patterns
        $gedcomPatterns = [
            'GEDCOM', '0 HEAD', '1 SOUR', '2 VERS',
            '../gedcom', '/gedcom/', 'gedcom.zip',
            'file://', 'http://', 'ftp://'
        ];
        
        $requestData = json_encode($request->all());
        
        foreach ($gedcomPatterns as $pattern) {
            if (str_contains($requestData, $pattern) && 
                !$request->is('gedcom/*') && 
                !$request->is('admin/gedcom/*')) {
                return true;
            }
        }
        
        // Check uploaded files for malicious content
        if ($request->hasFile('gedcom')) {
            $file = $request->file('gedcom');
            $content = file_get_contents($file->getRealPath());
            
            if (str_contains($content, '<?php') || 
                str_contains($content, '<script>') ||
                str_contains($content, 'javascript:')) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if request is a legitimate GEDCOM import operation.
     */
    private function isGedcomImportOperation(Request $request): bool
    {
        // GEDCOM import paths
        $gedcomPaths = [
            'gedcom/importteam',
            'livewire/message/gedcom.importteam',
        ];
        
        foreach ($gedcomPaths as $path) {
            if (str_contains($request->path(), $path)) {
                return true;
            }
        }
        
        // Check for Livewire GEDCOM component updates
        if ($request->hasHeader('X-Livewire') && 
            str_contains($request->getContent(), 'importteam')) {
            return true;
        }
        
        return false;
    }

    /**
     * Detect cross-team data access attempts.
     */
    private function detectCrossTeamAccess(Request $request): bool
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        $userTeamId = $user->currentTeam?->id;
        
        // Skip if user has no current team (new users, GEDCOM imports)
        if (!$userTeamId) {
            return false;
        }
        
        // Check URL parameters for team manipulation
        $urlSegments = $request->segments();
        foreach ($urlSegments as $segment) {
            if (is_numeric($segment) && $segment != $userTeamId) {
                // This might be an attempt to access another team's data
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Track failed access attempts for analysis.
     */
    private function trackFailedAccess(Request $request): void
    {
        $key = 'failed_access:' . $request->ip();
        $attempts = RateLimiter::attempts($key);
        
        RateLimiter::hit($key, 3600); // Track for 1 hour
        
        if ($attempts > 10) { // More than 10 failed attempts
            $this->logSecurityThreat('multiple_failed_access', $request, [
                'severity' => 'high',
                'failed_attempts' => $attempts,
                'time_window' => '1_hour',
            ]);
        }
    }
    
    /**
     * Check if request is authorized admin operation.
     */
    private function isAuthorizedAdminRequest(Request $request): bool
    {
        return auth()->check() && 
               auth()->user()->hasPermission('admin.user_management.edit') &&
               $request->is('admin/*');
    }
    
    /**
     * Check if request is authorized permission operation.
     */
    private function isAuthorizedPermissionRequest(Request $request): bool
    {
        return auth()->check() &&
               auth()->user()->hasPermission('admin.user_management.edit') &&
               $request->is('admin/permissions/*');
    }
    
    /**
     * Sanitize request data for secure logging.
     */
    private function sanitizeRequestData(array $data): array
    {
        $sensitiveFields = [
            'password', 'password_confirmation', 'token', 'secret', 
            'key', 'api_key', 'private_key', 'access_token'
        ];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }
        
        return $data;
    }
    
    /**
     * Log security threats with comprehensive context.
     */
    private function logSecurityThreat(string $threatType, Request $request, array $context = []): void
    {
        $logData = array_merge([
            'threat_type' => $threatType,
            'timestamp' => now()->toISOString(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'session_id' => session()->getId(),
            'referer' => $request->header('referer'),
            'x_forwarded_for' => $request->header('x-forwarded-for'),
        ], $context);
        
        // Log to security channel
        Log::channel('security')->warning("Security threat detected: {$threatType}", $logData);
        
        // Store in database for analysis
        $this->storeSecurityEvent($threatType, $logData);
        
        // Send critical alerts for high severity threats
        if (isset($context['severity']) && $context['severity'] === 'critical') {
            $this->sendCriticalAlert($threatType, $logData);
        }
    }
    
    /**
     * Store security event in database.
     */
    private function storeSecurityEvent(string $threatType, array $logData): void
    {
        try {
            DB::table('security_events')->insert([
                'event_type' => $threatType,
                'user_id' => auth()->id(),
                'ip_address' => $logData['ip_address'],
                'user_agent' => $logData['user_agent'],
                'context' => json_encode($logData),
                'severity' => $logData['severity'] ?? 'medium',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Fail gracefully - don't break the request
            Log::error('Failed to store security event: ' . $e->getMessage(), [
                'threat_type' => $threatType,
                'original_context' => $logData
            ]);
        }
    }
    
    /**
     * Send critical security alerts.
     */
    private function sendCriticalAlert(string $threatType, array $context): void
    {
        // Queue critical alert notification
        try {
            // Using Laravel's built-in notification system
            Log::critical("CRITICAL SECURITY ALERT: {$threatType}", $context);
        } catch (\Exception $e) {
            Log::error('Failed to send security alert: ' . $e->getMessage());
        }
    }
}