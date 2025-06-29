# PHASE 4 SECURITY FRAMEWORK AGENT ASSIGNMENTS
## Comprehensive Security Infrastructure & Monitoring

**Priority**: 🛡️ **SECURITY FRAMEWORK IMPLEMENTATION**  
**Timeline**: 1-4 Weeks  
**Coordinator**: Tech Lead Tony  
**Status**: Ready for Deployment  
**Purpose**: Establish sustainable security infrastructure for long-term protection

---

## 🎯 **AGENT 1: Security Monitoring Architect**
**Mission**: Deploy automated security monitoring and threat detection (Task 4.001)  
**Duration**: 1 week  
**Priority**: MEDIUM (ongoing protection)  
**Agent ID**: security-monitoring-architect  

### **Comprehensive Security Monitoring Deployment**

#### **Phase 1: Automated Vulnerability Scanning (2 days)**
**Task 4.001.01.01**: Create GitHub Actions security scan workflow
**Task 4.001.01.02**: Configure dependency vulnerability scanning  
**Task 4.001.01.03**: Setup automated security advisory checks

**Implementation**:
```yaml
# .github/workflows/security-comprehensive.yml
name: Comprehensive Security Monitoring

on:
  schedule:
    - cron: '0 6 * * 1'  # Weekly Monday 6 AM
    - cron: '0 18 * * *' # Daily 6 PM
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  dependency-security-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP 8.3
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite
          
      - name: Setup Node.js 20
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
          
      - name: Install PHP Dependencies
        run: composer install --no-dev --optimize-autoloader
        
      - name: Install Node Dependencies
        run: npm ci
        
      - name: Run Composer Security Audit
        run: |
          composer audit --no-dev --format=table
          composer audit --no-dev --format=json > composer-audit.json
          
      - name: Run NPM Security Audit
        run: |
          npm audit --audit-level=moderate
          npm audit --json > npm-audit.json
          
      - name: Advanced Security Advisory Check
        run: |
          npx audit-ci --moderate
          
      - name: PHP Security Checker
        run: |
          composer require --dev enlightn/security-checker
          vendor/bin/security-checker security:check composer.lock
          
      - name: Upload Security Reports
        uses: actions/upload-artifact@v3
        with:
          name: security-reports
          path: |
            composer-audit.json
            npm-audit.json

  laravel-security-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Laravel Security Scanner
        run: |
          composer require --dev enlightn/enlightn
          php artisan enlightn --report --ci
          
  static-analysis-security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: PHPStan Security Analysis
        run: |
          composer require --dev phpstan/phpstan
          vendor/bin/phpstan analyse --level=8 app/
          
      - name: Psalm Security Analysis
        run: |
          composer require --dev vimeo/psalm
          vendor/bin/psalm --show-info=true --find-unused-code
```

#### **Phase 2: Runtime Security Monitoring (3 days)**
**Task 4.001.02.01**: Create SecurityMonitoring middleware
**Task 4.001.02.02**: Implement privilege escalation detection
**Task 4.001.02.03**: Implement suspicious file access detection

