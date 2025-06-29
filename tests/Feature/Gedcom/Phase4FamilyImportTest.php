<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;
use App\Models\Person;
use App\Models\Couple;
use App\Php\Gedcom\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('Phase 4: Family/Relationship Import', function () {
    
    test('couple creation from GEDCOM family works correctly', function () {
        $user = User::factory()->create();
        $import = new Import('Test Family', 'Family with marriage', 'test.ged', $user);
        
        // Create test GEDCOM with family relationships
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        
        // Husband
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME John /Smith/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 1 JAN 1970\n";
        
        // Wife
        $gedcomContent .= "0 @I2@ INDI\n";
        $gedcomContent .= "1 NAME Jane /Doe/\n";
        $gedcomContent .= "1 SEX F\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 15 FEB 1972\n";
        
        // Family/Marriage
        $gedcomContent .= "0 @F1@ FAM\n";
        $gedcomContent .= "1 HUSB @I1@\n";
        $gedcomContent .= "1 WIFE @I2@\n";
        $gedcomContent .= "1 MARR\n";
        $gedcomContent .= "2 DATE 20 JUN 1995\n";
        
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(2);
            expect($result['stats']['families'])->toBe(1);
            expect($result['stats']['errors'])->toBe(0);
            
            $team = $result['team'];
            $persons = $team->persons;
            $couples = $team->couples;
            
            expect($persons)->toHaveCount(2);
            expect($couples)->toHaveCount(1);
            
            // Verify couple information
            $couple = $couples->first();
            expect($couple->is_married)->toBeTrue();
            expect($couple->has_ended)->toBeFalse();
            expect($couple->date_start->format('Y-m-d'))->toBe('1995-06-20');
            
            // Verify persons are linked correctly
            $husband = $persons->where('firstname', 'John')->first();
            $wife = $persons->where('firstname', 'Jane')->first();
            
            expect($couple->person1_id)->toBeIn([$husband->id, $wife->id]);
            expect($couple->person2_id)->toBeIn([$husband->id, $wife->id]);
            expect($couple->person1_id)->not->toBe($couple->person2_id);
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('parent-child relationships are established correctly', function () {
        $user = User::factory()->create();
        $import = new Import('Test Family', 'Complete family', 'test.ged', $user);
        
        // Create test GEDCOM with parent-child relationships
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        
        // Father
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Robert /Johnson/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 1 APR 1940\n";
        
        // Mother
        $gedcomContent .= "0 @I2@ INDI\n";
        $gedcomContent .= "1 NAME Mary /Johnson/\n";
        $gedcomContent .= "1 SEX F\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 8 JUN 1942\n";
        
        // Child 1
        $gedcomContent .= "0 @I3@ INDI\n";
        $gedcomContent .= "1 NAME Michael /Johnson/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 15 SEP 1970\n";
        
        // Child 2
        $gedcomContent .= "0 @I4@ INDI\n";
        $gedcomContent .= "1 NAME Sarah /Johnson/\n";
        $gedcomContent .= "1 SEX F\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 22 NOV 1975\n";
        
        // Family
        $gedcomContent .= "0 @F1@ FAM\n";
        $gedcomContent .= "1 HUSB @I1@\n";
        $gedcomContent .= "1 WIFE @I2@\n";
        $gedcomContent .= "1 CHIL @I3@\n";
        $gedcomContent .= "1 CHIL @I4@\n";
        $gedcomContent .= "1 MARR\n";
        $gedcomContent .= "2 DATE 12 MAY 1968\n";
        
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(4);
            expect($result['stats']['families'])->toBe(1);
            
            $team = $result['team'];
            $persons = $team->persons;
            $couples = $team->couples;
            
            // Get specific family members
            $father = $persons->where('firstname', 'Robert')->first();
            $mother = $persons->where('firstname', 'Mary')->first();
            $son = $persons->where('firstname', 'Michael')->first();
            $daughter = $persons->where('firstname', 'Sarah')->first();
            $couple = $couples->first();
            
            // Verify couple relationship
            expect($couple->is_married)->toBeTrue();
            expect($couple->date_start->format('Y-m-d'))->toBe('1968-05-12');
            
            // Verify parent-child relationships
            expect($son->father_id)->toBe($father->id);
            expect($son->mother_id)->toBe($mother->id);
            expect($son->parents_id)->toBe($couple->id);
            
            expect($daughter->father_id)->toBe($father->id);
            expect($daughter->mother_id)->toBe($mother->id);
            expect($daughter->parents_id)->toBe($couple->id);
            
            // Verify children relationships from parent perspective
            $fatherChildren = $father->children;
            $motherChildren = $mother->children;
            
            expect($fatherChildren)->toHaveCount(2);
            expect($motherChildren)->toHaveCount(2);
            expect($fatherChildren->pluck('id')->toArray())->toContain($son->id, $daughter->id);
            expect($motherChildren->pluck('id')->toArray())->toContain($son->id, $daughter->id);
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('divorce information is captured correctly', function () {
        $user = User::factory()->create();
        $import = new Import('Test Family', 'Divorced couple', 'test.ged', $user);
        
        // Create test GEDCOM with divorced couple
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        
        // Ex-husband
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME David /Brown/\n";
        $gedcomContent .= "1 SEX M\n";
        
        // Ex-wife
        $gedcomContent .= "0 @I2@ INDI\n";
        $gedcomContent .= "1 NAME Lisa /Brown/\n";
        $gedcomContent .= "1 SEX F\n";
        
        // Family with marriage and divorce
        $gedcomContent .= "0 @F1@ FAM\n";
        $gedcomContent .= "1 HUSB @I1@\n";
        $gedcomContent .= "1 WIFE @I2@\n";
        $gedcomContent .= "1 MARR\n";
        $gedcomContent .= "2 DATE 14 FEB 1990\n";
        $gedcomContent .= "1 DIV\n";
        $gedcomContent .= "2 DATE 30 SEP 2005\n";
        
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            expect($result['stats']['families'])->toBe(1);
            
            $couple = $result['team']->couples->first();
            
            expect($couple->is_married)->toBeTrue();
            expect($couple->has_ended)->toBeTrue();
            expect($couple->date_start->format('Y-m-d'))->toBe('1990-02-14');
            expect($couple->date_end->format('Y-m-d'))->toBe('2005-09-30');
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('single parent families work correctly', function () {
        $user = User::factory()->create();
        $import = new Import('Test Family', 'Single parent', 'test.ged', $user);
        
        // Create test GEDCOM with single parent
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        
        // Mother
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Emma /Wilson/\n";
        $gedcomContent .= "1 SEX F\n";
        
        // Child
        $gedcomContent .= "0 @I2@ INDI\n";
        $gedcomContent .= "1 NAME Alex /Wilson/\n";
        $gedcomContent .= "1 SEX M\n";
        
        // Family with only mother and child
        $gedcomContent .= "0 @F1@ FAM\n";
        $gedcomContent .= "1 WIFE @I1@\n";
        $gedcomContent .= "1 CHIL @I2@\n";
        
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(2);
            expect($result['stats']['families'])->toBe(1);
            
            $team = $result['team'];
            $persons = $team->persons;
            
            $mother = $persons->where('firstname', 'Emma')->first();
            $child = $persons->where('firstname', 'Alex')->first();
            
            // Verify child has mother but no father
            expect($child->mother_id)->toBe($mother->id);
            expect($child->father_id)->toBeNull();
            expect($child->parents_id)->toBeNull(); // No couple record for single parent
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('multiple families with same person work correctly', function () {
        $user = User::factory()->create();
        $import = new Import('Test Family', 'Multiple marriages', 'test.ged', $user);
        
        // Create test GEDCOM with person married twice
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        
        // Main person
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Tom /Anderson/\n";
        $gedcomContent .= "1 SEX M\n";
        
        // First wife
        $gedcomContent .= "0 @I2@ INDI\n";
        $gedcomContent .= "1 NAME Anna /Anderson/\n";
        $gedcomContent .= "1 SEX F\n";
        
        // Second wife
        $gedcomContent .= "0 @I3@ INDI\n";
        $gedcomContent .= "1 NAME Beth /Anderson/\n";
        $gedcomContent .= "1 SEX F\n";
        
        // Child from first marriage
        $gedcomContent .= "0 @I4@ INDI\n";
        $gedcomContent .= "1 NAME Tim /Anderson/\n";
        $gedcomContent .= "1 SEX M\n";
        
        // Child from second marriage
        $gedcomContent .= "0 @I5@ INDI\n";
        $gedcomContent .= "1 NAME Tina /Anderson/\n";
        $gedcomContent .= "1 SEX F\n";
        
        // First family
        $gedcomContent .= "0 @F1@ FAM\n";
        $gedcomContent .= "1 HUSB @I1@\n";
        $gedcomContent .= "1 WIFE @I2@\n";
        $gedcomContent .= "1 CHIL @I4@\n";
        $gedcomContent .= "1 MARR\n";
        $gedcomContent .= "2 DATE 10 JUN 1980\n";
        $gedcomContent .= "1 DIV\n";
        $gedcomContent .= "2 DATE 15 MAR 1990\n";
        
        // Second family
        $gedcomContent .= "0 @F2@ FAM\n";
        $gedcomContent .= "1 HUSB @I1@\n";
        $gedcomContent .= "1 WIFE @I3@\n";
        $gedcomContent .= "1 CHIL @I5@\n";
        $gedcomContent .= "1 MARR\n";
        $gedcomContent .= "2 DATE 22 AUG 1995\n";
        
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(5);
            expect($result['stats']['families'])->toBe(2);
            
            $team = $result['team'];
            $persons = $team->persons;
            $couples = $team->couples;
            
            expect($couples)->toHaveCount(2);
            
            $tom = $persons->where('firstname', 'Tom')->first();
            $anna = $persons->where('firstname', 'Anna')->first();
            $beth = $persons->where('firstname', 'Beth')->first();
            $tim = $persons->where('firstname', 'Tim')->first();
            $tina = $persons->where('firstname', 'Tina')->first();
            
            // Verify both couples exist
            $couple1 = $couples->where(function ($couple) use ($tom, $anna) {
                return ($couple->person1_id === $tom->id && $couple->person2_id === $anna->id) ||
                       ($couple->person1_id === $anna->id && $couple->person2_id === $tom->id);
            })->first();
            
            $couple2 = $couples->where(function ($couple) use ($tom, $beth) {
                return ($couple->person1_id === $tom->id && $couple->person2_id === $beth->id) ||
                       ($couple->person1_id === $beth->id && $couple->person2_id === $tom->id);
            })->first();
            
            expect($couple1)->not->toBeNull();
            expect($couple2)->not->toBeNull();
            
            // Verify first marriage details
            expect($couple1->is_married)->toBeTrue();
            expect($couple1->has_ended)->toBeTrue();
            expect($couple1->date_start->format('Y-m-d'))->toBe('1980-06-10');
            expect($couple1->date_end->format('Y-m-d'))->toBe('1990-03-15');
            
            // Verify second marriage details
            expect($couple2->is_married)->toBeTrue();
            expect($couple2->has_ended)->toBeFalse();
            expect($couple2->date_start->format('Y-m-d'))->toBe('1995-08-22');
            
            // Verify children relationships
            expect($tim->father_id)->toBe($tom->id);
            expect($tim->mother_id)->toBe($anna->id);
            expect($tim->parents_id)->toBe($couple1->id);
            
            expect($tina->father_id)->toBe($tom->id);
            expect($tina->mother_id)->toBe($beth->id);
            expect($tina->parents_id)->toBe($couple2->id);
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('handles families with missing persons gracefully', function () {
        $user = User::factory()->create();
        $import = new Import('Test Family', 'Incomplete family', 'test.ged', $user);
        
        // Create test GEDCOM with family referencing non-existent person
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        
        // Only one person
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Known /Person/\n";
        $gedcomContent .= "1 SEX M\n";
        
        // Family referencing unknown person @I2@
        $gedcomContent .= "0 @F1@ FAM\n";
        $gedcomContent .= "1 HUSB @I1@\n";
        $gedcomContent .= "1 WIFE @I2@\n";
        $gedcomContent .= "1 MARR\n";
        $gedcomContent .= "2 DATE 1 JAN 2000\n";
        
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(1);
            expect($result['stats']['families'])->toBe(0); // Should not create couple
            expect($result['stats']['errors'])->toBeGreaterThan(0); // Should log error
            
        } finally {
            unlink($tempFile);
        }
    });
});