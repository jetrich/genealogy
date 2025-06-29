# Permission System Deployment Guide

## Overview

This document outlines the complete deployment of the granular permission system to replace the binary `is_developer` authorization mechanism. The new system provides fine-grained access control with comprehensive audit trails.

## 🚀 Deployment Steps

### 1. Run Database Migration

```bash
# Create the permission system tables
php artisan migrate --force

# Seed the default permissions
php artisan db:seed --class=PermissionSeeder
```

### 2. Migrate Existing Developers

```bash
# Dry run to see what will be migrated
php artisan permissions:migrate-developers --dry-run

# Execute the migration (recommended to run with confirmation)
php artisan permissions:migrate-developers

# Or run without confirmation prompts
php artisan permissions:migrate-developers --force
```

### 3. Update Routes (Optional - Gradual Migration)

Update routes to use the new `permission` middleware instead of `developer`:

```php
// OLD:
Route::middleware(['auth', 'developer'])->group(function () {
    // Routes requiring developer access
});

// NEW:
Route::middleware(['auth', 'permission:admin.user_management.view'])->group(function () {
    // Routes requiring specific permission
});
```

### 4. Add Permission Management Route

Add to your `routes/web.php`:

```php
// Permission Management (for administrators)
Route::middleware(['auth', 'permission:admin.user_management.permissions'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/permissions', App\Livewire\Admin\PermissionManagement::class)
            ->name('admin.permissions');
    });
```

## 🔧 New Permission Categories

### User Management
- `admin.user_management.view` - View user accounts
- `admin.user_management.create` - Create new users
- `admin.user_management.edit` - Edit existing users
- `admin.user_management.delete` - Delete users
- `admin.user_management.permissions` - Grant/revoke permissions

### Team Management
- `admin.team_management.view` - View team information
- `admin.team_management.edit` - Modify teams
- `admin.team_management.delete` - Delete teams

### Cross-Team Access
- `admin.cross_team_access.people` - Access genealogy data across teams
- `admin.cross_team_access.statistics` - View cross-team analytics
- `admin.cross_team_access.couples` - Access couple relationships across teams

### System Administration
- `admin.system.logs` - View system logs
- `admin.system.backup` - Create/manage backups
- `admin.system.settings` - Modify system settings
- `admin.system.maintenance` - Perform maintenance tasks

### Developer Tools
- `admin.developer.database` - Direct database access
- `admin.developer.debug` - Access debugging tools
- `admin.developer.cache` - Manage application cache

### GEDCOM Management
- `admin.gedcom.import_export` - Import/export GEDCOM files
- `admin.gedcom.bulk_operations` - Bulk GEDCOM operations

### Media Management
- `admin.media.cross_team_access` - Access media across teams
- `admin.media.bulk_operations` - Bulk media operations

## 🛡️ Security Features

### 1. Complete Audit Trail
- All permission grants/revokes are logged
- Includes justification requirements
- IP address and user agent tracking
- Activity log integration

### 2. Permission Expiration
- Optional expiration dates for temporary access
- Automatic permission cleanup
- Time-bound administrative access

### 3. Sensitive Permission Marking
- Critical permissions marked as `is_sensitive`
- Additional authorization checks
- Enhanced logging for sensitive operations

### 4. Multi-Factor Authorization
- AdminAccessService requires multiple checks
- Admin context headers for cross-team access
- Justification requirements

## 📊 Administrative Interface

Access the permission management interface at `/admin/permissions` (requires `admin.user_management.permissions` permission).

Features:
- User search and selection
- Permission granting with justification
- Permission revocation tracking
- Real-time permission status
- Legacy developer flag visibility

## 🔄 Migration Verification

### 1. Test Permission System

```bash
# Run the comprehensive test suite
php artisan test tests/Feature/Security/PermissionSystemTest.php
```

### 2. Verify User Access

1. Login as a migrated developer user
2. Verify access to administrative functions
3. Check audit logs for permission usage

### 3. Grant New Permissions

Use the administrative interface to grant specific permissions to users based on their actual needs.

## 🚨 Post-Deployment Tasks

### 1. Review and Customize Permissions

- Review the permissions granted to each user
- Remove unnecessary permissions following principle of least privilege
- Set expiration dates for temporary access

### 2. Update Documentation

- Update user guides with new permission requirements
- Document permission request procedures
- Create role-based permission templates

### 3. Disable Legacy System (Future)

Once fully tested and verified:

```sql
-- Consider setting is_developer to false for migrated users
-- UPDATE users SET is_developer = false WHERE id IN (migrated_user_ids);
```

## 🔍 Monitoring and Maintenance

### 1. Audit Log Review

Regularly review permission audit logs:

```sql
SELECT 
    u.name,
    p.name as permission,
    pal.action,
    pal.performed_at,
    pal.context
FROM permission_audit_log pal
JOIN users u ON pal.user_id = u.id
JOIN permissions p ON pal.permission_id = p.id
ORDER BY pal.performed_at DESC
LIMIT 50;
```

### 2. Permission Usage Monitoring

Monitor permission usage through activity logs:

```sql
SELECT 
    properties->>'$.permission_used' as permission,
    COUNT(*) as usage_count,
    COUNT(DISTINCT causer_id) as unique_users
FROM activity_log 
WHERE log_name = 'permission_usage'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY properties->>'$.permission_used'
ORDER BY usage_count DESC;
```

### 3. Regular Permission Review

- Monthly permission audit
- Remove expired or unused permissions
- Review sensitive permission holders
- Update permissions based on role changes

## 🆘 Troubleshooting

### Permission Not Working

1. Check if permission exists in database
2. Verify user has been granted the permission
3. Check permission expiration date
4. Review middleware configuration

### Legacy Developer Access Issues

The system maintains backward compatibility. Legacy developers will continue to have access through the `is_developer` flag until permissions are fully migrated.

### Migration Command Issues

```bash
# If migration fails, check logs and re-run with dry-run
php artisan permissions:migrate-developers --dry-run

# Check for missing permissions
php artisan db:seed --class=PermissionSeeder --force
```

## 📝 Key Files Created/Modified

### New Files
- `app/Models/Permission.php`
- `app/Http/Middleware/HasPermission.php`
- `app/Livewire/Admin/PermissionManagement.php`
- `app/Console/Commands/MigrateToPermissionSystem.php`
- `database/migrations/2025_06_29_000001_create_permissions_system.php`
- `database/seeders/PermissionSeeder.php`
- `resources/views/livewire/admin/permission-management.blade.php`
- `tests/Feature/Security/PermissionSystemTest.php`

### Modified Files
- `app/Models/User.php` - Added permission methods
- `app/Policies/UserPolicy.php` - Updated with granular permissions
- `app/Services/AdminAccessService.php` - Permission-aware authorization
- `app/Http/Middleware/IsDeveloper.php` - Backward compatibility
- `bootstrap/app.php` - Middleware registration

## ✅ Success Criteria

- [x] Granular permission system implemented
- [x] Complete audit trail for all permission actions
- [x] Administrative interface for permission management
- [x] Migration strategy for existing developers
- [x] Comprehensive test coverage
- [x] Backward compatibility maintained
- [x] Security enhanced with sensitive permission marking

The permission system is now ready for deployment and provides a robust, auditable, and flexible authorization framework for the genealogy application.