**Security Monitoring Middleware**:
```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Advanced security monitoring middleware for genealogy application.
 * Detects and prevents various attack patterns in real-time.
 */
class SecurityMonitoring
{
    public function handle(Request $request, Closure $next)
    {
        // Monitor for privilege escalation attempts
        if ($this->detectPrivilegeEscalation($request)) {
            $this->logSecurityEvent('privilege_escalation_attempt', $request, [
                'severity' => 'critical',
                'request_data' => $request->all(),
                'headers' => $request->headers->all(),
            ]);
            
            // Rate limit this IP for suspicious activity
            RateLimiter::hit('security_violation:' . $request->ip(), 3600);
            
            abort(403, 'Security policy violation detected');
        }
        
        // Monitor for suspicious file access patterns
        if ($this->detectSuspiciousFileAccess($request)) {
            $this->logSecurityEvent('suspicious_file_access', $request, [
                'severity' => 'warning',
                'path' => $request->path(),
                'query_params' => $request->query(),
            ]);
        }
        
        // Monitor for genealogy-specific attacks
        if ($this->detectGenealogyAttacks($request)) {
            $this->logSecurityEvent('genealogy_data_attack', $request, [
                'severity' => 'high',
                'attack_type' => 'genealogy_data_extraction',
            ]);
            
            abort(403, 'Genealogy data access violation');
        }
        
        // Monitor for mass data extraction attempts
        if ($this->detectMassDataExtraction($request)) {
            $this->logSecurityEvent('mass_data_extraction', $request, [
                'severity' => 'high',
                'pattern' => 'bulk_download_attempt',
            ]);
            
            // Temporary rate limit
            RateLimiter::hit('bulk_access:' . $request->ip(), 1800);
        }
        
        // Monitor authentication anomalies
        if ($this->detectAuthenticationAnomalies($request)) {
            $this->logSecurityEvent('authentication_anomaly', $request, [
                'severity' => 'medium',
                'anomaly_type' => 'unusual_login_pattern',
            ]);
        }
        
        return $next($request);
    }
    
    /**
     * Detect privilege escalation attempts.
     */
    private function detectPrivilegeEscalation(Request $request): bool
    {
        $data = $request->all();
        $requestBody = json_encode($data);
        
        // Check for attempts to modify developer flag
        if (isset($data['is_developer']) || str_contains($requestBody, 'is_developer')) {
            return true;
        }
        
        // Check for permission manipulation attempts
        $permissionPatterns = [
            'admin.', 'permission', 'role', 'grant', 'revoke'
        ];
        
        foreach ($permissionPatterns as $pattern) {
            if (str_contains($requestBody, $pattern) && !$this->isAuthorizedPermissionRequest($request)) {
                return true;
            }
        }
        
        // Check for SQL injection attempts targeting user roles
        $sqlPatterns = [
            'UPDATE users SET is_developer',
            'INSERT INTO user_permissions',
            'DELETE FROM user_permissions',
        ];
        
        foreach ($sqlPatterns as $pattern) {
            if (str_contains($requestBody, $pattern)) {
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
            '/.env', '/../', '/etc/passwd', '/.git/', '/.htaccess',
            '.pem', '.key', '.crt', '.p12', 'id_rsa', 'authorized_keys',
            '/proc/', '/var/log/', 'wp-config', 'config.php'
        ];
        
        $path = $request->path();
        $fullUrl = $request->fullUrl();
        
        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($path, $pattern) || str_contains($fullUrl, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect genealogy-specific attack patterns.
     */
    private function detectGenealogyAttacks(Request $request): bool
    {
        // Check for attempts to access other teams' data
        if (str_contains($request->path(), '/admin/') && 
            !auth()->check()) {
            return true;
        }
        
        // Check for GEDCOM exploitation attempts
        $gedcomPatterns = [
            'GEDCOM', '0 HEAD', '1 SOUR', '2 VERS',
            '../gedcom', '/gedcom/', 'gedcom.zip'
        ];
        
        $requestData = json_encode($request->all());
        foreach ($gedcomPatterns as $pattern) {
            if (str_contains($requestData, $pattern) && 
                !$request->is('gedcom/*') && 
                !$request->is('admin/gedcom/*')) {
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
        // Check rate limiting for data-heavy endpoints
        $dataEndpoints = [
            'api/people', 'api/couples', 'api/teams', 
            'export', 'download', 'backup'
        ];
        
        foreach ($dataEndpoints as $endpoint) {
            if (str_contains($request->path(), $endpoint)) {
                $key = 'data_access:' . $request->ip() . ':' . $endpoint;
                
                if (RateLimiter::tooManyAttempts($key, 10)) { // 10 requests per hour
                    return true;
                }
                
                RateLimiter::hit($key, 3600);
            }
        }
        
        return false;
    }
    
    /**
     * Detect authentication anomalies.
     */
    private function detectAuthenticationAnomalies(Request $request): bool
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        $currentIp = $request->ip();
        $userAgent = $request->userAgent();
        
        // Check for unusual IP changes
        if ($user->last_login_ip && 
            $user->last_login_ip !== $currentIp && 
            !$this->isKnownIpRange($currentIp, $user->known_ip_ranges ?? [])) {
            return true;
        }
        
        // Check for unusual user agent changes
        if ($user->last_user_agent && 
            $user->last_user_agent !== $userAgent &&
            !str_contains($userAgent, 'bot')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if this is an authorized permission management request.
     */
    private function isAuthorizedPermissionRequest(Request $request): bool
    {
        return $request->is('admin/permissions/*') && 
               auth()->check() && 
               auth()->user()->hasPermission('admin.user_management.edit');
    }
    
    /**
     * Check if IP is in known safe ranges.
     */
    private function isKnownIpRange(string $ip, array $knownRanges): bool
    {
        // Implementation for IP range checking
        foreach ($knownRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Log security events with comprehensive context.
     */
    private function logSecurityEvent(string $eventType, Request $request, array $context = []): void
    {
        $logData = array_merge([
            'event_type' => $eventType,
            'timestamp' => now()->toISOString(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'referer' => $request->header('referer'),
        ], $context);
        
        // Log to security channel
        Log::channel('security')->warning("Security event: {$eventType}", $logData);
        
        // Store in database for analysis
        \DB::table('security_events')->insert([
            'event_type' => $eventType,
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'context' => json_encode($logData),
            'severity' => $context['severity'] ?? 'medium',
            'created_at' => now(),
        ]);
    }
    
    /**
     * Check if IP is within a CIDR range.
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $mask) = explode('/', $range);
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask = -1 << (32 - $mask);
        
        return ($ip_long & $mask) === ($subnet_long & $mask);
    }
}
```

#### **Phase 3: Security Dashboard (2 days)**
**Task 4.001.03.01**: Create SecurityDashboard Livewire component
**Task 4.001.03.02**: Implement security metrics collection
**Task 4.001.03.03**: Create security alerting system

---

## 🎯 **AGENT 2: Security Headers Architect**
**Mission**: Implement comprehensive security headers and input validation (Task 4.002)  
**Duration**: 3-5 days  
**Priority**: MEDIUM (defense in depth)  
**Agent ID**: security-headers-architect  

