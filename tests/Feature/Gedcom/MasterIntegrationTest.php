<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;
use App\Models\Person;
use App\Models\Couple;
use App\Php\Gedcom\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

describe('Master GEDCOM Integration: End-to-End Testing', function () {
    
    test('complete family tree import with all phases integrated', function () {
        $user = User::factory()->create();
        $import = new Import('Complete Family Tree', 'Integration test family', 'integration.ged', $user);
        
        // Create comprehensive GEDCOM with multiple generations
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        
        // Great-grandfather
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME William /Smith/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 12 JAN 1890\n";
        $gedcomContent .= "2 PLAC Boston, MA, USA\n";
        $gedcomContent .= "1 DEAT\n";
        $gedcomContent .= "2 DATE 15 MAR 1970\n";
        $gedcomContent .= "2 PLAC Boston, MA, USA\n";
        
        // Great-grandmother
        $gedcomContent .= "0 @I2@ INDI\n";
        $gedcomContent .= "1 NAME Margaret /Johnson/\n";
        $gedcomContent .= "1 SEX F\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 8 APR 1895\n";
        $gedcomContent .= "2 PLAC New York, NY, USA\n";
        $gedcomContent .= "1 DEAT\n";
        $gedcomContent .= "2 DATE 22 NOV 1975\n";
        
        // Grandfather
        $gedcomContent .= "0 @I3@ INDI\n";
        $gedcomContent .= "1 NAME Robert /Smith/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 5 JUN 1920\n";
        $gedcomContent .= "2 PLAC Boston, MA, USA\n";
        $gedcomContent .= "1 DEAT\n";
        $gedcomContent .= "2 DATE 10 SEP 1995\n";
        
        // Grandmother
        $gedcomContent .= "0 @I4@ INDI\n";
        $gedcomContent .= "1 NAME Mary /Davis/\n";
        $gedcomContent .= "1 SEX F\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 14 FEB 1925\n";
        $gedcomContent .= "2 PLAC Chicago, IL, USA\n";
        
        // Father
        $gedcomContent .= "0 @I5@ INDI\n";
        $gedcomContent .= "1 NAME John /Smith/\n";
        $gedcomContent .= "2 GIVN John\n";
        $gedcomContent .= "2 SURN Smith\n";
        $gedcomContent .= "2 NICK Johnny\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 20 MAY 1950\n";
        $gedcomContent .= "2 PLAC Boston, MA, USA\n";
        
        // Mother
        $gedcomContent .= "0 @I6@ INDI\n";
        $gedcomContent .= "1 NAME Jane /Wilson/\n";
        $gedcomContent .= "1 SEX F\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 18 AUG 1952\n";
        $gedcomContent .= "2 PLAC Philadelphia, PA, USA\n";
        
        // Son
        $gedcomContent .= "0 @I7@ INDI\n";
        $gedcomContent .= "1 NAME Michael /Smith/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 25 DEC 1980\n";
        $gedcomContent .= "2 PLAC Boston, MA, USA\n";
        
        // Daughter
        $gedcomContent .= "0 @I8@ INDI\n";
        $gedcomContent .= "1 NAME Sarah /Smith/\n";
        $gedcomContent .= "1 SEX F\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 10 JUL 1983\n";
        $gedcomContent .= "2 PLAC Boston, MA, USA\n";
        
        // Great-grandparents marriage
        $gedcomContent .= "0 @F1@ FAM\n";
        $gedcomContent .= "1 HUSB @I1@\n";
        $gedcomContent .= "1 WIFE @I2@\n";
        $gedcomContent .= "1 CHIL @I3@\n";
        $gedcomContent .= "1 MARR\n";
        $gedcomContent .= "2 DATE 15 JUN 1915\n";
        
        // Grandparents marriage
        $gedcomContent .= "0 @F2@ FAM\n";
        $gedcomContent .= "1 HUSB @I3@\n";
        $gedcomContent .= "1 WIFE @I4@\n";
        $gedcomContent .= "1 CHIL @I5@\n";
        $gedcomContent .= "1 MARR\n";
        $gedcomContent .= "2 DATE 22 SEP 1945\n";
        
        // Parents marriage
        $gedcomContent .= "0 @F3@ FAM\n";
        $gedcomContent .= "1 HUSB @I5@\n";
        $gedcomContent .= "1 WIFE @I6@\n";
        $gedcomContent .= "1 CHIL @I7@\n";
        $gedcomContent .= "1 CHIL @I8@\n";
        $gedcomContent .= "1 MARR\n";
        $gedcomContent .= "2 DATE 12 JUN 1975\n";
        
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'integration_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            // Verify import success
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(8);
            expect($result['stats']['families'])->toBe(3);
            expect($result['stats']['errors'])->toBe(0);
            
            $team = $result['team'];
            $persons = $team->persons->sortBy('birthYear');
            $couples = $team->couples;
            
            // Verify all individuals were created correctly
            expect($persons)->toHaveCount(8);
            expect($couples)->toHaveCount(3);
            
            // Test Phase 3: Person data extraction
            $william = $persons->where('firstname', 'William')->first();
            $margaret = $persons->where('firstname', 'Margaret')->first();
            $robert = $persons->where('firstname', 'Robert')->first();
            $mary = $persons->where('firstname', 'Mary')->first();
            $john = $persons->where('firstname', 'John')->first();
            $jane = $persons->where('firstname', 'Jane')->first();
            $michael = $persons->where('firstname', 'Michael')->first();
            $sarah = $persons->where('firstname', 'Sarah')->first();
            
            // Verify person details (Phase 3)
            expect($william->surname)->toBe('Smith');
            expect($william->sex)->toBe('M');
            expect($william->birthYear)->toBe('1890');
            expect($william->deathYear)->toBe('1970');
            expect($william->pob)->toBe('Boston, MA, USA');
            expect($william->pod)->toBe('Boston, MA, USA');
            
            expect($john->nickname)->toBe('Johnny');
            expect($jane->surname)->toBe('Wilson');
            
            // Test Phase 4: Family relationships
            $greatGrandparentsCouple = $couples->where(function ($couple) use ($william, $margaret) {
                return ($couple->person1_id === $william->id && $couple->person2_id === $margaret->id) ||
                       ($couple->person1_id === $margaret->id && $couple->person2_id === $william->id);
            })->first();
            
            $grandparentsCouple = $couples->where(function ($couple) use ($robert, $mary) {
                return ($couple->person1_id === $robert->id && $couple->person2_id === $mary->id) ||
                       ($couple->person1_id === $mary->id && $couple->person2_id === $robert->id);
            })->first();
            
            $parentsCouple = $couples->where(function ($couple) use ($john, $jane) {
                return ($couple->person1_id === $john->id && $couple->person2_id === $jane->id) ||
                       ($couple->person1_id === $jane->id && $couple->person2_id === $john->id);
            })->first();
            
            // Verify couples exist and have correct marriage data
            expect($greatGrandparentsCouple)->not->toBeNull();
            expect($grandparentsCouple)->not->toBeNull();
            expect($parentsCouple)->not->toBeNull();
            
            expect($greatGrandparentsCouple->is_married)->toBeTrue();
            expect($greatGrandparentsCouple->date_start->format('Y-m-d'))->toBe('1915-06-15');
            
            expect($grandparentsCouple->date_start->format('Y-m-d'))->toBe('1945-09-22');
            expect($parentsCouple->date_start->format('Y-m-d'))->toBe('1975-06-12');
            
            // Verify parent-child relationships
            expect($robert->father_id)->toBe($william->id);
            expect($robert->mother_id)->toBe($margaret->id);
            expect($robert->parents_id)->toBe($greatGrandparentsCouple->id);
            
            expect($john->father_id)->toBe($robert->id);
            expect($john->mother_id)->toBe($mary->id);
            expect($john->parents_id)->toBe($grandparentsCouple->id);
            
            expect($michael->father_id)->toBe($john->id);
            expect($michael->mother_id)->toBe($jane->id);
            expect($michael->parents_id)->toBe($parentsCouple->id);
            
            expect($sarah->father_id)->toBe($john->id);
            expect($sarah->mother_id)->toBe($jane->id);
            expect($sarah->parents_id)->toBe($parentsCouple->id);
            
            // Verify multi-generational relationships work
            $johnsChildren = $john->children;
            expect($johnsChildren)->toHaveCount(2);
            expect($johnsChildren->pluck('id')->toArray())->toContain($michael->id, $sarah->id);
            
            $robertsChildren = $robert->children;
            expect($robertsChildren)->toHaveCount(1);
            expect($robertsChildren->first()->id)->toBe($john->id);
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('security integration prevents malicious imports', function () {
        $user = User::factory()->create();
        
        // Test 1: File too large
        $import = new Import('Large Test', null, 'large.ged', $user);
        $largeFile = tempnam(sys_get_temp_dir(), 'large_gedcom');
        
        // Create file exceeding 50MB limit
        $handle = fopen($largeFile, 'w');
        fwrite($handle, "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n");
        for ($i = 0; $i < 60000; $i++) {
            fwrite($handle, str_repeat("1 NOTE Padding data for file size test\n", 100));
        }
        fwrite($handle, "0 TRLR");
        fclose($handle);
        
        try {
            expect(function () use ($import, $largeFile) {
                $import->import($largeFile);
            })->toThrow(Exception::class, 'File size exceeds maximum allowed limit');
        } finally {
            unlink($largeFile);
        }
        
        // Test 2: Malicious content detection
        $import2 = new Import('Malicious Test', null, 'malicious.ged', $user);
        $maliciousContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $maliciousContent .= "0 @I1@ INDI\n";
        $maliciousContent .= "1 NAME <script>alert('xss')</script> /Evil/\n";
        $maliciousContent .= "1 SEX M\n";
        $maliciousContent .= "0 TRLR";
        
        $maliciousFile = tempnam(sys_get_temp_dir(), 'malicious_gedcom');
        file_put_contents($maliciousFile, $maliciousContent);
        
        try {
            expect(function () use ($import2, $maliciousFile) {
                $import2->import($maliciousFile);
            })->toThrow(Exception::class, 'potentially malicious content');
        } finally {
            unlink($maliciousFile);
        }
        
        // Test 3: Too many individuals
        $import3 = new Import('Massive Test', null, 'massive.ged', $user);
        $massiveContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        
        // Add more than 10,000 individuals
        for ($i = 1; $i <= 10001; $i++) {
            $massiveContent .= "0 @I{$i}@ INDI\n";
            $massiveContent .= "1 NAME Person{$i} /Test/\n";
            $massiveContent .= "1 SEX M\n";
        }
        $massiveContent .= "0 TRLR";
        
        $massiveFile = tempnam(sys_get_temp_dir(), 'massive_gedcom');
        file_put_contents($massiveFile, $massiveContent);
        
        try {
            expect(function () use ($import3, $massiveFile) {
                $import3->import($massiveFile);
            })->toThrow(Exception::class, 'too many individuals');
        } finally {
            unlink($massiveFile);
        }
    });
    
    test('comprehensive logging and audit trail works', function () {
        $user = User::factory()->create();
        $import = new Import('Audit Test', 'Security audit test', 'audit.ged', $user);
        
        // Create valid GEDCOM for logging test
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Audit /Test/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'audit_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        Log::spy();
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            
            // Verify comprehensive security logging occurred
            Log::shouldHaveReceived('info')->with('Starting GEDCOM import', \Mockery::type('array'));
            Log::shouldHaveReceived('info')->with('GEDCOM file security validation passed', \Mockery::type('array'));
            Log::shouldHaveReceived('info')->with('GEDCOM parsing activity monitor', \Mockery::type('array'));
            Log::shouldHaveReceived('info')->with('GEDCOM content security validation passed', \Mockery::type('array'));
            Log::shouldHaveReceived('info')->with('GEDCOM import completed successfully', \Mockery::type('array'));
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('error handling and recovery works correctly', function () {
        $user = User::factory()->create();
        $import = new Import('Error Test', 'Error handling test', 'error.ged', $user);
        
        // Create GEDCOM with some valid and some problematic data
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        
        // Valid person
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Valid /Person/\n";
        $gedcomContent .= "1 SEX M\n";
        
        // Person with problematic date
        $gedcomContent .= "0 @I2@ INDI\n";
        $gedcomContent .= "1 NAME Problem /Date/\n";
        $gedcomContent .= "1 SEX F\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE INVALID DATE FORMAT\n";
        
        // Family referencing non-existent person
        $gedcomContent .= "0 @F1@ FAM\n";
        $gedcomContent .= "1 HUSB @I1@\n";
        $gedcomContent .= "1 WIFE @I999@\n"; // Non-existent person
        
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'error_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            // Should succeed despite errors
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(2); // Both individuals should be created
            expect($result['stats']['families'])->toBe(0); // Family should fail due to missing person
            expect($result['stats']['errors'])->toBeGreaterThan(0); // Should have logged errors
            
            // Verify team and valid person were created
            $team = $result['team'];
            $persons = $team->persons;
            expect($persons)->toHaveCount(2);
            
            $validPerson = $persons->where('firstname', 'Valid')->first();
            expect($validPerson)->not->toBeNull();
            expect($validPerson->surname)->toBe('Person');
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('all security validations work in Livewire component', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Test invalid file extension
        $invalidFile = UploadedFile::fake()->create('malicious.txt', 100);
        
        $component = \Livewire\Livewire::test(\App\Livewire\Gedcom\Importteam::class)
            ->set('name', 'Test Team')
            ->set('file', $invalidFile);
        
        expect(function () use ($component) {
            $component->call('importteam');
        })->toThrow(Exception::class, 'Invalid file type');
        
        // Test dangerous filename
        $dangerousFile = UploadedFile::fake()->createWithContent('../evil.ged', 'content');
        
        $component2 = \Livewire\Livewire::test(\App\Livewire\Gedcom\Importteam::class)
            ->set('name', 'Test Team')
            ->set('file', $dangerousFile);
        
        expect(function () use ($component2) {
            $component2->call('importteam');
        })->toThrow(Exception::class, 'invalid characters');
    });
    
    test('complete workflow integration with permissions', function () {
        // Create user with proper permissions
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Create valid GEDCOM file
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Integration /Test/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 1 JAN 2000\n";
        $gedcomContent .= "0 TRLR";
        
        $validFile = UploadedFile::fake()->createWithContent('valid.ged', $gedcomContent);
        
        $component = \Livewire\Livewire::test(\App\Livewire\Gedcom\Importteam::class)
            ->set('name', 'Integration Test Team')
            ->set('description', 'Full workflow test')
            ->set('file', $validFile)
            ->call('importteam');
        
        // Should redirect to team show page (indicating success)
        $component->assertRedirect();
        
        // Verify team and person were created
        $team = Team::where('name', 'Integration Test Team')->first();
        expect($team)->not->toBeNull();
        expect($team->user_id)->toBe($user->id);
        
        $person = $team->persons->first();
        expect($person)->not->toBeNull();
        expect($person->firstname)->toBe('Integration');
        expect($person->surname)->toBe('Test');
    });
});