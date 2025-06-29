# GEDCOM Import Implementation Plan

**Project**: Laravel Genealogy Application  
**Target**: Complete GEDCOM import functionality  
**Approach**: Methodical, phase-by-phase with focused branches and testing  
**Reference**: Solutions available in `feature/gedcom-import-fixes` backup branch  

## Project Overview

Transform the placeholder GEDCOM import system into a fully functional feature that:
- Parses GEDCOM 5.5 files using PhpGedcom library
- Creates teams with complete genealogy data  
- Maintains security and team isolation
- Provides comprehensive error handling and validation

## Phase-by-Phase Implementation

### 🛡️ Phase 1: Security Foundation (CRITICAL)
**Branch**: `feature/phase1-security-foundation`  
**Duration**: 2-3 hours  
**Priority**: HIGH - Must complete before any import functionality  

#### 1.1 Fix Mass Assignment Vulnerability ⚠️ CRITICAL
- **File**: `app/Models/User.php`
- **Issue**: `is_developer` in fillable array (line 61)
- **Fix**: Remove from fillable, add guarded protection
- **Test**: Verify privilege escalation attempts fail
- **Security Impact**: Prevents CVSS 8.5 vulnerability

#### 1.2 Add Missing Genealogy Permissions
- **File**: `database/seeders/PermissionSeeder.php` (create)
- **Permissions to Add**:
  ```php
  'person:create', 'person:read', 'person:update', 'person:delete',
  'couple:create', 'couple:read', 'couple:update', 'couple:delete', 
  'team:import', 'team:export'
  ```
- **Integration**: Update existing users to have these permissions
- **Test**: Verify GEDCOM import controller access works

#### 1.3 Auto-Grant Permissions for New Users  
- **File**: `app/Actions/Fortify/CreateNewUser.php`
- **Function**: Add `grantDefaultPermissions()` method
- **Purpose**: Prevent 403 errors for new registrations
- **Test**: Register new user, verify GEDCOM access

#### 1.4 Enhanced Team Model Security
- **File**: `app/Models/Team.php`  
- **Fix**: Add `user_id` to fillable for team creation
- **Security**: Ensure proper team ownership validation
- **Test**: Verify team creation works without mass assignment errors

#### Phase 1 Success Criteria
- ✅ No mass assignment vulnerabilities
- ✅ Comprehensive permission system
- ✅ New users can access GEDCOM import
- ✅ Team creation works securely
- ✅ All security tests pass

---

### 🧬 Phase 2: Core PhpGedcom Integration  
**Branch**: `feature/phase2-phpgedcom-core`  
**Duration**: 4-6 hours  
**Priority**: HIGH - Core functionality  

#### 2.1 Implement GEDCOM File Parsing
- **File**: `app/Php/Gedcom/Import.php`
- **Method**: `parseGedcomFile(string $filePath)`
- **Library**: Use PhpGedcom\Parser
- **Error Handling**: Comprehensive validation for malformed files
- **Test**: Parse sample GEDCOM files, verify data extraction

#### 2.2 Add Event Extraction Helper
- **Method**: `findEventByType(array $events, string $eventClass)`
- **Purpose**: Proper PhpGedcom API usage for birth/death events
- **Fix**: Access events via `getEven()` array, not direct methods
- **Test**: Verify birth/death date extraction works

#### 2.3 Implement Date Parsing
- **Method**: `parseGedcomDate(?string $dateString)`
- **Formats**: Handle GEDCOM date formats (ABT, EST, CAL, etc.)
- **Library**: Carbon for date normalization
- **Test**: Parse various GEDCOM date formats

#### 2.4 Add Basic Team Creation Logic
- **Method**: `createTeam()`
- **Integration**: Create team with proper user association
- **Transaction**: Wrap in database transaction for atomicity
- **Test**: Verify team creation from GEDCOM metadata

#### Phase 2 Success Criteria
- ✅ GEDCOM files parse without errors
- ✅ Events extracted correctly using proper API
- ✅ Dates parsed and normalized properly  
- ✅ Teams created with correct ownership
- ✅ Database transactions work atomically

---

### 👥 Phase 3: Person Data Import
**Branch**: `feature/phase3-person-import`  
**Duration**: 3-4 hours  
**Priority**: HIGH - Core data import  

#### 3.1 Implement Individual Person Import
- **Method**: `createPersonFromGedcom(Indi $individual, Team $team)`
- **Data Extraction**:
  - Names (first, surname from PhpGedcom Name records)
  - Birth data (date, place from birth events)
  - Death data (date, place from death events)  
  - Sex/Gender mapping
