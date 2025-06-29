<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;

describe('Security Foundation', function () {
    
    test('mass assignment vulnerability is fixed - is_developer cannot be set via fillable', function () {
        $userData = [
            'firstname' => 'Test',
            'surname' => 'User', 
            'email' => 'test@example.com',
            'password' => 'securepassword123',
            'is_developer' => true, // This should be ignored
        ];

        $user = User::create($userData);
        
        // is_developer should NOT be set to true via mass assignment
        expect($user->is_developer)->toBeFalse();
        expect($user->firstname)->toBe('Test');
        expect($user->email)->toBe('test@example.com');
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
});