<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;
use App\Models\Person;
use App\Php\Gedcom\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('Phase 3: Person Data Import', function () {
    
    test('name extraction works with standard GEDCOM format', function () {
        $user = User::factory()->create();
        $import = new Import('Test', null, 'test.ged', $user);
        
        // Create test GEDCOM with realistic individual data
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME John Doe /Smith/\n";
        $gedcomContent .= "2 GIVN John Doe\n";
        $gedcomContent .= "2 SURN Smith\n";
        $gedcomContent .= "2 NICK Johnny\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 1 JAN 1990\n";
        $gedcomContent .= "2 PLAC New York, NY, USA\n";
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(1);
            expect($result['stats']['errors'])->toBe(0);
            
            // Verify person was created with correct data
            $team = $result['team'];
            $persons = $team->persons;
            expect($persons)->toHaveCount(1);
            
            $person = $persons->first();
            expect($person->firstname)->toBe('John Doe');
            expect($person->surname)->toBe('Smith');
            expect($person->nickname)->toBe('Johnny');
            expect($person->sex)->toBe('M');
            expect($person->dob->format('Y-m-d'))->toBe('1990-01-01');
            expect($person->yob)->toBe(1990);
            expect($person->pob)->toBe('New York, NY, USA');
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('birth and death information extraction works correctly', function () {
        $user = User::factory()->create();
        $import = new Import('Test', null, 'test.ged', $user);
        
        // Create test GEDCOM with birth and death events
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Jane /Doe/\n";
        $gedcomContent .= "1 SEX F\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 15 MAR 1965\n";
        $gedcomContent .= "2 PLAC Boston, MA, USA\n";
        $gedcomContent .= "1 DEAT\n";
        $gedcomContent .= "2 DATE 20 DEC 2020\n";
        $gedcomContent .= "2 PLAC Miami, FL, USA\n";
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(1);
            
            $person = $result['team']->persons->first();
            expect($person->firstname)->toBe('Jane');
            expect($person->surname)->toBe('Doe');
            expect($person->sex)->toBe('F');
            expect($person->dob->format('Y-m-d'))->toBe('1965-03-15');
            expect($person->yob)->toBe(1965);
            expect($person->pob)->toBe('Boston, MA, USA');
            expect($person->dod->format('Y-m-d'))->toBe('2020-12-20');
            expect($person->yod)->toBe(2020);
            expect($person->pod)->toBe('Miami, FL, USA');
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('handles various GEDCOM date formats correctly', function () {
        $user = User::factory()->create();
        $import = new Import('Test', null, 'test.ged', $user);
        
        // Test different date formats
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Year /Only/\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 1950\n";
        $gedcomContent .= "0 @I2@ INDI\n";
        $gedcomContent .= "1 NAME Month /Year/\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE JUN 1975\n";
        $gedcomContent .= "0 @I3@ INDI\n";
        $gedcomContent .= "1 NAME Approximate /Date/\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE ABT 1980\n";
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(3);
            
            $persons = $result['team']->persons->sortBy('firstname');
            
            // Year only
            $person1 = $persons->where('firstname', 'Approximate')->first();
            expect($person1->yob)->toBe(1980);
            
            // Month/Year
            $person2 = $persons->where('firstname', 'Month')->first();
            expect($person2->yob)->toBe(1975);
            expect($person2->dob->format('Y-m'))->toBe('1975-06');
            
            // Year only
            $person3 = $persons->where('firstname', 'Year')->first();
            expect($person3->yob)->toBe(1950);
            expect($person3->dob->format('Y'))->toBe('1950');
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('handles missing or incomplete data gracefully', function () {
        $user = User::factory()->create();
        $import = new Import('Test', null, 'test.ged', $user);
        
        // Create test GEDCOM with minimal data
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME /Minimal/\n";
        $gedcomContent .= "0 @I2@ INDI\n";
        $gedcomContent .= "1 NAME NoData\n";
        $gedcomContent .= "1 SEX U\n";
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(2);
            
            $persons = $result['team']->persons;
            
            // Person with surname only
            $person1 = $persons->where('surname', 'Minimal')->first();
            expect($person1->firstname)->toBeNull();
            expect($person1->surname)->toBe('Minimal');
            expect($person1->sex)->toBeNull();
            
            // Person with firstname only and unknown sex
            $person2 = $persons->where('firstname', 'NoData')->first();
            expect($person2->firstname)->toBe('NoData');
            expect($person2->surname)->toBeNull();
            expect($person2->sex)->toBe('X'); // U converted to X
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('sex conversion works correctly', function () {
        $user = User::factory()->create();
        $import = new Import('Test', null, 'test.ged', $user);
        
        // Create test GEDCOM with various sex values
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Male /Person/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "0 @I2@ INDI\n";
        $gedcomContent .= "1 NAME Female /Person/\n";
        $gedcomContent .= "1 SEX F\n";
        $gedcomContent .= "0 @I3@ INDI\n";
        $gedcomContent .= "1 NAME Unknown /Person/\n";
        $gedcomContent .= "1 SEX U\n";
        $gedcomContent .= "0 @I4@ INDI\n";
        $gedcomContent .= "1 NAME NoSex /Person/\n";
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(4);
            
            $persons = $result['team']->persons;
            
            expect($persons->where('firstname', 'Male')->first()->sex)->toBe('M');
            expect($persons->where('firstname', 'Female')->first()->sex)->toBe('F');
            expect($persons->where('firstname', 'Unknown')->first()->sex)->toBe('X');
            expect($persons->where('firstname', 'NoSex')->first()->sex)->toBeNull();
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('multiple individuals are imported correctly', function () {
        $user = User::factory()->create();
        $import = new Import('Family Tree', 'Test family', 'test.ged', $user);
        
        // Create test GEDCOM with multiple people
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        
        // Father
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Robert William /Johnson/\n";
        $gedcomContent .= "2 GIVN Robert William\n";
        $gedcomContent .= "2 SURN Johnson\n";
        $gedcomContent .= "2 NICK Bob\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 12 APR 1940\n";
        $gedcomContent .= "2 PLAC Chicago, IL, USA\n";
        
        // Mother  
        $gedcomContent .= "0 @I2@ INDI\n";
        $gedcomContent .= "1 NAME Mary Elizabeth /Johnson/\n";
        $gedcomContent .= "2 GIVN Mary Elizabeth\n";
        $gedcomContent .= "2 SURN Johnson\n";
        $gedcomContent .= "1 SEX F\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 8 JUN 1942\n";
        $gedcomContent .= "2 PLAC Detroit, MI, USA\n";
        
        // Child
        $gedcomContent .= "0 @I3@ INDI\n";
        $gedcomContent .= "1 NAME Michael Robert /Johnson/\n";
        $gedcomContent .= "2 GIVN Michael Robert\n";
        $gedcomContent .= "2 SURN Johnson\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 15 SEP 1970\n";
        $gedcomContent .= "2 PLAC Milwaukee, WI, USA\n";
        
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(3);
            expect($result['stats']['errors'])->toBe(0);
            
            $team = $result['team'];
            expect($team->name)->toBe('Family Tree');
            expect($team->description)->toBe('Test family');
            
            $persons = $team->persons;
            expect($persons)->toHaveCount(3);
            
            // Verify each person
            $father = $persons->where('firstname', 'Robert William')->first();
            expect($father->surname)->toBe('Johnson');
            expect($father->nickname)->toBe('Bob');
            expect($father->sex)->toBe('M');
            expect($father->birthYear)->toBe('1940');
            expect($father->pob)->toBe('Chicago, IL, USA');
            
            $mother = $persons->where('firstname', 'Mary Elizabeth')->first();
            expect($mother->surname)->toBe('Johnson');
            expect($mother->sex)->toBe('F');
            expect($mother->birthYear)->toBe('1942');
            expect($mother->pob)->toBe('Detroit, MI, USA');
            
            $child = $persons->where('firstname', 'Michael Robert')->first();
            expect($child->surname)->toBe('Johnson');
            expect($child->sex)->toBe('M');
            expect($child->birthYear)->toBe('1970');
            expect($child->pob)->toBe('Milwaukee, WI, USA');
            
        } finally {
            unlink($tempFile);
        }
    });
});