- **Model**: Create Person records with team association
- **Test**: Import individuals, verify all data fields populated

#### 3.2 Add Import Statistics Tracking
- **Property**: `$importStats` array
- **Metrics**: Count individuals, families, errors
- **Logging**: Comprehensive import progress logging
- **Test**: Verify statistics accuracy during import

#### 3.3 Implement Error Handling for Individuals
- **Strategy**: Continue import on individual failures
- **Logging**: Log individual import failures with context
- **Recovery**: Graceful degradation for missing data
- **Test**: Import GEDCOM with malformed individual records

#### 3.4 Add GEDCOM Person ID Mapping
- **Purpose**: Map GEDCOM IDs to Person model IDs
- **Use**: Enable family relationship creation in Phase 4
- **Structure**: `$gedcomPersonMap` array
- **Test**: Verify ID mapping works for relationship creation

#### Phase 3 Success Criteria
- ✅ Individual persons imported completely
- ✅ Birth/death dates and places preserved
- ✅ Names extracted and stored properly
- ✅ Import statistics tracked accurately
- ✅ Error handling works gracefully
- ✅ GEDCOM ID mapping functional

---

### 👨‍👩‍👧‍👦 Phase 4: Family Relationship Import
**Branch**: `feature/phase4-family-import`  
**Duration**: 3-4 hours  
**Priority**: HIGH - Complete genealogy functionality  

#### 4.1 Implement Family/Couple Import
- **Method**: `createCoupleFromGedcom(Fam $family, Team $team)`
- **Data Extraction**:
  - Husband/Wife GEDCOM ID mapping
  - Marriage events (date, place)
  - Divorce events (date, has_ended flag)
- **Model**: Create Couple records linking Person models
- **Test**: Import families, verify couple relationships

#### 4.2 Add Parent-Child Relationships
- **Method**: `updateParentChildRelationships(PhpGedcom $gedcom)`
- **Process**: Second pass after all individuals imported
- **Data**: Update Person records with father_id, mother_id
- **Test**: Verify family tree hierarchy is correct

#### 4.3 Handle Missing/Incomplete Families
- **Strategy**: Create couples even with missing spouse data
- **Validation**: Require at least one valid spouse
- **Error Handling**: Log and skip invalid family records
- **Test**: Import GEDCOM with incomplete family data

#### 4.4 Add Family Event Processing
- **Events**: Marriage, divorce, separation
- **API**: Use proper PhpGedcom Family event access
- **Helper**: Use `findEventByType()` for family events
- **Test**: Verify marriage/divorce dates imported correctly

#### Phase 4 Success Criteria
- ✅ Family relationships imported completely
- ✅ Marriage/divorce data preserved
- ✅ Parent-child hierarchy established
- ✅ Incomplete families handled gracefully
- ✅ Family events processed correctly
- ✅ Complete family tree structure created

---

### 🔐 Phase 5: Advanced Security Integration
**Branch**: `feature/phase5-security-hardening`  
**Duration**: 2-3 hours  
**Priority**: MEDIUM - Production readiness  

#### 5.1 Implement Security Middleware
- **File**: `app/Http/Middleware/SecurityMonitoring.php`
- **Features**:
  - GEDCOM import operation detection
  - Team management operation allowance
  - Cross-team access prevention
  - Malicious GEDCOM file detection
- **Test**: Verify legitimate operations allowed, attacks blocked

#### 5.2 Add GEDCOM File Validation
- **Validation**: File content security checks
- **Patterns**: Detect PHP, JavaScript, path traversal
- **Size Limits**: Prevent DoS via large files
- **Test**: Upload malicious files, verify rejection

#### 5.3 Implement Rate Limiting
- **Scope**: GEDCOM import operations
- **Limits**: Reasonable limits to prevent abuse
- **Monitoring**: Track and log rate limit violations
- **Test**: Verify rate limiting works correctly

#### 5.4 Add Comprehensive Audit Logging
- **Events**: All GEDCOM import operations
- **Context**: User, team, file details, results
- **Storage**: Database and log files
- **Test**: Verify all operations logged properly

#### Phase 5 Success Criteria
- ✅ Security middleware integrated
- ✅ Malicious files detected and blocked
- ✅ Rate limiting prevents abuse
- ✅ Comprehensive audit trail maintained
- ✅ Team isolation enforced
- ✅ No security vulnerabilities remain

