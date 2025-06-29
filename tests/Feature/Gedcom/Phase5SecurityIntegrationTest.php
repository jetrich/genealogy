<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;
use App\Models\Person;
use App\Php\Gedcom\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

describe('Phase 5: Security Integration', function () {
    
    test('file size validation prevents oversized uploads', function () {
        $user = User::factory()->create();
        $import = new Import('Test', null, 'large.ged', $user);
        
        // Create a temporary file that's too large (simulate 100MB)
        $largeFile = tempnam(sys_get_temp_dir(), 'large_gedcom');
        $handle = fopen($largeFile, 'w');
        
        // Write a basic GEDCOM header
        fwrite($handle, "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n");
        
        // Pad with data to exceed 50MB limit
        $dataChunk = str_repeat("1 NOTE This is padding data\n", 1000);
        for ($i = 0; $i < 60000; $i++) { // Approximate 60MB
            fwrite($handle, $dataChunk);
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
    });
    
    test('invalid GEDCOM header detection works', function () {
        $user = User::factory()->create();
        $import = new Import('Test', null, 'invalid.ged', $user);
        
        // Create file with invalid header
        $invalidFile = tempnam(sys_get_temp_dir(), 'invalid_gedcom');
        file_put_contents($invalidFile, "This is not a GEDCOM file\nInvalid content");
        
        try {
            expect(function () use ($import, $invalidFile) {
                $import->import($invalidFile);
            })->toThrow(Exception::class, 'Invalid GEDCOM file format');
            
        } finally {
            unlink($invalidFile);
        }
    });
    
    test('malicious content detection in names', function () {
        $user = User::factory()->create();
        $import = new Import('Test', null, 'malicious.ged', $user);
        
        // Create GEDCOM with malicious script content
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME <script>alert('xss')</script> /Evil/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'malicious_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            expect(function () use ($import, $tempFile) {
                $import->import($tempFile);
            })->toThrow(Exception::class, 'GEDCOM file contains potentially malicious content');
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('excessive individuals count detection', function () {
        $user = User::factory()->create();
        $import = new Import('Test', null, 'massive.ged', $user);
        
        // Create GEDCOM with too many individuals (simulate by checking validation)
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        
        // Add many individuals (more than the 10,000 limit)
        for ($i = 1; $i <= 10001; $i++) {
            $gedcomContent .= "0 @I{$i}@ INDI\n";
            $gedcomContent .= "1 NAME Person{$i} /Test/\n";
            $gedcomContent .= "1 SEX M\n";
        }
        
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'massive_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        try {
            expect(function () use ($import, $tempFile) {
                $import->import($tempFile);
            })->toThrow(Exception::class, 'GEDCOM file contains too many individuals');
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('security logging captures important events', function () {
        $user = User::factory()->create();
        $import = new Import('Test Security', 'Security test', 'secure.ged', $user);
        
        // Create valid GEDCOM
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME John /Doe/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'secure_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        // Capture logs
        Log::spy();
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            
            // Verify security logging occurred
            Log::shouldHaveReceived('info')
                ->with('GEDCOM file security validation passed', \Mockery::type('array'))
                ->once();
                
            Log::shouldHaveReceived('info')
                ->with('GEDCOM parsing activity monitor', \Mockery::type('array'))
                ->once();
                
            Log::shouldHaveReceived('info')
                ->with('GEDCOM content security validation passed', \Mockery::type('array'))
                ->once();
            
        } finally {
            unlink($tempFile);
        }
    });
    
    test('file extension validation works in Livewire component', function () {
        $this->actingAs(User::factory()->create());
        
        // Create a fake file with wrong extension
        $fakeFile = UploadedFile::fake()->create('malicious.txt', 100);
        
        $component = \Livewire\Livewire::test(\App\Livewire\Gedcom\Importteam::class)
            ->set('name', 'Test Team')
            ->set('file', $fakeFile);
        
        expect(function () use ($component) {
            $component->call('importteam');
        })->toThrow(Exception::class, 'Invalid file type. Only .ged and .gedcom files are allowed.');
    });
    
    test('dangerous filename patterns are detected', function () {
        $this->actingAs(User::factory()->create());
        
        // Create file with dangerous filename
        $dangerousFile = UploadedFile::fake()->createWithContent('../../../etc/passwd.ged', 'content');
        
        $component = \Livewire\Livewire::test(\App\Livewire\Gedcom\Importteam::class)
            ->set('name', 'Test Team')
            ->set('file', $dangerousFile);
        
        expect(function () use ($component) {
            $component->call('importteam');
        })->toThrow(Exception::class, 'Filename contains invalid characters.');
    });
    
    test('security context is logged on import failure', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Create invalid GEDCOM that will fail parsing
        $invalidFile = UploadedFile::fake()->createWithContent('invalid.ged', 'Invalid GEDCOM content');
        
        Log::spy();
        
        $component = \Livewire\Livewire::test(\App\Livewire\Gedcom\Importteam::class)
            ->set('name', 'Test Team')
            ->set('file', $invalidFile);
        
        $component->call('importteam');
        
        // Verify security error logging occurred
        Log::shouldHaveReceived('error')
            ->with('GEDCOM import failed with security context', \Mockery::type('array'))
            ->once();
    });
    
    test('memory monitoring for large files works', function () {
        $user = User::factory()->create();
        $import = new Import('Test', null, 'large.ged', $user);
        
        // Create file larger than 10MB to trigger memory monitoring
        $largeFile = tempnam(sys_get_temp_dir(), 'large_gedcom');
        $handle = fopen($largeFile, 'w');
        
        // Write valid GEDCOM header
        fwrite($handle, "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n");
        
        // Add enough data to exceed 10MB
        for ($i = 0; $i < 15000; $i++) {
            fwrite($handle, "0 @I{$i}@ INDI\n1 NAME Person{$i} /Large/\n1 SEX M\n");
        }
        
        fwrite($handle, "0 TRLR");
        fclose($handle);
        
        Log::spy();
        
        try {
            // This should trigger memory monitoring
            $import->import($largeFile);
            
            // Verify large file parsing was logged
            Log::shouldHaveReceived('info')
                ->with('Large GEDCOM file parsing initiated', \Mockery::type('array'))
                ->once();
                
        } catch (Exception $e) {
            // Expected to fail due to too many individuals, but should still log memory monitoring
            Log::shouldHaveReceived('info')
                ->with('Large GEDCOM file parsing initiated', \Mockery::type('array'))
                ->once();
        } finally {
            unlink($largeFile);
        }
    });
    
    test('valid security-checked import succeeds with proper logging', function () {
        $user = User::factory()->create();
        $import = new Import('Secure Family', 'Security validated', 'secure.ged', $user);
        
        // Create valid, clean GEDCOM
        $gedcomContent = "0 HEAD\n1 SOUR TestApp\n1 GEDC\n2 VERS 5.5\n";
        $gedcomContent .= "0 @I1@ INDI\n";
        $gedcomContent .= "1 NAME Robert /Smith/\n";
        $gedcomContent .= "1 SEX M\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 1 JAN 1970\n";
        $gedcomContent .= "0 @I2@ INDI\n";
        $gedcomContent .= "1 NAME Mary /Smith/\n";
        $gedcomContent .= "1 SEX F\n";
        $gedcomContent .= "1 BIRT\n";
        $gedcomContent .= "2 DATE 15 MAR 1975\n";
        $gedcomContent .= "0 @F1@ FAM\n";
        $gedcomContent .= "1 HUSB @I1@\n";
        $gedcomContent .= "1 WIFE @I2@\n";
        $gedcomContent .= "1 MARR\n";
        $gedcomContent .= "2 DATE 20 JUN 2000\n";
        $gedcomContent .= "0 TRLR";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'secure_gedcom');
        file_put_contents($tempFile, $gedcomContent);
        
        Log::spy();
        
        try {
            $result = $import->import($tempFile);
            
            expect($result['success'])->toBeTrue();
            expect($result['stats']['individuals'])->toBe(2);
            expect($result['stats']['families'])->toBe(1);
            expect($result['stats']['errors'])->toBe(0);
            
            // Verify all security validation steps were logged
            Log::shouldHaveReceived('info')
                ->with('GEDCOM file security validation passed', \Mockery::type('array'))
                ->once();
                
            Log::shouldHaveReceived('info')
                ->with('GEDCOM content security validation passed', \Mockery::type('array'))
                ->once();
                
        } finally {
            unlink($tempFile);
        }
    });
});