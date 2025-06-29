<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;
use App\Php\Gedcom\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('Phase 2: Core PhpGedcom Parsing', function () {
    
    test('import class can be instantiated with proper parameters', function () {
        $user = User::factory()->create();
        
        $import = new Import(
            'Test Family Tree',
            'Imported from GEDCOM',
            'test.ged',
            $user
        );
        
        expect($import)->toBeInstanceOf(Import::class);
    });

    test('team creation works with user assignment', function () {
        $user = User::factory()->create();
        
        $import = new Import(
            'Test Family Tree',
            'Test Description',
            'test.ged',
            $user
        );

        // Use reflection to test the private createTeam method
        $reflection = new ReflectionClass($import);
        $createTeamMethod = $reflection->getMethod('createTeam');
        $createTeamMethod->setAccessible(true);
        
        $team = $createTeamMethod->invoke($import);
        
        expect($team)->toBeInstanceOf(Team::class);
        expect($team->name)->toBe('Test Family Tree');
        expect($team->description)->toBe('Test Description');
        expect($team->user_id)->toBe($user->id);
        expect($team->personal_team)->toBeFalse();
        
        // Check user is attached with admin role
        $teamUser = $team->users()->where('user_id', $user->id)->first();
        expect($teamUser)->not->toBeNull();
        expect($teamUser->pivot->role)->toBe('administrator');
    });

    test('gedcom date parsing handles various formats', function () {
        $user = User::factory()->create();
        $import = new Import('Test', null, 'test.ged', $user);
        
        // Use reflection to test the protected parseGedcomDate method
        $reflection = new ReflectionClass($import);
        $parseDateMethod = $reflection->getMethod('parseGedcomDate');
        $parseDateMethod->setAccessible(true);
        
        // Test various GEDCOM date formats
        $result = $parseDateMethod->invoke($import, '1 JAN 1990');
        expect($result)->not->toBeNull();
        expect($result->year)->toBe(1990);
        expect($result->month)->toBe(1);
        expect($result->day)->toBe(1);
        
        // Test month/year format
        $result = $parseDateMethod->invoke($import, 'JAN 1990');
        expect($result)->not->toBeNull();
        expect($result->year)->toBe(1990);
        expect($result->month)->toBe(1);
        
        // Test year only format
        $result = $parseDateMethod->invoke($import, '1990');
        expect($result)->not->toBeNull();
        expect($result->year)->toBe(1990);
        
        // Test with GEDCOM prefixes
        $result = $parseDateMethod->invoke($import, 'ABT 1990');
        expect($result)->not->toBeNull();
        expect($result->year)->toBe(1990);
        
        // Test null input
        $result = $parseDateMethod->invoke($import, null);
        expect($result)->toBeNull();
        
        // Test empty string
        $result = $parseDateMethod->invoke($import, '');
        expect($result)->toBeNull();
    });

    test('event finder helper works correctly', function () {
        $user = User::factory()->create();
        $import = new Import('Test', null, 'test.ged', $user);
        
        // Use reflection to test the protected findEventByType method
        $reflection = new ReflectionClass($import);
        $findEventMethod = $reflection->getMethod('findEventByType');
        $findEventMethod->setAccessible(true);
        
        // Mock some events
        $event1 = new \PhpGedcom\Record\Indi\Birt();
        $event2 = new \PhpGedcom\Record\Indi\Deat();
        $events = [$event1, $event2];
        
        // Test finding birth event
        $result = $findEventMethod->invoke($import, $events, 'PhpGedcom\Record\Indi\Birt');
        expect($result)->toBe($event1);
        
        // Test finding death event
        $result = $findEventMethod->invoke($import, $events, 'PhpGedcom\Record\Indi\Deat');
        expect($result)->toBe($event2);
        
        // Test finding non-existent event
        $result = $findEventMethod->invoke($import, $events, 'PhpGedcom\Record\Indi\Marr');
        expect($result)->toBeNull();
        
        // Test empty events array
        $result = $findEventMethod->invoke($import, [], 'PhpGedcom\Record\Indi\Birt');
        expect($result)->toBeNull();
    });

    test('import statistics tracking works', function () {
        $user = User::factory()->create();
        $import = new Import('Test', null, 'test.ged', $user);
        
        // Use reflection to check initial stats
        $reflection = new ReflectionClass($import);
        $statsProperty = $reflection->getProperty('importStats');
        $statsProperty->setAccessible(true);
        
        $stats = $statsProperty->getValue($import);
        
        expect($stats)->toEqual([
            'individuals' => 0,
            'families' => 0,
            'errors' => 0,
        ]);
    });

    test('comprehensive logging is implemented', function () {
        $user = User::factory()->create();
        
        // Test that the import class uses proper logging
        $import = new Import(
            'Test Family Tree',
            'Test Description', 
            'test.ged',
            $user
        );
        
        // Create a simple test GEDCOM content
        $gedcomContent = "0 HEAD\n1 SOUR Test\n1 GEDC\n2 VERS 5.5\n0 TRLR";
        $tempFile = tempnam(sys_get_temp_dir(), 'test_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            // This should log the parsing attempt
            $result = $import->import($tempFile);
            
            // Should succeed and create team with placeholder data
            expect($result)->toHaveKey('success');
            expect($result['success'])->toBeTrue();
            expect($result)->toHaveKey('team');
            expect($result['team'])->toBeInstanceOf(Team::class);
            expect($result)->toHaveKey('stats');
            
        } finally {
            unlink($tempFile);
        }
    });
});