---

### 🔗 Phase 6: UI Integration & Polish
**Branch**: `feature/phase6-ui-integration`  
**Duration**: 1-2 hours  
**Priority**: MEDIUM - User experience  

#### 6.1 Update Livewire Component
- **File**: `app/Livewire/Gedcom/Importteam.php`
- **Change**: Replace "under construction" with actual import call
- **Integration**: Call Import class with proper error handling
- **Redirect**: Navigate to team view after successful import
- **Test**: End-to-end UI functionality

#### 6.2 Add Progress Indication
- **Feature**: Show import progress to user
- **Implementation**: Livewire real-time updates
- **Feedback**: Display statistics during import
- **Test**: Verify progress updates work

#### 6.3 Enhance Error Display
- **UI**: User-friendly error messages
- **Details**: Show import statistics and issues
- **Recovery**: Guidance for fixing failed imports
- **Test**: Import broken GEDCOM, verify error handling

#### Phase 6 Success Criteria
- ✅ UI calls actual import functionality
- ✅ Progress feedback provided to users
- ✅ Error handling user-friendly
- ✅ End-to-end workflow complete

---

## Testing Strategy

### Unit Testing (Each Phase)
- **Framework**: Pest PHP (already configured)
- **Coverage**: 80%+ minimum for new code
- **Focus**: Core import logic, security functions
- **Sample Data**: Real GEDCOM files for testing

### Integration Testing
- **Database**: Full end-to-end with test database
- **Security**: Penetration testing for vulnerabilities
- **Performance**: Large GEDCOM file testing
- **UI**: Browser testing with real workflows

### Quality Assurance  
- **Code Style**: Laravel Pint formatting
- **Type Safety**: PHPStan static analysis
- **Security**: Manual security review
- **Documentation**: Comprehensive code documentation

## Branch Strategy

### Feature Branches
- Each phase gets its own feature branch
- Small, focused commits with descriptive messages
- Pull requests for each phase with proper review
- No direct commits to main branch

### Merge Strategy
- Each phase creates a focused pull request
- Code review required before merge
- All tests must pass before merge
- Squash merge to keep history clean

## Risk Mitigation

### High-Risk Areas
- **PhpGedcom API**: Use backup branch as reference
- **Security Integration**: Test thoroughly before production
- **Database Transactions**: Ensure atomicity for large imports

### Rollback Plan
- Each phase isolated in separate branches
- Database migrations reversible
- Feature flags for gradual rollout
- Backup system before major changes

## Success Metrics

### Functional Metrics
- ✅ GEDCOM files import successfully (95%+ success rate)
- ✅ All genealogy data preserved accurately
- ✅ Family relationships established correctly
- ✅ No data corruption or loss

### Security Metrics  
- ✅ Zero privilege escalation vulnerabilities
- ✅ Complete team data isolation
- ✅ All malicious uploads blocked
- ✅ Comprehensive audit trail

### Performance Metrics
- ✅ Import performance acceptable (<30s for 1000 individuals)
- ✅ Memory usage reasonable (<512MB for large files)
- ✅ Database performance maintained
- ✅ UI responsive during imports

### Quality Metrics
- ✅ 80%+ test coverage
- ✅ Zero critical security issues
- ✅ PSR-12 code standards compliance
- ✅ Comprehensive documentation

---

## Implementation Timeline

| Phase | Duration | Dependencies | Priority |
|-------|----------|--------------|----------|
| Phase 1: Security | 2-3 hours | None | CRITICAL |
| Phase 2: PhpGedcom | 4-6 hours | Phase 1 | HIGH |
| Phase 3: Persons | 3-4 hours | Phase 2 | HIGH |  
| Phase 4: Families | 3-4 hours | Phase 3 | HIGH |
| Phase 5: Security | 2-3 hours | Phase 4 | MEDIUM |
| Phase 6: UI Polish | 1-2 hours | Phase 5 | MEDIUM |

**Total Estimated Time**: 15-22 hours of focused development
**With Testing & QA**: 20-30 hours total
**Recommended Schedule**: 1-2 phases per day with proper testing

---

**Next Action**: Begin Phase 1 - Security Foundation  
**Reference Materials**: `BACKUP_IMPLEMENTATION_NOTES.md` and `feature/gedcom-import-fixes` branch  
**Success Criteria**: Each phase must pass all tests before proceeding to next phase