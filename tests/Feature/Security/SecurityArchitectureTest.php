<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Team;
use App\Models\Person;
use App\Models\Couple;
use App\Models\Concerns\HasSecureTeamScope;
use App\Services\AdminAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end security architecture tests.
 * 
 * SECURITY: These tests verify that the complete security architecture
 * is properly implemented and working as designed, preventing the
 * critical multi-tenancy vulnerabilities that existed previously.
 */
class SecurityArchitectureTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_person_model_uses_secure_team_scope_trait(): void
    {
        $this->assertTrue(
            in_array(HasSecureTeamScope::class, class_uses(Person::class)),
            'Person model must use HasSecureTeamScope trait'
        );
    }
    
    public function test_couple_model_uses_secure_team_scope_trait(): void
    {
        $this->assertTrue(
            in_array(HasSecureTeamScope::class, class_uses(Couple::class)),
            'Couple model must use HasSecureTeamScope trait' 
        );
    }
    
    public function test_person_model_has_secure_team_global_scope(): void
    {
        $person = new Person();
        $globalScopes = $person->getGlobalScopes();
        
        $this->assertArrayHasKey(
            'secure_team',
            $globalScopes,
            'Person model must have secure_team global scope'
        );
    }
    
    public function test_couple_model_has_secure_team_global_scope(): void
    {
        $couple = new Couple();
        $globalScopes = $couple->getGlobalScopes();
        
        $this->assertArrayHasKey(
            'secure_team', 
            $globalScopes,
            'Couple model must have secure_team global scope'
        );
    }
    
    public function test_no_developer_bypass_in_person_queries(): void
    {
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        $person1 = Person::factory()->create(['team_id' => $team1->id]);
        $person2 = Person::factory()->create(['team_id' => $team2->id]);
        
        $developer = User::factory()->create(['is_developer' => true]);
        $developer->teams()->attach($team1);
        $developer->switchTeam($team1);
        
        $this->actingAs($developer);
        
        // Developer should NOT see all people automatically
        $people = Person::all();
        $this->assertCount(1, $people);
        $this->assertEquals($person1->id, $people->first()->id);
        
        // Should NOT contain person from other team
        $peopleIds = $people->pluck('id')->toArray();
        $this->assertNotContains($person2->id, $peopleIds);
    }
    
    public function test_no_developer_bypass_in_couple_queries(): void
    {
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        $couple1 = Couple::factory()->create(['team_id' => $team1->id]);
        $couple2 = Couple::factory()->create(['team_id' => $team2->id]);
        
        $developer = User::factory()->create(['is_developer' => true]);
        $developer->teams()->attach($team1);
        $developer->switchTeam($team1);
        
        $this->actingAs($developer);
        
        // Developer should NOT see all couples automatically
        $couples = Couple::all();
        $this->assertCount(1, $couples);
        $this->assertEquals($couple1->id, $couples->first()->id);
        
        // Should NOT contain couple from other team
        $coupleIds = $couples->pluck('id')->toArray();
        $this->assertNotContains($couple2->id, $coupleIds);
    }
    
    public function test_admin_access_service_exists_and_has_security_methods(): void
    {
        $this->assertTrue(
            class_exists(AdminAccessService::class),
            'AdminAccessService class must exist'
        );
        
        $this->assertTrue(
            method_exists(AdminAccessService::class, 'getAllPeople'),
            'AdminAccessService must have getAllPeople method'
        );
        
        $this->assertTrue(
            method_exists(AdminAccessService::class, 'getAllCouples'),
            'AdminAccessService must have getAllCouples method'
        );
        
        $this->assertTrue(
            method_exists(AdminAccessService::class, 'getCrossTeamStatistics'),
            'AdminAccessService must have getCrossTeamStatistics method'
        );
    }
    
    public function test_admin_context_middleware_is_registered(): void
    {
        $middleware = app()->make('router')->getMiddleware();
        
        $this->assertArrayHasKey(
            'admin.context',
            $middleware,
            'AdminContextMiddleware must be registered as admin.context'
        );
    }
    
    public function test_security_fixes_prevent_data_leakage(): void
    {
        // Create multiple teams with data
        $teams = Team::factory()->count(3)->create();
        $people = collect();
        $couples = collect();
        
        foreach ($teams as $team) {
            $teamPeople = Person::factory()->count(2)->create(['team_id' => $team->id]);
            $teamCouples = Couple::factory()->count(1)->create(['team_id' => $team->id]);
            
            $people = $people->merge($teamPeople);
            $couples = $couples->merge($teamCouples);
        }
        
        // Test with regular user
        $user = User::factory()->create();
        $user->teams()->attach($teams->first());
        $user->switchTeam($teams->first());
        
        $this->actingAs($user);
        
        // Should only see data from first team (2 people, 1 couple)
        $visiblePeople = Person::all();
        $visibleCouples = Couple::all();
        
        $this->assertCount(2, $visiblePeople);
        $this->assertCount(1, $visibleCouples);
        
        // Test with developer (should NOT see all data automatically)
        $developer = User::factory()->create(['is_developer' => true]);
        $developer->teams()->attach($teams->get(1)); // Second team
        $developer->switchTeam($teams->get(1));
        
        $this->actingAs($developer);
        
        // Developer should still only see their current team's data
        $devVisiblePeople = Person::all();
        $devVisibleCouples = Couple::all();
        
        $this->assertCount(2, $devVisiblePeople);
        $this->assertCount(1, $devVisibleCouples);
        
        // Verify data is different from first user
        $userIds = $visiblePeople->pluck('id')->toArray();
        $devIds = $devVisiblePeople->pluck('id')->toArray();
        $this->assertEmpty(array_intersect($userIds, $devIds));
    }
    
    public function test_security_architecture_completely_prevents_cross_team_access(): void
    {
        // Create separate family trees
        $smithFamily = Team::factory()->create(['name' => 'Smith Family Tree']);
        $johnsonFamily = Team::factory()->create(['name' => 'Johnson Family Tree']);
        
        // Add sensitive data to each family
        $smithSecretPerson = Person::factory()->create([
            'firstname' => 'Secret',
            'surname' => 'Smith',
            'team_id' => $smithFamily->id
        ]);
        
        $johnsonSecretPerson = Person::factory()->create([
            'firstname' => 'Confidential',
            'surname' => 'Johnson', 
            'team_id' => $johnsonFamily->id
        ]);
        
        // Test Smith family member access
        $smithUser = User::factory()->create();
        $smithUser->teams()->attach($smithFamily);
        $smithUser->switchTeam($smithFamily);
        
        $this->actingAs($smithUser);
        
        $accessiblePeople = Person::all();
        $accessibleNames = $accessiblePeople->pluck('firstname')->toArray();
        
        // Should see Smith family data
        $this->assertContains('Secret', $accessibleNames);
        // Should NOT see Johnson family data
        $this->assertNotContains('Confidential', $accessibleNames);
        
        // Test Johnson family member access
        $johnsonUser = User::factory()->create();
        $johnsonUser->teams()->attach($johnsonFamily);
        $johnsonUser->switchTeam($johnsonFamily);
        
        $this->actingAs($johnsonUser);
        
        $accessiblePeople = Person::all();
        $accessibleNames = $accessiblePeople->pluck('firstname')->toArray();
        
        // Should see Johnson family data
        $this->assertContains('Confidential', $accessibleNames);
        // Should NOT see Smith family data
        $this->assertNotContains('Secret', $accessibleNames);
    }
    
    public function test_developer_must_use_admin_service_for_cross_team_access(): void
    {
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        Person::factory()->create(['team_id' => $team1->id]);
        Person::factory()->create(['team_id' => $team2->id]);
        
        $developer = User::factory()->create(['is_developer' => true]);
        $developer->teams()->attach($team1);
        $developer->switchTeam($team1);
        
        $this->actingAs($developer);
        
        // Normal queries should still be scoped
        $scopedPeople = Person::all();
        $this->assertCount(1, $scopedPeople);
        
        // Must use AdminAccessService for cross-team access
        request()->headers->set('X-Admin-Context', 'authorized');
        request()->headers->set('X-Admin-Justification', 'Testing cross-team access requirement');
        
        $allPeople = AdminAccessService::getAllPeople();
        $this->assertCount(2, $allPeople);
        
        // Verify this is the ONLY way to get cross-team access
        $this->assertGreaterThan($scopedPeople->count(), $allPeople->count());
    }
}