# Comprehensive Audit Logging Framework

## Overview

The genealogy application now includes a comprehensive enterprise-grade audit logging and security event tracking system designed for compliance, forensics, and security monitoring.

## Core Components

### 1. SecurityAuditService (`app/Services/SecurityAuditService.php`)

The central service for all audit logging operations with 10 specialized audit categories:

- **AUTHENTICATION**: User login, logout, password changes
- **AUTHORIZATION**: Permission grants, role changes, access denials
- **GENEALOGY_DATA**: Person/couple creation, updates, deletions
- **ADMIN_ACTION**: Administrative operations requiring review
- **SECURITY_EVENT**: Suspicious activities, security violations
- **SYSTEM_CHANGE**: Configuration changes, system updates
- **PERMISSION_CHANGE**: User permission modifications
- **TEAM_ACCESS**: Multi-tenant access tracking
- **GEDCOM_OPERATION**: Family tree import/export operations
- **FILE_OPERATION**: File uploads, downloads, deletions

### 2. Database Schema (`database/migrations/2025_06_29_000003_create_audit_logs_table.php`)

Comprehensive audit log table with:
- Core audit information (action, category, request_id)
- User context (user_id, email, session_id)
- Request context (IP, user agent, URL, method)
- Genealogy context (team_id, subject_type, subject_id)
- Security metadata (device fingerprinting, suspicious activity flags)
- Compliance features (retention policies, review workflow)
- Performance indexes for querying and compliance reporting

### 3. Enhanced Logging Channels (`config/logging.php`)

Dedicated logging channels with retention policies:
- **Security**: 30 days retention for security events
- **Admin**: 90 days retention for administrative actions
- **Genealogy**: 365 days retention for genealogy operations
- **GEDCOM**: 90 days retention for import/export operations
- **Files**: 60 days retention for file operations
- **Audit**: 2555 days (7 years) retention for compliance

### 4. Custom Log Formatters

- **AuditLogFormatter**: Compliance-focused formatting with 7-year retention metadata
- **SecurityLogFormatter**: Enhanced security context with threat correlation
- **AdminLogFormatter**: Privilege level tracking and escalation metadata

## Advanced Features

### Request Fingerprinting and Device Tracking

The system generates unique fingerprints for:
- **Request Fingerprinting**: SHA256 hash of IP, user agent, and headers
- **Device Fingerprinting**: MD5 hash of browser characteristics
- **Session Correlation**: Links related events across user sessions

### Automatic Model Observation

The `SecurityAuditObserver` automatically logs:
- User model changes (email, password, developer flag changes)
- Person model operations (creation, updates, deletions)
- Couple model operations (relationship creation/deletion)

### Performance and Security Monitoring

The `AuditTrailMiddleware` provides:
- Automatic request/response logging
- Performance monitoring (execution time, memory usage)
- Slow query detection (>5 seconds)
- Error response tracking (4xx/5xx status codes)
- Route-specific filtering to avoid log noise

## Usage Examples

### Log User Authentication Events

```php
use App\Services\SecurityAuditService;

// Log successful login
SecurityAuditService::logUserAction('user_login', [
    'login_method' => 'email_password',
    'two_factor_enabled' => true,
]);

// Log failed login attempt
SecurityAuditService::logUserAction('login_failed', [
    'failure_reason' => 'invalid_credentials',
    'attempt_count' => 3,
]);
```

### Log Genealogy Data Operations

```php
// Log person creation
SecurityAuditService::logGenealogyAction('person_created', $person, [
    'creation_method' => 'manual_entry',
    'data_source' => 'user_input',
]);

// Log genealogy data export
SecurityAuditService::logGedcomOperation('gedcom_export', [
    'file_size' => 1024000,
    'records_count' => 500,
    'export_format' => 'gedcom_5.5',
]);
```

### Log Administrative Actions

```php
// Log user permission changes
SecurityAuditService::logPermissionChange(
    'permission_granted',
    $targetUser,
    'admin.user_management.edit',
    $adminUser,
    ['permission_level' => 'high_privilege']
);

// Log system configuration changes
SecurityAuditService::logAdminAction('system_backup_created', [
    'backup_size' => 5000000,
    'backup_type' => 'full_database',
    'scheduled' => false,
]);
```

### Log File Operations

```php
// Log file upload with security scanning
SecurityAuditService::logFileOperation('file_upload', [
    'file_path' => 'uploads/photos/family-photo.jpg',
    'file_size' => 2048000,
    'mime_type' => 'image/jpeg',
    'virus_scan_result' => 'clean',
    'security_scan' => true,
]);
```

## Audit Dashboard

The `AuditDashboard` Livewire component (`app/Livewire/Admin/AuditDashboard.php`) provides:

### Real-time Statistics
- Total events in selected date range
- Security events count
- Administrative actions count
- High-severity events count
- Unreviewed events requiring attention
- Suspicious activity flags
- Unique users and IP addresses

### Advanced Filtering
- Search across actions, users, and IPs
- Filter by audit category
- Filter by severity level
- Date range selection
- User-specific filtering
- IP address filtering
- Unreviewed events only
- Suspicious activity only

### Review Workflow
- Mark events as reviewed with notes
- Flag events as suspicious
- Bulk operations for efficiency
- Reviewer tracking and accountability

## Security and Compliance Features

### Compliance Readiness
- **7-year retention**: Audit logs retained for compliance requirements
- **Immutable logging**: Audit records cannot be modified once created
- **Full-text search**: Advanced search capabilities for investigations
- **Export functionality**: Compliance reporting and data export
- **Review workflow**: Manual review process for critical actions

### Forensic Capabilities
- **Request correlation**: Link related events across sessions
- **Device fingerprinting**: Track users across devices and sessions
- **Geolocation ready**: Placeholder for IP geolocation services
- **Timeline reconstruction**: Chronological event reconstruction
- **Cross-reference analysis**: Correlate events across different categories

### Performance Optimization
- **Strategic indexing**: Database indexes optimized for common queries
- **Efficient storage**: JSON context storage for flexible data
- **Retention policies**: Automatic cleanup of expired logs
- **Query optimization**: Scoped queries for large datasets

## Security Headers and CSP Integration

The audit system integrates with:
- Content Security Policy (CSP) violation reporting
- Security headers monitoring
- Rate limiting and abuse detection
- Cross-team access monitoring
- Privilege escalation detection

## Future Enhancements

### Planned Integrations
- **MaxMind GeoIP**: IP geolocation for security analysis
- **Elastic Stack**: Advanced log aggregation and analysis
- **SIEM Integration**: Security Information and Event Management
- **Real-time Alerts**: Webhook notifications for critical events
- **Machine Learning**: Anomaly detection and threat analysis

### Compliance Extensions
- **GDPR Support**: Data subject rights and privacy controls
- **SOX Compliance**: Financial data audit requirements
- **HIPAA Ready**: Healthcare privacy framework support
- **ISO 27001**: Information security management standards

## Installation and Configuration

### 1. Database Migration
```bash
php artisan migrate
```

### 2. Observer Registration
Add to `AppServiceProvider::boot()`:
```php
use App\Models\User;
use App\Models\Person;
use App\Models\Couple;
use App\Observers\SecurityAuditObserver;

User::observe(SecurityAuditObserver::class);
Person::observe(SecurityAuditObserver::class);
Couple::observe(SecurityAuditObserver::class);
```

### 3. Middleware Registration
Add to `app/Http/Kernel.php`:
```php
protected $middleware = [
    // ... other middleware
    \App\Http\Middleware\AuditTrailMiddleware::class,
];
```

### 4. Queue Configuration
Ensure queue processing for admin notifications:
```bash
php artisan queue:work
```

## Monitoring and Maintenance

### Log File Locations
- Security: `storage/logs/security-YYYY-MM-DD.log`
- Admin: `storage/logs/admin-YYYY-MM-DD.log`
- Genealogy: `storage/logs/genealogy-YYYY-MM-DD.log`
- GEDCOM: `storage/logs/gedcom-YYYY-MM-DD.log`
- Files: `storage/logs/files-YYYY-MM-DD.log`
- Audit: `storage/logs/audit-YYYY-MM-DD.log`

### Regular Maintenance Tasks
1. **Review unreviewed events**: Daily review of high-severity events
2. **Monitor suspicious activity**: Weekly analysis of flagged events
3. **Performance analysis**: Monthly review of slow queries and requests
4. **Retention cleanup**: Automated cleanup based on retention policies
5. **Export compliance data**: Quarterly exports for compliance reporting

## Best Practices

### Development Guidelines
1. **Log security-sensitive operations**: Always log authentication, authorization, and data changes
2. **Use appropriate severity levels**: Critical for security breaches, high for admin actions
3. **Include relevant context**: Add meaningful context for forensic analysis
4. **Avoid logging sensitive data**: Never log passwords, tokens, or personal data
5. **Use structured logging**: Consistent JSON structure for machine parsing

### Operational Guidelines
1. **Regular review cycles**: Establish review schedules for different severity levels
2. **Incident response integration**: Link audit logs to incident response procedures
3. **Access control**: Restrict audit log access to authorized personnel only
4. **Backup and recovery**: Include audit logs in backup and disaster recovery plans
5. **Compliance documentation**: Maintain documentation for audit and compliance purposes

This comprehensive audit logging framework provides enterprise-grade security monitoring, compliance readiness, and forensic capabilities for the genealogy application.