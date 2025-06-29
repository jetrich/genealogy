# PHASE 3 ARCHITECTURAL SECURITY AGENT ASSIGNMENTS
## Multi-Tenancy & Permission System Redesign

**Priority**: 🏗️ **ARCHITECTURAL SECURITY FIXES**  
**Timeline**: 1-7 Days  
**Coordinator**: Tech Lead Tony  
**Status**: Ready for Deployment  
**Complexity**: HIGH (Core security architecture changes)

---

## 🎯 **AGENT 1: Multi-Tenancy Security Architect**
**Mission**: Fix fundamental multi-tenancy security flaws (Task 3.001)  
**Duration**: 2-3 days  
**Priority**: CRITICAL (CVSS 8.7)  
**Agent ID**: security-multitenant-architect  

### **Critical Security Flaw to Fix**:
```php
// CURRENT VULNERABLE CODE (Person.php:439, Couple.php:151):
if (Auth::guest() || auth()->user()->is_developer) {
    return; // ← Developers bypass ALL team scoping
}
```
**Risk**: Developers can access ALL team data across the entire genealogy application

### **Atomic Task Assignment**:
```
3.001.01.01: Create HasSecureTeamScope trait
3.001.01.02: Implement AdminAccessService with authorization
3.001.01.03: Create administrative middleware
3.001.02.01: Update Person model with secure scope
3.001.02.02: Update Couple model with secure scope
3.001.02.03: Remove vulnerable global scopes
3.001.03.01: Create CrossTeamController for admin access
3.001.03.02: Add audit logging for admin actions
3.001.04.01: Create comprehensive multi-tenancy tests
3.001.04.02: Test developer bypass prevention
3.001.04.03: Test administrative access controls
```

### **Phase 1: Secure Trait Creation (Day 1 - 6 hours)**
**Files to Create**:
- `app/Models/Concerns/HasSecureTeamScope.php`
- `app/Services/AdminAccessService.php`
- `app/Http/Middleware/AdminContextMiddleware.php`

**Security Architecture**:
```php
// NEW SECURE IMPLEMENTATION
trait HasSecureTeamScope
{
    protected static function bootHasSecureTeamScope(): void
    {
        static::addGlobalScope('secure_team', function (Builder $builder) {
            // NO DEVELOPER BYPASSES - Use explicit admin queries instead
            if (Auth::guest()) {
                return;
            }
            
            $user = auth()->user();
            $currentTeam = $user->currentTeam;
            
            if (!$currentTeam) {
                $builder->whereRaw('1 = 0'); // Show nothing
                return;
            }
            
            // SECURE: Always apply team scope
            $builder->where(static::getTableName() . '.team_id', $currentTeam->id);
        });
    }
}
```

### **Phase 2: Model Security Updates (Day 2 - 4 hours)**
**Files to Modify**:
- `app/Models/Person.php` - Replace vulnerable scope
- `app/Models/Couple.php` - Replace vulnerable scope

**Critical Changes**:
1. Remove existing vulnerable `booted()` methods
2. Add `use HasSecureTeamScope;` trait
3. Ensure NO developer bypasses exist

### **Phase 3: Administrative Access (Day 2-3 - 6 hours)**
**Files to Create**:
- `app/Http/Controllers/Admin/CrossTeamController.php`
- Admin routes with proper middleware
- Audit logging for all admin actions

### **Phase 4: Comprehensive Testing (Day 3 - 4 hours)**
**Test Files to Create**:
- `tests/Feature/Security/MultiTenancyTest.php`
- `tests/Feature/Security/AdminAccessTest.php`

**Critical Test Cases**:
- Users cannot access other teams' data
- Developers cannot bypass without explicit permission
- Admin access requires proper authorization
- All actions are properly audited

---

## 🎯 **AGENT 2: Permission System Architect**
**Mission**: Overhaul developer access control system (Task 3.002)  
**Duration**: 3-5 days  
**Priority**: HIGH (prevents future privilege escalation)  
**Agent ID**: security-permission-architect  

### **Current Problem**: 
Binary `is_developer` flag provides all-or-nothing access with no audit trail

