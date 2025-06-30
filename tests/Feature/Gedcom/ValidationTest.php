<?php

declare(strict_types=1);

use App\Models\User;
use App\Php\Gedcom\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('GEDCOM Validation System', function () {
    
    test('validates GEDCOM 5.5 file successfully', function () {
        $validator = new Validator();
        
        // Use the test-2.ged file (GEDCOM 5.5.1 format)
        $gedcomPath = storage_path('app/public/gedcom/test-2.ged');
        
        expect(file_exists($gedcomPath))->toBeTrue();
        
        $result = $validator->validate($gedcomPath);
        
        expect($result)->toHaveKeys([
            'valid', 'can_import', 'has_warnings', 'issues', 'warnings', 'stats', 'recommendations'
        ]);
        
        expect($result['valid'])->toBeTrue();
        expect($result['can_import'])->toBeTrue();
        expect($result['stats']['individuals'])->toBeGreaterThan(0);
        expect($result['stats']['version'])->toBe('5.5.1');
        expect($result['stats']['source'])->toBe('webtrees');
        expect($result['stats']['encoding'])->toBe('UTF-8');
    });
    
    test('detects GEDCOM 7.0 version compatibility warning', function () {
        $validator = new Validator();
        
        // Use the test-1.ged file (GEDCOM 7.0 format)
        $gedcomPath = storage_path('app/public/gedcom/test-1.ged');
        
        expect(file_exists($gedcomPath))->toBeTrue();
        
        $result = $validator->validate($gedcomPath);
        
        expect($result['stats']['version'])->toBe('7.0');
        expect($result['has_warnings'])->toBeTrue();
        
        // Should have version compatibility warning
        $versionWarnings = array_filter($result['warnings'], function ($warning) {
            return $warning['category'] === 'version';
        });
        
        expect($versionWarnings)->not->toBeEmpty();
    });
    
    test('detects invalid GEDCOM file structure', function () {
        $validator = new Validator();
        
        // Create invalid GEDCOM content
        $invalidContent = "This is not a GEDCOM file\nJust plain text";
        $tempFile = tempnam(sys_get_temp_dir(), 'invalid_gedcom');
        file_put_contents($tempFile, $invalidContent);
        
        try {
            $result = $validator->validate($tempFile);
            
            expect($result['valid'])->toBeFalse();
            expect($result['can_import'])->toBeFalse();
            expect($result['issues'])->not->toBeEmpty();
            
            // Should have file structure issues
            $structureIssues = array_filter($result['issues'], function ($issue) {
                return $issue['category'] === 'file_structure';
            });
            
            expect($structureIssues)->not->toBeEmpty();
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('detects malicious content in GEDCOM', function () {
        $validator = new Validator();
        
        // Create GEDCOM with malicious content
        $maliciousContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $maliciousContent .= "0 @I1@ INDI\n";
        $maliciousContent .= "1 NAME <script>alert('xss')</script> /Evil/\n";
        $maliciousContent .= "1 SEX M\n";
        $maliciousContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'malicious_gedcom');
        file_put_contents($tempFile, $maliciousContent);
        
        try {
            $result = $validator->validate($tempFile);
            
            expect($result['valid'])->toBeFalse();
            expect($result['can_import'])->toBeFalse();
            expect($result['issues'])->not->toBeEmpty();
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('provides helpful recommendations based on issues', function () {
        $validator = new Validator();
        
        // Use a file with warnings to test recommendations
        $gedcomPath = storage_path('app/public/gedcom/test-1.ged');
        $result = $validator->validate($gedcomPath);
        
        expect($result['recommendations'])->toBeArray();
        expect($result['recommendations'])->not->toBeEmpty();
        
        // Should include version recommendation for GEDCOM 7.0
        $versionRecommendation = array_filter($result['recommendations'], function ($rec) {
            return strpos($rec, 'GEDCOM 5.5') !== false;
        });
        
        expect($versionRecommendation)->not->toBeEmpty();
    });
    
    test('livewire validation component works correctly', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Create valid GEDCOM content for upload
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Test /Person/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "0 TRLR";
        
        $file = UploadedFile::fake()->createWithContent('test.ged', $gedcomContent);
        
        $component = \Livewire\Livewire::test(\App\Livewire\Gedcom\ValidateImport::class)
            ->set('file', $file)
            ->call('validateGedcom');
        
        $component->assertSet('showResults', true)
                  ->assertSee('Validation Summary')
                  ->assertSee('Valid');
    });
    
    test('validation integration with import workflow', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // First validate a file
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Integration /Test/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "0 TRLR";
        
        $file = UploadedFile::fake()->createWithContent('integration.ged', $gedcomContent);
        
        // Validate first
        $validateComponent = \Livewire\Livewire::test(\App\Livewire\Gedcom\ValidateImport::class)
            ->set('file', $file)
            ->call('validateGedcom');
        
        expect($validateComponent->get('validationResults')['valid'])->toBeTrue();
        
        // Proceed to import - should redirect and set session
        $validateComponent->call('proceedToImport');
        $validateComponent->assertRedirect(route('gedcom.importteam'));
        
        // Check that validation results are in session
        expect(session('gedcom_validation_results'))->not->toBeNull();
        expect(session('gedcom_validated_file'))->toBe('integration.ged');
    });
    
    test('import component recognizes pre-validated files', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Set up session as if file was pre-validated
        session([
            'gedcom_validation_results' => [
                'valid' => true,
                'can_import' => true,
                'issues' => [],
                'warnings' => [
                    ['message' => 'Test warning', 'severity' => 'warning']
                ],
                'stats' => ['individuals' => 1, 'families' => 0]
            ],
            'gedcom_validated_file' => 'prevalidated.ged'
        ]);
        
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Prevalidated /Test/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "0 TRLR";
        
        $file = UploadedFile::fake()->createWithContent('prevalidated.ged', $gedcomContent);
        
        $component = \Livewire\Livewire::test(\App\Livewire\Gedcom\Importteam::class)
            ->set('name', 'Test Team')
            ->set('file', $file)
            ->call('importteam');
        
        // Should succeed and redirect to team
        $component->assertRedirect();
        
        // Session data should be cleared after successful import
        expect(session('gedcom_validation_results'))->toBeNull();
        expect(session('gedcom_validated_file'))->toBeNull();
    });
});