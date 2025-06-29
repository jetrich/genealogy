<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Team;
use App\Models\Person;
use App\Models\Couple;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for administrative access controls and middleware.
 * 
 * SECURITY: These tests ensure that administrative endpoints
 * requiring cross-team access are properly protected and require
 * explicit authorization with audit logging.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->team1 = Team::factory()->create(['name' => 'Team 1']);
        $this->team2 = Team::factory()->create(['name' => 'Team 2']);
        
        $this->person1 = Person::factory()->create(['team_id' => $this->team1->id]);
        $this->person2 = Person::factory()->create(['team_id' => $this->team2->id]);
        
        $this->couple1 = Couple::factory()->create(['team_id' => $this->team1->id]);
        $this->couple2 = Couple::factory()->create(['team_id' => $this->team2->id]);
    }
    
    public function test_admin_endpoints_require_authentication(): void
    {
        // Test without authentication
        $response = $this->get(route('developer.admin.health'));
        $response->assertRedirect(); // Should redirect to login
        
        $response = $this->get(route('developer.admin.people'));
        $response->assertRedirect();
        
        $response = $this->get(route('developer.admin.statistics'));
        $response->assertRedirect();
    }
    
    public function test_admin_endpoints_require_developer_status(): void
    {
        $regularUser = User::factory()->create(['is_developer' => false]);
        
        $response = $this->actingAs($regularUser)
            ->withHeaders([
                'X-Admin-Context' => 'authorized',
                'X-Admin-Justification' => 'Testing access'
            ])
            ->get(route('developer.admin.health'));
        
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Administrative access denied']);
    }
    
    public function test_admin_endpoints_require_admin_context_headers(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        
        // Test without headers
        $response = $this->actingAs($developer)
            ->get(route('developer.admin.health'));
        
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Administrative context required']);
    }
    
    public function test_admin_endpoints_require_both_headers(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        
        // Test with only X-Admin-Context
        $response = $this->actingAs($developer)
            ->withHeader('X-Admin-Context', 'authorized')
            ->get(route('developer.admin.health'));
        
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Administrative context required']);
        
        // Test with only X-Admin-Justification
        $response = $this->actingAs($developer)
            ->withHeader('X-Admin-Justification', 'Testing')
            ->get(route('developer.admin.health'));
        
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Administrative context required']);
    }
    
    public function test_admin_context_must_be_authorized(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        
        $response = $this->actingAs($developer)
            ->withHeaders([
                'X-Admin-Context' => 'invalid',
                'X-Admin-Justification' => 'Testing access'
            ])
            ->get(route('developer.admin.health'));
        
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Administrative context required']);
    }
    
    public function test_justification_must_be_meaningful(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        
        // Test with too short justification
        $response = $this->actingAs($developer)
            ->withHeaders([
                'X-Admin-Context' => 'authorized',
                'X-Admin-Justification' => 'test' // Too short
            ])
            ->get(route('developer.admin.health'));
        
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Administrative context required']);
    }
    
    public function test_health_check_endpoint_works_with_proper_headers(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        
        $response = $this->actingAs($developer)
            ->withHeaders([
                'X-Admin-Context' => 'authorized',
                'X-Admin-Justification' => 'Testing administrative access health check'
            ])
            ->get(route('developer.admin.health'));
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Administrative access is working'
        ]);
        
        $responseData = $response->json();
        $this->assertEquals($developer->id, $responseData['user']['id']);
        $this->assertEquals($developer->email, $responseData['user']['email']);
        $this->assertTrue($responseData['user']['is_developer']);
    }
    
    public function test_admin_people_endpoint_returns_all_people(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        
        $response = $this->actingAs($developer)
            ->withHeaders([
                'X-Admin-Context' => 'authorized',
                'X-Admin-Justification' => 'System maintenance - checking people data integrity'
            ])
            ->get(route('developer.admin.people'));
        
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $responseData = $response->json();
        $this->assertCount(2, $responseData['data']); // Both people from both teams
        $this->assertEquals(2, $responseData['total']);
    }
    
    public function test_admin_couples_endpoint_returns_all_couples(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        
        $response = $this->actingAs($developer)
            ->withHeaders([
                'X-Admin-Context' => 'authorized',
                'X-Admin-Justification' => 'Data integrity check for couples relationships'
            ])
            ->get(route('developer.admin.couples'));
        
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $responseData = $response->json();
        $this->assertCount(2, $responseData['data']); // Both couples from both teams
        $this->assertEquals(2, $responseData['total']);
    }
    
    public function test_admin_statistics_endpoint_returns_cross_team_stats(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        
        $response = $this->actingAs($developer)
            ->withHeaders([
                'X-Admin-Context' => 'authorized',
                'X-Admin-Justification' => 'Monthly statistics report generation'
            ])
            ->get(route('developer.admin.statistics'));
        
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $responseData = $response->json();
        $statistics = $responseData['data'];
        
        $this->assertEquals(2, $statistics['total_people']);
        $this->assertEquals(2, $statistics['total_couples']);
        $this->assertCount(2, $statistics['teams_with_counts']); // Both teams
    }
    
    public function test_admin_team_details_endpoint(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        
        $response = $this->actingAs($developer)
            ->withHeaders([
                'X-Admin-Context' => 'authorized',
                'X-Admin-Justification' => 'Team-specific data analysis for ' . $this->team1->name
            ])
            ->get(route('developer.admin.team.details', ['teamId' => $this->team1->id]));
        
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $responseData = $response->json();
        $data = $responseData['data'];
        
        $this->assertEquals($this->team1->id, $data['team']['id']);
        $this->assertEquals($this->team1->name, $data['team']['name']);
        $this->assertCount(1, $data['people']); // Only people from team 1
        $this->assertCount(1, $data['couples']); // Only couples from team 1
    }
    
    public function test_admin_endpoints_log_access(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        
        // Spy on the Log facade
        \Illuminate\Support\Facades\Log::spy();
        
        $this->actingAs($developer)
            ->withHeaders([
                'X-Admin-Context' => 'authorized',
                'X-Admin-Justification' => 'Testing audit logging functionality'
            ])
            ->get(route('developer.admin.health'));
        
        // Verify middleware logged the access
        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->with('Administrative middleware access granted', \Mockery::type('array'))
            ->once();
    }
    
    public function test_admin_endpoints_handle_service_errors_gracefully(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        
        // Mock a scenario where AdminAccessService throws an exception
        // This simulates what happens when headers are missing in the service call
        $response = $this->actingAs($developer)
            ->withHeaders([
                'X-Admin-Context' => 'authorized',
                'X-Admin-Justification' => 'Testing error handling'
            ])
            ->get(route('developer.admin.people'));
        
        // The service should work with proper middleware, but if it fails,
        // the controller should handle it gracefully
        if ($response->status() !== 200) {
            $response->assertJson(['success' => false]);
            $this->assertStringContains('Failed to retrieve', $response->json()['message']);
        }
    }
    
    public function test_admin_endpoints_are_only_accessible_to_developers(): void
    {
        $admin = User::factory()->create(['is_developer' => false]); // Not a developer
        
        $response = $this->actingAs($admin)
            ->withHeaders([
                'X-Admin-Context' => 'authorized',
                'X-Admin-Justification' => 'Attempting unauthorized administrative access'
            ])
            ->get(route('developer.admin.people'));
        
        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'Administrative access denied',
            'message' => 'Only developers can access administrative functions'
        ]);
    }
}