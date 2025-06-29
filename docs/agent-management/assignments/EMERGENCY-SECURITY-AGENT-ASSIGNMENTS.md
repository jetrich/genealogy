# EMERGENCY SECURITY AGENT ASSIGNMENTS
## Critical Security Remediation - Immediate Deployment

**Priority**: 🚨 **CRITICAL EMERGENCY**  
**Timeline**: 0-24 Hours  
**Coordinator**: Tech Lead Tony  
**Status**: Ready for Immediate Dispatch  

---

## 🎯 **AGENT 1: Mass Assignment Security Agent**
**Mission**: Fix critical mass assignment vulnerability (Task 2.001)  
**Duration**: 10 minutes  
**Priority**: CRITICAL (CVSS 8.5)  
**Agent ID**: security-mass-assignment-agent  

### **Atomic Task Assignment**:
```
2.001.01.01: Remove 'is_developer' from User model fillable array
2.001.01.02: Add 'is_developer' to User model guarded array
2.001.02.01: Create privilege escalation test
2.001.02.02: Verify existing functionality unchanged
2.001.02.03: Test profile update forms work correctly
```

### **Specific Mission Details**:
- **Target File**: `app/Models/User.php:61`
- **Vulnerability**: Mass assignment allowing privilege escalation
- **Fix**: Move 'is_developer' from fillable to guarded array
- **Validation**: Create test ensuring fix works
- **Risk**: ZERO implementation risk, CRITICAL security impact

### **Implementation Code**:
```php
// BEFORE (VULNERABLE):
protected $fillable = [
    'firstname', 'surname', 'email', 'password',
    'language', 'timezone', 
    'is_developer',  // ← REMOVE THIS
    'seen_at',
];

// AFTER (SECURE):
protected $fillable = [
    'firstname', 'surname', 'email', 'password',
    'language', 'timezone', 'seen_at',
];

// ADD EXPLICIT GUARDING:
protected $guarded = ['is_developer'];
```

### **Required Actions**:
1. **Edit app/Models/User.php** - Remove 'is_developer' from fillable
2. **Add guarded property** - Explicitly guard 'is_developer'
3. **Create test** - Verify privilege escalation blocked
4. **Run tests** - Ensure no functionality broken
5. **Document fix** - Update security log

---

## 🎯 **AGENT 2: Dependency Vulnerability Agent**  
**Mission**: Patch critical dependency vulnerabilities (Task 2.002)  
**Duration**: 30 minutes  
**Priority**: CRITICAL (CVSS 9.8)  
**Agent ID**: security-dependency-agent  

### **Atomic Task Assignment**:
```
2.002.01.01: Backup current package-lock.json
2.002.01.02: Update Vite to 6.2.3+ (fixes CVE-2025-30208, CVE-2025-31486, CVE-2025-31125)
2.002.01.03: Update Axios to 1.8.2+ (fixes CVE-2025-27152)
2.002.02.01: Verify versions updated correctly
2.002.02.02: Test Vite development server functionality
2.002.02.03: Test Axios HTTP requests in application
2.002.02.04: Verify build process works correctly
2.002.02.05: Run automated test suite
```

### **Specific Mission Details**:
- **Target Files**: `package.json`, `package-lock.json`
- **Vulnerabilities**: CVE-2025-30208, CVE-2025-31486, CVE-2025-31125 (Vite), CVE-2025-27152 (Axios)
- **Fix**: Update to patched versions
- **Validation**: Comprehensive testing of updated dependencies

### **Implementation Commands**:
```bash
# 1. Backup current package-lock.json
cp package-lock.json package-lock.json.backup

# 2. Update vulnerable packages
npm update vite@latest     # Fix CVE-2025-30208, CVE-2025-31486, CVE-2025-31125
npm update axios@latest    # Fix CVE-2025-27152

# 3. Verify versions
npm list vite axios

# 4. Test application startup
npm run dev
npm run build

# 5. Run test suite
npm test
```

### **Expected Version Targets**:
- **Vite**: 6.2.3+ (fixes all file read vulnerabilities)
- **Axios**: 1.8.2+ (fixes SSRF vulnerability)

### **Rollback Plan**:
```bash
# If issues arise, rollback immediately:
cp package-lock.json.backup package-lock.json
npm ci
```

---

## 🎯 **AGENT 3: Development Server Security Agent**  
**Mission**: Harden development server security (Task 2.003)  
**Duration**: 15 minutes  
**Priority**: HIGH (prevents Vite exploitation)  
**Agent ID**: security-devserver-agent  

### **Atomic Task Assignment**:
```
2.003.01.01: Update package.json dev script to localhost only
2.003.01.02: Add optional dev-network script for network access
2.003.02.01: Configure vite.config.js with security settings
2.003.02.02: Add file system access restrictions
2.003.02.03: Verify dev server localhost-only access
2.003.02.04: Test file access restrictions work
```

### **Specific Mission Details**:
- **Target Files**: `package.json`, `vite.config.js`
- **Vulnerability**: Network-exposed development server
- **Fix**: Restrict to localhost, add file access restrictions
- **Validation**: Test security restrictions work

### **Implementation Changes**:
```json
// package.json - BEFORE:
{
  "scripts": {
    "dev": "vite --host"  // ← DANGEROUS: Exposes to network
  }
}

// package.json - AFTER:
{
  "scripts": {
    "dev": "vite",  // ← SECURE: Localhost only
    "dev-network": "vite --host"  // ← Optional for intentional network access
  }
}
```

```javascript
// vite.config.js - Add security configuration
export default defineConfig({
    server: {
        host: 'localhost',  // Explicit localhost binding
        cors: true,
        fs: {
            strict: true,   // Enable strict file system access
            deny: ['.env', '.env.*', '*.{pem,crt,key}']  // Block sensitive files
        }
    },
    // ... existing config
});
```

---

## 🚀 **DEPLOYMENT STRATEGY**

### **Concurrent Agent Deployment**:
1. **Deploy all 3 agents simultaneously** for maximum efficiency
2. **Each agent operates independently** on atomic tasks
3. **Coordination via shared security log** for progress tracking
4. **Emergency stop protocol** if any agent encounters issues

### **Agent Coordination Protocol**:
- **Mass Assignment Agent**: Works on User model (10 min)
- **Dependency Agent**: Works on package files (30 min)
- **DevServer Agent**: Works on config files (15 min)
- **No conflicts**: All agents work on different files

### **Success Criteria**:
- ✅ Mass assignment vulnerability eliminated
- ✅ Dependency vulnerabilities patched  
- ✅ Development server secured
- ✅ All tests passing
- ✅ No functionality degradation

### **Emergency Protocols**:
- **Immediate rollback** available for each fix
- **Independent operation** - failure of one doesn't affect others
- **Comprehensive testing** before marking complete
- **Tony coordination** for any issues requiring escalation

---

## 📊 **AGENT DISPATCH COMMANDS**

Ready for immediate agent deployment:

```bash
# Agent 1: Mass Assignment Fix
claude --agent security-mass-assignment-agent --task 2.001 --priority critical

# Agent 2: Dependency Patching  
claude --agent security-dependency-agent --task 2.002 --priority critical

# Agent 3: DevServer Hardening
claude --agent security-devserver-agent --task 2.003 --priority high
```

**Status**: ✅ **READY FOR IMMEDIATE DISPATCH**  
**Next Action**: Deploy all 3 agents concurrently for emergency security remediation