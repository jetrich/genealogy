<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Team;
use App\Models\Person;
use App\Models\Couple;
use App\Services\AdminAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive multi-tenancy security tests.
 * 
 * SECURITY: These tests ensure that the genealogy application properly
 * isolates family tree data between different teams and prevents
 * unauthorized cross-team access.
 * 
 * Tests cover:
 * - Team isolation for regular users
 * - Developer bypass prevention
 * - Administrative access controls
 * - Data leak prevention
 */
class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test teams
        $this->smithFamily = Team::factory()->create(['name' => 'Smith Family']);
        $this->johnsonFamily = Team::factory()->create(['name' => 'Johnson Family']);
        $this->brownFamily = Team::factory()->create(['name' => 'Brown Family']);
        
        // Create test people in different teams
        $this->smithPerson = Person::factory()->create([
            'firstname' => 'John',
            'surname' => 'Smith',
            'team_id' => $this->smithFamily->id
        ]);
        
        $this->johnsonPerson = Person::factory()->create([
            'firstname' => 'Jane',
            'surname' => 'Johnson',
            'team_id' => $this->johnsonFamily->id
        ]);
        
        $this->brownPerson = Person::factory()->create([
            'firstname' => 'Bob',
            'surname' => 'Brown',
            'team_id' => $this->brownFamily->id
        ]);
        
        // Create test couples in different teams
        $this->smithCouple = Couple::factory()->create([
            'person1_id' => $this->smithPerson->id,
            'person2_id' => Person::factory()->create(['team_id' => $this->smithFamily->id])->id,
            'team_id' => $this->smithFamily->id
        ]);
        
        $this->johnsonCouple = Couple::factory()->create([
            'person1_id' => $this->johnsonPerson->id,
            'person2_id' => Person::factory()->create(['team_id' => $this->johnsonFamily->id])->id,
            'team_id' => $this->johnsonFamily->id
        ]);
    }
    
    public function test_users_can_only_access_their_current_team_people(): void
    {
        $user = User::factory()->create();
        $user->teams()->attach($this->smithFamily);
        $user->switchTeam($this->smithFamily);
        
        $this->actingAs($user);
        
        // Should only see people from Smith family
        $people = Person::all();
        $this->assertCount(2, $people); // Smith person + their partner
        
        // Should not see Johnson or Brown family people
        $peopleIds = $people->pluck('id')->toArray();
        $this->assertNotContains($this->johnsonPerson->id, $peopleIds);
        $this->assertNotContains($this->brownPerson->id, $peopleIds);
    }
    
    public function test_users_can_only_access_their_current_team_couples(): void
    {
        $user = User::factory()->create();
        $user->teams()->attach($this->smithFamily);
        $user->switchTeam($this->smithFamily);
        
        $this->actingAs($user);
        
        // Should only see couples from Smith family
        $couples = Couple::all();
        $this->assertCount(1, $couples);
        $this->assertEquals($this->smithCouple->id, $couples->first()->id);
        
        // Should not see Johnson family couples
        $coupleIds = $couples->pluck('id')->toArray();
        $this->assertNotContains($this->johnsonCouple->id, $coupleIds);
    }
    
    public function test_developers_cannot_bypass_team_scoping(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        $developer->teams()->attach($this->smithFamily);
        $developer->switchTeam($this->smithFamily);
        
        $this->actingAs($developer);
        
        // Developer should still only see their team's data
        $people = Person::all();
        $this->assertCount(2, $people); // Only Smith family people
        
        $couples = Couple::all();
        $this->assertCount(1, $couples); // Only Smith family couples
        
        // Should NOT see other teams' data
        $peopleIds = $people->pluck('id')->toArray();
        $this->assertNotContains($this->johnsonPerson->id, $peopleIds);
        $this->assertNotContains($this->brownPerson->id, $peopleIds);
    }
    
    public function test_developers_without_team_context_see_no_data(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        // Don't attach to any team or set current team
        
        $this->actingAs($developer);
        
        // Should see NO data without team context
        $people = Person::all();
        $this->assertCount(0, $people);
        
        $couples = Couple::all();
        $this->assertCount(0, $couples);
    }
    
    public function test_users_switching_teams_see_different_data(): void
    {
        $user = User::factory()->create();
        $user->teams()->attach([$this->smithFamily->id, $this->johnsonFamily->id]);
        
        $this->actingAs($user);
        
        // Switch to Smith family
        $user->switchTeam($this->smithFamily);
        $smithPeople = Person::all();
        $this->assertCount(2, $smithPeople);
        
        // Switch to Johnson family
        $user->switchTeam($this->johnsonFamily);
        $johnsonPeople = Person::all();
        $this->assertCount(2, $johnsonPeople); // Johnson person + their partner
        
        // Data should be completely different
        $smithIds = $smithPeople->pluck('id')->toArray();
        $johnsonIds = $johnsonPeople->pluck('id')->toArray();
        $this->assertEmpty(array_intersect($smithIds, $johnsonIds));
    }
    
    public function test_admin_access_service_requires_authorization(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        $this->actingAs($developer);
        
        // Should fail without admin context headers
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unauthorized administrative access attempt');
        
        AdminAccessService::getAllPeople();
    }
    
    public function test_admin_access_service_works_with_proper_authorization(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        $this->actingAs($developer);
        
        // Mock the request with proper headers
        request()->headers->set('X-Admin-Context', 'authorized');
        request()->headers->set('X-Admin-Justification', 'Testing cross-team access for security validation');
        
        // Should now work and return all people across teams
        $allPeople = AdminAccessService::getAllPeople();
        $this->assertCount(4, $allPeople); // All people from all teams
        
        // Should include people from all teams
        $peopleIds = $allPeople->pluck('id')->toArray();
        $this->assertContains($this->smithPerson->id, $peopleIds);
        $this->assertContains($this->johnsonPerson->id, $peopleIds);
        $this->assertContains($this->brownPerson->id, $peopleIds);
    }
    
    public function test_admin_access_service_logs_access(): void
    {
        $developer = User::factory()->create(['is_developer' => true]);
        $this->actingAs($developer);
        
        // Mock the request with proper headers
        request()->headers->set('X-Admin-Context', 'authorized');
        request()->headers->set('X-Admin-Justification', 'Security audit access');
        
        // Capture log output
        \Illuminate\Support\Facades\Log::spy();
        
        AdminAccessService::getAllPeople();
        
        // Verify access was logged
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->with('Administrative cross-team access executed', \Mockery::type('array'))
            ->once();
    }
    
    public function test_non_developers_cannot_use_admin_access_service(): void
    {
        $regularUser = User::factory()->create(['is_developer' => false]);
        $regularUser->teams()->attach($this->smithFamily);
        $regularUser->switchTeam($this->smithFamily);
        
        $this->actingAs($regularUser);
        
        // Mock the request with proper headers (shouldn't matter)
        request()->headers->set('X-Admin-Context', 'authorized');
        request()->headers->set('X-Admin-Justification', 'Attempting unauthorized access');
        
        // Should fail because user is not a developer
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unauthorized administrative access attempt');
        
        AdminAccessService::getAllPeople();
    }
    
    public function test_guest_users_see_no_data(): void
    {
        // No authentication
        
        // Should see no data as guest
        $people = Person::all();
        $this->assertCount(0, $people);
        
        $couples = Couple::all();
        $this->assertCount(0, $couples);
    }
    
    public function test_team_scope_applies_to_relationships(): void
    {
        $user = User::factory()->create();
        $user->teams()->attach($this->smithFamily);
        $user->switchTeam($this->smithFamily);
        
        $this->actingAs($user);
        
        // Load person with their couples relationship
        $person = Person::with('couples')->first();
        
        // Should only load couples from the same team
        foreach ($person->couples as $couple) {
            $this->assertEquals($this->smithFamily->id, $couple->team_id);
        }
    }
    
    public function test_direct_model_queries_respect_team_scoping(): void
    {
        $user = User::factory()->create();
        $user->teams()->attach($this->smithFamily);
        $user->switchTeam($this->smithFamily);
        
        $this->actingAs($user);
        
        // Test various query methods
        $this->assertCount(2, Person::get());
        $this->assertCount(2, Person::all());
        $this->assertCount(1, Person::where('firstname', 'John')->get());
        $this->assertNull(Person::where('firstname', 'Jane')->first()); // Jane is in Johnson family
        
        // Test couple queries
        $this->assertCount(1, Couple::get());
        $this->assertCount(1, Couple::all());
    }
    
    public function test_can_access_team_method_on_models(): void
    {
        $user = User::factory()->create();
        $user->teams()->attach($this->smithFamily);
        $user->switchTeam($this->smithFamily);
        
        $this->actingAs($user);
        
        $person = Person::first();
        $this->assertTrue($person->canAccessTeam());
        
        // Test with a person from different team (shouldn't be accessible anyway)
        $otherPerson = new Person(['team_id' => $this->johnsonFamily->id]);
        $this->actingAs($user);
        $this->assertFalse($otherPerson->canAccessTeam());
    }
}