### **Web Security Headers Implementation**

#### **Phase 1: Security Headers Middleware (2 days)**
**Task 4.002.01.01**: Create SecurityHeaders middleware
**Task 4.002.01.02**: Configure Content Security Policy
**Task 4.002.01.03**: Add X-Frame-Options and security headers

**Security Headers Implementation**:
```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Comprehensive security headers middleware for genealogy application.
 * Implements defense-in-depth security headers to protect against various attacks.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Content Security Policy - Strict genealogy app policy
        $csp = $this->buildContentSecurityPolicy($request);
        $response->headers->set('Content-Security-Policy', $csp);
        
        // Frame protection
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // Content type protection
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // XSS Protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Referrer Policy - Protect genealogy privacy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions Policy - Genealogy app doesn't need these features
        $response->headers->set('Permissions-Policy', 
            'camera=(), microphone=(), geolocation=(), usb=(), bluetooth=()'
        );
        
        // HSTS for HTTPS environments
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 
                'max-age=31536000; includeSubDomains; preload'
            );
        }
        
        // Cross-Origin policies
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        
        // Additional security headers
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('X-Download-Options', 'noopen');
        
        // Remove server information
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');
        
        return $response;
    }
    
    /**
     * Build comprehensive CSP for genealogy application.
     */
    private function buildContentSecurityPolicy(Request $request): string
    {
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https: blob:",
            "media-src 'self' blob:",
            "object-src 'none'",
            "frame-src 'none'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "connect-src 'self' https:",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
        ];
        
        // Development environment adjustments
        if (app()->environment('local')) {
            $csp[] = "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com";
            $csp[] = "connect-src 'self' ws: wss: https:"; // For Vite HMR
        }
        
        return implode('; ', $csp);
    }
}
```

#### **Phase 2: Enhanced Input Validation (2 days)**
**Task 4.002.02.01**: Enhance input validation rules
**Task 4.002.02.02**: Add CSRF protection verification  
**Task 4.002.02.03**: Implement request rate limiting

---

## 🎯 **AGENT 3: Audit Logging Architect**
**Mission**: Comprehensive audit logging and security event tracking (Task 4.003)  
**Duration**: 1 week  
**Priority**: MEDIUM (compliance and forensics)  
**Agent ID**: security-audit-architect  

### **Enterprise Audit Logging System**

#### **Enhanced Security Logging Framework**:
```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

/**
 * Comprehensive security audit logging service for genealogy application.
 * Provides detailed logging for security events, user actions, and system changes.
 */
class SecurityAuditService
{
    /**
     * Log security-sensitive user actions.
     */
    public static function logUserAction(string $action, array $context = []): void
    {
        $auditData = [
            'action' => $action,
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'timestamp' => now()->toISOString(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ];
        
        $fullContext = array_merge($auditData, $context);
        
        // Log to security channel
        Log::channel('security')->info("User action: {$action}", $fullContext);
        
        // Store in activity log
        activity('security')
            ->by(auth()->user())
            ->withProperties($fullContext)
            ->log($action);
    }
    
    /**
     * Log genealogy-specific actions.
     */
    public static function logGenealogyAction(string $action, $subject = null, array $properties = []): void
    {
        $context = [
            'action_type' => 'genealogy_data',
            'team_id' => auth()->user()?->currentTeam?->id,
            'team_name' => auth()->user()?->currentTeam?->name,
        ];
        
        activity('genealogy')
            ->by(auth()->user())
            ->performedOn($subject)
            ->withProperties(array_merge($context, $properties))
            ->log($action);
    }
    
    /**
     * Log administrative actions with enhanced detail.
     */
    public static function logAdminAction(string $action, array $context = []): void
    {
        $enhancedContext = array_merge([
            'admin_action' => true,
            'requires_review' => true,
            'security_level' => 'high',
        ], $context);
        
        self::logUserAction("ADMIN: {$action}", $enhancedContext);
        
        // Also notify security monitoring
        Log::channel('admin')->critical("Administrative action: {$action}", $enhancedContext);
    }
}
```

---

## 🚀 **CONCURRENT DEPLOYMENT STRATEGY**

### **Agent Coordination**:
1. **Security Monitoring**: Deploys runtime protection and vulnerability scanning
2. **Security Headers**: Implements web security headers and input validation  
3. **Audit Logging**: Creates comprehensive logging and compliance framework

### **Integration Points**:
- All agents enhance the existing security architecture
- No conflicts - each focuses on different security layers
- Combined result: Enterprise-grade security framework

### **Success Criteria**:
- ✅ Automated security scanning integrated into CI/CD
- ✅ Runtime security monitoring active
- ✅ Comprehensive web security headers deployed
- ✅ Enterprise audit logging implemented
- ✅ Security dashboard operational
- ✅ **Risk Reduction**: LOW → VERY LOW

**Status**: ✅ **READY FOR CONCURRENT DEPLOYMENT**  
**Timeline**: 1-2 weeks for complete security framework  
**Impact**: Establishes sustainable security program with ongoing protection