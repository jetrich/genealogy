<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Permission;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Carbon\Carbon;

class PermissionSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed permissions
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
    }

    public function test_user_can_be_granted_permission()
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        
        $permission = Permission::where('name', 'admin.user_management.view')->first();
        
        $user->grantPermission('admin.user_management.view', $admin, 'Testing permission grant');
        
        $this->assertTrue($user->hasPermission('admin.user_management.view'));
        
        // Check audit log
        $this->assertDatabaseHas('permission_audit_log', [
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'action' => 'granted',
            'performed_by' => $admin->id,
        ]);
        
        // Check activity log
        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $admin->id,
            'subject_id' => $user->id,
            'description' => "Permission 'admin.user_management.view' granted to user",
        ]);
    }

    public function test_user_can_be_revoked_permission()
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        
        $permission = Permission::where('name', 'admin.system.logs')->first();
        
        // Grant permission first
        $user->grantPermission('admin.system.logs', $admin, 'Initial grant');
        $this->assertTrue($user->hasPermission('admin.system.logs'));
        
        // Revoke permission
        $user->revokePermission('admin.system.logs', $admin, 'Access no longer needed');
        $this->assertFalse($user->hasPermission('admin.system.logs'));
        
        // Check revocation audit log
        $this->assertDatabaseHas('permission_audit_log', [
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'action' => 'revoked',
            'performed_by' => $admin->id,
        ]);
    }

    public function test_permission_expiration_works()
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        
        // Grant permission that expires in the past
        $user->permissions()->attach(
            Permission::where('name', 'admin.system.logs')->first()->id,
            [
                'granted_at' => now()->subDays(2),
                'granted_by' => $admin->id,
                'expires_at' => now()->subHour(),
                'justification' => 'Temporary access for testing',
            ]
        );
        
        // Should not have permission due to expiration
        $this->assertFalse($user->hasPermission('admin.system.logs'));
        
        // Grant permission that expires in the future
        $user->permissions()->detach();
        $user->permissions()->attach(
            Permission::where('name', 'admin.system.logs')->first()->id,
            [
                'granted_at' => now(),
                'granted_by' => $admin->id,
                'expires_at' => now()->addHour(),
                'justification' => 'Temporary access for testing',
            ]
        );
        
        // Should have permission since it hasn't expired
        $this->assertTrue($user->hasPermission('admin.system.logs'));
    }

    public function test_cannot_grant_duplicate_permission()
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        
        $user->grantPermission('admin.user_management.view', $admin, 'Initial grant');
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User already has permission: admin.user_management.view');
        
        $user->grantPermission('admin.user_management.view', $admin, 'Duplicate grant attempt');
    }

    public function test_cannot_revoke_non_existent_permission()
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User does not have permission: admin.user_management.view');
        
        $user->revokePermission('admin.user_management.view', $admin, 'Revoke attempt');
    }

    public function test_has_any_permission_works()
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        
        $user->grantPermission('admin.user_management.view', $admin, 'Testing');
        
        $this->assertTrue($user->hasAnyPermission([
            'admin.user_management.view',
            'admin.system.logs'
        ]));
        
        $this->assertFalse($user->hasAnyPermission([
            'admin.system.logs',
            'admin.developer.database'
        ]));
    }

    public function test_has_all_permissions_works()
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        
        $user->grantPermission('admin.user_management.view', $admin, 'Testing');
        $user->grantPermission('admin.system.logs', $admin, 'Testing');
        
        $this->assertTrue($user->hasAllPermissions([
            'admin.user_management.view',
            'admin.system.logs'
        ]));
        
        $this->assertFalse($user->hasAllPermissions([
            'admin.user_management.view',
            'admin.system.logs',
            'admin.developer.database'
        ]));
    }

    public function test_legacy_developer_access_still_works()
    {
        $user = User::factory()->create(['is_developer' => true]);
        
        // Legacy developer should still have access via hasLegacyDeveloperAccess
        $this->assertTrue($user->hasLegacyDeveloperAccess());
        
        // Regular user should not
        $regularUser = User::factory()->create(['is_developer' => false]);
        $this->assertFalse($regularUser->hasLegacyDeveloperAccess());
        
        // User with developer permission should have access
        $admin = User::factory()->create();
        $regularUser->grantPermission('admin.developer.database', $admin, 'Testing');
        $this->assertTrue($regularUser->hasLegacyDeveloperAccess());
    }

    public function test_user_policy_respects_permissions()
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        
        // Grant view permission to user
        $user->grantPermission('admin.user_management.view', $admin, 'Testing');
        
        $this->actingAs($user);
        
        // User should be able to view users (policy check)
        $response = $this->get('/back/developer/users');
        $this->assertEquals(200, $response->status());
    }

    public function test_user_without_permissions_denied_access()
    {
        $user = User::factory()->create(['is_developer' => false]);
        
        $this->actingAs($user);
        
        // User without permissions should be denied
        $response = $this->get('/back/developer/users');
        $this->assertEquals(403, $response->status());
    }

    public function test_permission_middleware_works()
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        
        // Grant specific permission
        $user->grantPermission('admin.user_management.view', $admin, 'Testing middleware');
        
        $this->actingAs($user);
        
        // Create a test route with permission middleware
        \Route::get('/test-permission', function () {
            return 'success';
        })->middleware('auth', 'permission:admin.user_management.view');
        
        $response = $this->get('/test-permission');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('success', $response->getContent());
    }

    public function test_permission_middleware_denies_without_permission()
    {
        $user = User::factory()->create(['is_developer' => false]);
        
        $this->actingAs($user);
        
        // Create a test route with permission middleware
        \Route::get('/test-permission-denied', function () {
            return 'success';
        })->middleware('auth', 'permission:admin.user_management.view');
        
        $response = $this->get('/test-permission-denied');
        $this->assertEquals(403, $response->status());
    }

    public function test_sensitive_permissions_are_marked_correctly()
    {
        $sensitivePermissions = Permission::where('is_sensitive', true)->pluck('name');
        
        // Verify that critical permissions are marked as sensitive
        $expectedSensitive = [
            'admin.user_management.create',
            'admin.user_management.edit',
            'admin.user_management.delete',
            'admin.cross_team_access.people',
            'admin.developer.database',
        ];
        
        foreach ($expectedSensitive as $permission) {
            $this->assertContains($permission, $sensitivePermissions->toArray(), 
                "Permission '{$permission}' should be marked as sensitive");
        }
    }

    public function test_permission_categories_exist()
    {
        $categories = Permission::distinct()->pluck('category');
        
        $expectedCategories = [
            'user_management',
            'team_management',
            'cross_team_access',
            'system',
            'developer',
            'gedcom',
            'media',
        ];
        
        foreach ($expectedCategories as $category) {
            $this->assertContains($category, $categories->toArray(), 
                "Category '{$category}' should exist in permissions");
        }
    }

    public function test_migration_command_works()
    {
        // Create a developer user
        $developer = User::factory()->create(['is_developer' => true]);
        
        // Run migration command in dry-run mode
        $this->artisan('permissions:migrate-developers', ['--dry-run' => true])
             ->assertExitCode(0);
        
        // User should still not have permissions after dry-run
        $this->assertFalse($developer->hasPermission('admin.user_management.view'));
        
        // Run actual migration
        $this->artisan('permissions:migrate-developers', ['--force' => true])
             ->assertExitCode(0);
        
        // Refresh the user model
        $developer->refresh();
        
        // User should now have permissions
        $this->assertTrue($developer->hasPermission('admin.user_management.view'));
        $this->assertTrue($developer->hasPermission('admin.developer.database'));
        $this->assertTrue($developer->hasPermission('admin.cross_team_access.people'));
    }
}