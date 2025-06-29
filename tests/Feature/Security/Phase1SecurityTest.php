<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;

describe('Phase 1: Security Foundation', function () {
    
    test('mass assignment vulnerability is fixed - is_developer cannot be set via fillable', function () {
        $userData = [
            'firstname' => 'Test',
            'surname' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'is_developer' => true, // This should be ignored
        ];

        $user = User::create($userData);
        
        // is_developer should NOT be set to true via mass assignment
        expect($user->is_developer)->toBeFalse();
        expect($user->firstname)->toBe('Test');
        expect($user->email)->toBe('test@example.com');
    });

    test('new users get administrator role on personal team', function () {
        $userData = [
            'firstname' => 'New',
            'surname' => 'User',
            'email' => 'newuser@example.com',
            'language' => 'en',
            'timezone' => 'UTC',
            'password' => 'password123',
        ];

        $createUser = new \App\Actions\Fortify\CreateNewUser();
        $user = $createUser->create($userData);

        // User should have a personal team
        expect($user->ownedTeams)->toHaveCount(1);
        
        $personalTeam = $user->ownedTeams->first();
        expect($personalTeam->personal_team)->toBeTrue();
        
        // User should be attached to team with administrator role
        $teamUser = $personalTeam->users()->where('user_id', $user->id)->first();
        expect($teamUser)->not->toBeNull();
        expect($teamUser->pivot->role)->toBe('administrator');
    });

    test('administrators have GEDCOM import permissions', function () {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        
        // Attach user as administrator
        $team->users()->attach($user->id, ['role' => 'administrator']);
        $user->current_team_id = $team->id;
        $user->save();

        // Check GEDCOM permissions
        expect($user->hasPermission('person:create'))->toBeTrue();
        expect($user->hasPermission('team:import'))->toBeTrue();
        expect($user->hasPermission('team:export'))->toBeTrue();
        expect($user->hasPermission('team:manage'))->toBeTrue();
    });

    test('managers have GEDCOM import permissions but not team management', function () {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        
        // Attach user as manager
        $team->users()->attach($user->id, ['role' => 'manager']);
        $user->current_team_id = $team->id;
        $user->save();

        // Check GEDCOM permissions
        expect($user->hasPermission('person:create'))->toBeTrue();
        expect($user->hasPermission('team:import'))->toBeTrue();
        expect($user->hasPermission('team:export'))->toBeTrue();
        expect($user->hasPermission('team:manage'))->toBeFalse();
    });

    test('editors have GEDCOM import permissions', function () {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        
        // Attach user as editor
        $team->users()->attach($user->id, ['role' => 'editor']);
        $user->current_team_id = $team->id;
        $user->save();

        // Check GEDCOM permissions
        expect($user->hasPermission('person:create'))->toBeTrue();
        expect($user->hasPermission('team:import'))->toBeTrue();
        expect($user->hasPermission('team:export'))->toBeTrue();
        expect($user->hasPermission('person:delete'))->toBeFalse(); // editors can't delete
    });

    test('members cannot import GEDCOM files', function () {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        
        // Attach user as member (read-only)
        $team->users()->attach($user->id, ['role' => 'member']);
        $user->current_team_id = $team->id;
        $user->save();

        // Check GEDCOM permissions
        expect($user->hasPermission('person:read'))->toBeTrue();
        expect($user->hasPermission('person:create'))->toBeFalse();
        expect($user->hasPermission('team:import'))->toBeFalse();
        expect($user->hasPermission('team:export'))->toBeFalse();
    });

    test('team model allows user_id in fillable for GEDCOM import', function () {
        $user = User::factory()->create();
        
        $teamData = [
            'user_id' => $user->id,
            'name' => 'GEDCOM Import Team',
            'description' => 'Created from GEDCOM import',
            'personal_team' => false,
        ];

        // This should not throw a mass assignment exception
        $team = Team::create($teamData);
        
        expect($team->user_id)->toBe($user->id);
        expect($team->name)->toBe('GEDCOM Import Team');
        expect($team->personal_team)->toBeFalse();
    });

    test('GEDCOM controller requires person:create permission', function () {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        
        // User with no permissions
        $user->current_team_id = $team->id;
        $user->save();

        // Should get 403 when trying to access GEDCOM import
        $this->actingAs($user)
            ->get(route('gedcom.importteam'))
            ->assertStatus(403);

        // Now give them administrator role
        $team->users()->attach($user->id, ['role' => 'administrator']);
        
        // Should now be able to access
        $this->actingAs($user)
            ->get(route('gedcom.importteam'))
            ->assertStatus(200);
    });
});