### **Atomic Task Assignment**:
```
3.002.01.01: Create permissions database schema
3.002.01.02: Seed default permissions
3.002.01.03: Create Permission and UserPermission models
3.002.02.01: Add hasPermission method to User model
3.002.02.02: Add grantPermission method with audit logging
3.002.02.03: Create permission checking middleware
3.002.03.01: Update UserPolicy with permission checks
3.002.03.02: Replace is_developer checks across codebase
3.002.04.01: Create PermissionManagement Livewire component
3.002.04.02: Create administrative interface for permissions
3.002.05.01: Create permission migration command
3.002.05.02: Migrate existing developers to new system
3.002.06.01: Create permission system tests
3.002.06.02: Test permission granting audit trail
```

### **Phase 1: Permission Infrastructure (Day 1-2 - 8 hours)**
**Database Schema**:
```php
// New migrations to create:
Schema::create('permissions', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('description');
    $table->timestamps();
});

Schema::create('user_permissions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('permission_id')->constrained()->onDelete('cascade');
    $table->timestamp('granted_at');
    $table->foreignId('granted_by')->constrained('users');
    $table->unique(['user_id', 'permission_id']);
});
```

**Default Permissions**:
- `admin.user_management` - Manage users and teams
- `admin.cross_team_access` - Access data across all teams
- `admin.system_logs` - View system logs and debugging info
- `admin.backup_management` - Create and manage backups

### **Phase 2: User Model Enhancement (Day 2-3 - 6 hours)**
**Files to Modify**:
- `app/Models/User.php` - Add permission methods
- Replace all `is_developer` checks with `hasPermission()` calls

**New Methods**:
```php
public function hasPermission(string $permission): bool
public function grantPermission(string $permission, User $grantedBy): void
public function revokePermission(string $permission, User $revokedBy): void
```

### **Phase 3: Policy & Middleware Updates (Day 3-4 - 8 hours)**
**Files to Update**:
- `app/Policies/UserPolicy.php`
- `app/Policies/TeamPolicy.php`
- All controllers using `is_developer` checks

**Replace everywhere**:
```php
// OLD: return $user->is_developer;
// NEW: return $user->hasPermission('admin.user_management');
```

### **Phase 4: Administrative Interface (Day 4-5 - 8 hours)**
**Files to Create**:
- `app/Livewire/Admin/PermissionManagement.php`
- `resources/views/livewire/admin/permission-management.blade.php`
- Routes and navigation updates

### **Phase 5: Migration & Testing (Day 5 - 6 hours)**
**Migration Strategy**:
1. Create command to migrate existing developers
2. Grant all permissions to current developers
3. Comprehensive testing of new system
4. Gradual rollout with fallback plan

---

## 🚀 **DEPLOYMENT STRATEGY**

### **Concurrent Agent Deployment**:
1. **Multi-Tenancy Agent**: Focuses on core security architecture
2. **Permission System Agent**: Builds new authorization framework
3. **Coordination**: Agents work independently but share security objectives

### **Risk Management**:
- **Branching Strategy**: Each agent works on feature branches
- **Testing Requirements**: Comprehensive test coverage before merge
- **Rollback Plans**: Complete rollback procedures documented
- **Staged Deployment**: Test environment → staging → production

### **Success Criteria**:
- ✅ Multi-tenancy properly enforced without developer bypasses
- ✅ Granular permission system implemented
- ✅ Administrative access properly audited  
- ✅ All existing functionality preserved
- ✅ **Risk Reduction**: MEDIUM → LOW

---

## 📊 **AGENT DISPATCH COMMANDS**

Ready for architectural security deployment:

```bash
# Agent 1: Multi-Tenancy Security Redesign
claude --agent security-multitenant-architect --task 3.001 --priority critical --duration 2-3days

# Agent 2: Permission System Overhaul  
claude --agent security-permission-architect --task 3.002 --priority high --duration 3-5days
```

**Timeline**: 1 week for complete architectural security transformation  
**Impact**: Establishes production-ready security framework  
**Status**: ✅ **READY FOR DEPLOYMENT**