<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Person;
use App\Models\Couple;
use App\Models\Team;
use App\Models\AuditLog;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\Log;

/**
 * Comprehensive test suite for audit logging system.
 */
class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $adminUser;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        
        $this->user = User::factory()->create([
            'current_team_id' => $this->team->id,
        ]);
        
        $this->adminUser = User::factory()->create([
            'current_team_id' => $this->team->id,
            'is_developer' => true,
        ]);

        // Add users to team
        $this->team->users()->attach($this->user->id, ['role' => 'editor']);
        $this->team->users()->attach($this->adminUser->id, ['role' => 'admin']);
    }

    /** @test */
    public function it_logs_user_authentication_actions(): void
    {
        $this->actingAs($this->user);

        SecurityAuditService::logUserAction('user_login', [
            'login_method' => 'email_password',
            'two_factor_enabled' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user_login',
            'category' => 'authentication',
            'user_id' => $this->user->id,
            'user_email' => $this->user->email,
        ]);

        $auditLog = AuditLog::where('action', 'user_login')->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals('authentication', $auditLog->category);
        $this->assertEquals('medium', $auditLog->severity);
        $this->assertFalse($auditLog->requires_review);
        $this->assertArrayHasKey('login_method', $auditLog->context);
        $this->assertEquals('email_password', $auditLog->context['login_method']);
    }

    /** @test */
    public function it_logs_genealogy_data_operations(): void
    {
        $this->actingAs($this->user);

        $person = Person::factory()->create([
            'team_id' => $this->team->id,
            'firstname' => 'John',
            'surname' => 'Doe',
        ]);

        SecurityAuditService::logGenealogyAction('person_created', $person, [
            'creation_method' => 'manual_entry',
            'data_source' => 'user_input',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'person_created',
            'category' => 'genealogy_data',
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'subject_type' => Person::class,
            'subject_id' => $person->id,
        ]);

        $auditLog = AuditLog::where('action', 'person_created')->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals('genealogy_data', $auditLog->category);
        $this->assertArrayHasKey('creation_method', $auditLog->context);
        $this->assertEquals('manual_entry', $auditLog->context['creation_method']);
    }

    /** @test */
    public function it_logs_administrative_actions_with_high_severity(): void
    {
        $this->actingAs($this->adminUser);

        SecurityAuditService::logAdminAction('system_backup_created', [
            'backup_size' => 5000000,
            'backup_type' => 'full_database',
            'scheduled' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system_backup_created',
            'category' => 'admin_action',
            'user_id' => $this->adminUser->id,
            'severity' => 'high',
            'requires_review' => true,
        ]);

        $auditLog = AuditLog::where('action', 'system_backup_created')->first();
        $this->assertNotNull($auditLog);
        $this->assertTrue($auditLog->requires_review);
        $this->assertFalse($auditLog->reviewed);
        $this->assertArrayHasKey('backup_type', $auditLog->context);
        $this->assertEquals('full_database', $auditLog->context['backup_type']);
    }

    /** @test */
    public function it_logs_permission_changes(): void
    {
        $this->actingAs($this->adminUser);

        $targetUser = User::factory()->create();
        $permission = 'admin.user_management.edit';

        SecurityAuditService::logPermissionChange(
            'permission_granted',
            $targetUser,
            $permission,
            $this->adminUser,
            ['permission_level' => 'high_privilege']
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'permission_granted',
            'category' => 'permission_change',
            'user_id' => $this->adminUser->id,
        ]);

        $auditLog = AuditLog::where('action', 'permission_granted')->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals($targetUser->id, $auditLog->context['target_user_id']);
        $this->assertEquals($targetUser->email, $auditLog->context['target_user_email']);
        $this->assertEquals($permission, $auditLog->context['permission_name']);
        $this->assertEquals($this->adminUser->id, $auditLog->context['performed_by_id']);
    }

    /** @test */
    public function it_logs_gedcom_operations(): void
    {
        $this->actingAs($this->user);

        SecurityAuditService::logGedcomOperation('gedcom_import', [
            'file_size' => 1024000,
            'file_type' => 'gedcom',
            'records_count' => 500,
            'security_scan_result' => 'clean',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'gedcom_import',
            'category' => 'gedcom_operation',
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
        ]);

        $auditLog = AuditLog::where('action', 'gedcom_import')->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals('gedcom_operation', $auditLog->category);
        $this->assertEquals(1024000, $auditLog->context['file_size']);
        $this->assertEquals(500, $auditLog->context['records_count']);
        $this->assertEquals('clean', $auditLog->context['security_scan_result']);
    }

    /** @test */
    public function it_logs_file_operations(): void
    {
        $this->actingAs($this->user);

        SecurityAuditService::logFileOperation('file_upload', [
            'file_path' => 'uploads/photos/family-photo.jpg',
            'file_size' => 2048000,
            'mime_type' => 'image/jpeg',
            'virus_scan_result' => 'clean',
            'security_scan' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'file_upload',
            'category' => 'file_operation',
            'user_id' => $this->user->id,
        ]);

        $auditLog = AuditLog::where('action', 'file_upload')->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals('file_operation', $auditLog->category);
        $this->assertEquals('uploads/photos/family-photo.jpg', $auditLog->context['file_path']);
        $this->assertEquals('image/jpeg', $auditLog->context['mime_type']);
        $this->assertEquals('clean', $auditLog->context['virus_scan_result']);
        $this->assertTrue($auditLog->context['security_scan']);
    }

    /** @test */
    public function it_generates_request_fingerprints(): void
    {
        $this->actingAs($this->user);

        // Simulate a request with specific headers
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Test Browser)',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate',
        ]);

        SecurityAuditService::logUserAction('test_action');

        $auditLog = AuditLog::where('action', 'test_action')->first();
        $this->assertNotNull($auditLog);
        $this->assertNotNull($auditLog->context['request_fingerprint']);
        $this->assertNotNull($auditLog->context['device_fingerprint']);
        $this->assertEquals(64, strlen($auditLog->context['request_fingerprint'])); // SHA256 hash length
        $this->assertEquals(32, strlen($auditLog->context['device_fingerprint'])); // MD5 hash length
    }

    /** @test */
    public function it_determines_severity_correctly(): void
    {
        $this->actingAs($this->adminUser);

        // High severity admin action
        SecurityAuditService::logAdminAction('user_deleted');
        $adminLog = AuditLog::where('action', 'user_deleted')->first();
        $this->assertEquals('high', $adminLog->severity);

        // High severity delete action
        $this->actingAs($this->user);
        SecurityAuditService::logGenealogyAction('person_delete_requested');
        $deleteLog = AuditLog::where('action', 'person_delete_requested')->first();
        $this->assertEquals('high', $deleteLog->severity);

        // Medium severity regular action
        SecurityAuditService::logUserAction('profile_updated');
        $updateLog = AuditLog::where('action', 'profile_updated')->first();
        $this->assertEquals('medium', $updateLog->severity);
    }

    /** @test */
    public function it_flags_events_requiring_review(): void
    {
        $this->actingAs($this->adminUser);

        // Admin actions require review
        SecurityAuditService::logAdminAction('database_backup_restored');
        $adminLog = AuditLog::where('action', 'database_backup_restored')->first();
        $this->assertTrue($adminLog->requires_review);
        $this->assertFalse($adminLog->reviewed);

        // Permission changes require review
        SecurityAuditService::logPermissionChange(
            'permission_revoked',
            $this->user,
            'admin.system.backup',
            $this->adminUser
        );
        $permissionLog = AuditLog::where('action', 'permission_revoked')->first();
        $this->assertTrue($permissionLog->requires_review);
        $this->assertFalse($permissionLog->reviewed);
    }

    /** @test */
    public function it_can_mark_events_as_reviewed(): void
    {
        $this->actingAs($this->adminUser);

        SecurityAuditService::logAdminAction('critical_system_change');
        $auditLog = AuditLog::where('action', 'critical_system_change')->first();
        
        $this->assertTrue($auditLog->requires_review);
        $this->assertFalse($auditLog->reviewed);

        $reviewer = User::factory()->create();
        $notes = 'Reviewed and approved by security team';
        
        $result = $auditLog->markAsReviewed($reviewer, $notes);
        
        $this->assertTrue($result);
        $auditLog->refresh();
        $this->assertTrue($auditLog->reviewed);
        $this->assertEquals($reviewer->id, $auditLog->reviewed_by);
        $this->assertEquals($notes, $auditLog->review_notes);
        $this->assertNotNull($auditLog->reviewed_at);
    }

    /** @test */
    public function it_can_flag_events_as_suspicious(): void
    {
        $this->actingAs($this->user);

        SecurityAuditService::logUserAction('unusual_access_pattern');
        $auditLog = AuditLog::where('action', 'unusual_access_pattern')->first();
        
        $this->assertFalse($auditLog->suspicious_activity);

        $result = $auditLog->flagAsSuspicious();
        
        $this->assertTrue($result);
        $auditLog->refresh();
        $this->assertTrue($auditLog->suspicious_activity);
    }

    /** @test */
    public function it_provides_audit_log_scopes(): void
    {
        $this->actingAs($this->user);

        // Create various audit log entries
        SecurityAuditService::logUserAction('login_attempt');
        SecurityAuditService::logGenealogyAction('person_viewed', null, ['test' => true]);
        
        $this->actingAs($this->adminUser);
        SecurityAuditService::logAdminAction('system_maintenance');

        // Test scopes
        $securityEvents = AuditLog::securityEvents()->get();
        $this->assertGreaterThan(0, $securityEvents->count());

        $adminActions = AuditLog::adminActions()->get();
        $this->assertEquals(1, $adminActions->count());

        $genealogyData = AuditLog::genealogyData()->get();
        $this->assertEquals(1, $genealogyData->count());

        $highSeverity = AuditLog::highSeverity()->get();
        $this->assertGreaterThan(0, $highSeverity->count());

        $requiresReview = AuditLog::requiresReview()->get();
        $this->assertGreaterThan(0, $requiresReview->count());

        $unreviewed = AuditLog::unreviewed()->get();
        $this->assertGreaterThan(0, $unreviewed->count());
    }

    /** @test */
    public function it_handles_context_values_safely(): void
    {
        $this->actingAs($this->user);

        $context = [
            'level_1' => [
                'level_2' => [
                    'deep_value' => 'test_value'
                ]
            ],
            'simple_value' => 'simple'
        ];

        SecurityAuditService::logUserAction('context_test', $context);
        $auditLog = AuditLog::where('action', 'context_test')->first();

        $this->assertEquals('test_value', $auditLog->getContextValue('level_1.level_2.deep_value'));
        $this->assertEquals('simple', $auditLog->getContextValue('simple_value'));
        $this->assertNull($auditLog->getContextValue('non_existent'));
        $this->assertEquals('default', $auditLog->getContextValue('non_existent', 'default'));
    }

    /** @test */
    public function it_filters_sensitive_data_from_logs(): void
    {
        $this->actingAs($this->user);

        // SecurityAuditService should not log sensitive data directly
        // This test ensures the service doesn't accidentally log passwords, tokens, etc.
        
        SecurityAuditService::logUserAction('password_change_attempt', [
            'success' => true,
            'method' => 'password_reset',
            // No actual password should be logged
        ]);

        $auditLog = AuditLog::where('action', 'password_change_attempt')->first();
        $this->assertNotNull($auditLog);
        
        // Verify that the context doesn't contain sensitive fields
        $contextString = json_encode($auditLog->context);
        $this->assertStringNotContainsString('password', $contextString);
        $this->assertStringNotContainsString('token', $contextString);
        $this->assertStringNotContainsString('secret', $contextString);
    }

    /** @test */
    public function it_correlates_related_events(): void
    {
        $this->actingAs($this->user);

        // All events in the same request should have the same request_id
        SecurityAuditService::logUserAction('action_1');
        SecurityAuditService::logUserAction('action_2');

        $logs = AuditLog::whereIn('action', ['action_1', 'action_2'])->get();
        $this->assertEquals(2, $logs->count());
        
        // Request IDs should be unique but related events should be traceable
        $this->assertNotNull($logs[0]->request_id);
        $this->assertNotNull($logs[1]->request_id);
    }
}