<?php

declare(strict_types=1);

namespace App\Php\Gedcom;

use PhpGedcom\Parser;
use Illuminate\Support\Facades\Log;

final class Validator
{
    private array $issues = [];
    private array $warnings = [];
    private array $stats = [
        'individuals' => 0,
        'families' => 0,
        'version' => null,
        'source' => null,
        'encoding' => null,
    ];

    /**
     * Validate GEDCOM file and return detailed analysis
     */
    public function validate(string $gedcomFilePath): array
    {
        $this->issues = [];
        $this->warnings = [];
        $this->stats = [
            'individuals' => 0,
            'families' => 0,
            'version' => null,
            'source' => null,
            'encoding' => null,
        ];

        try {
            Log::info('Starting GEDCOM validation', ['file' => $gedcomFilePath]);

            // Basic file validation
            $this->validateFileStructure($gedcomFilePath);

            // Parse and analyze content
            $gedcom = $this->parseForValidation($gedcomFilePath);

            if ($gedcom) {
                $this->analyzeContent($gedcom);
                $this->validateIndividuals($gedcom);
                $this->validateFamilies($gedcom);
                $this->validateReferences($gedcom);
            }

            $isValid = empty($this->issues);
            $hasWarnings = !empty($this->warnings);

            Log::info('GEDCOM validation completed', [
                'valid' => $isValid,
                'issues_count' => count($this->issues),
                'warnings_count' => count($this->warnings),
                'stats' => $this->stats,
            ]);

            return [
                'valid' => $isValid,
                'can_import' => $isValid,
                'has_warnings' => $hasWarnings,
                'issues' => $this->issues,
                'warnings' => $this->warnings,
                'stats' => $this->stats,
                'recommendations' => $this->generateRecommendations(),
            ];

        } catch (\Exception $e) {
            $this->issues[] = [
                'type' => 'critical',
                'category' => 'file_error',
                'message' => 'Failed to validate GEDCOM file: ' . $e->getMessage(),
                'location' => 'file_level',
                'severity' => 'error',
            ];

            Log::error('GEDCOM validation failed', [
                'error' => $e->getMessage(),
                'file' => $gedcomFilePath,
            ]);

            return [
                'valid' => false,
                'can_import' => false,
                'has_warnings' => false,
                'issues' => $this->issues,
                'warnings' => [],
                'stats' => $this->stats,
                'recommendations' => ['Fix critical file errors before attempting import.'],
            ];
        }
    }

    /**
     * Validate basic file structure and encoding
     */
    private function validateFileStructure(string $filePath): void
    {
        // Check file exists and size
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->issues[] = [
                'type' => 'critical',
                'category' => 'file_access',
                'message' => 'File is not accessible or does not exist',
                'location' => 'file_level',
                'severity' => 'error',
            ];
            return;
        }

        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            $this->issues[] = [
                'type' => 'critical',
                'category' => 'file_structure',
                'message' => 'File is empty',
                'location' => 'file_level',
                'severity' => 'error',
            ];
            return;
        }

        // Check for BOM and encoding issues
        $handle = fopen($filePath, 'r');
        $firstBytes = fread($handle, 10);
        fclose($handle);

        if (substr($firstBytes, 0, 3) === "\xEF\xBB\xBF") {
            $this->warnings[] = [
                'type' => 'encoding',
                'category' => 'file_structure',
                'message' => 'File contains UTF-8 BOM which may cause parsing issues',
                'location' => 'file_header',
                'severity' => 'warning',
                'suggestion' => 'Consider removing BOM or using UTF-8 without BOM encoding',
            ];
        }

        // Validate GEDCOM header
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            $this->issues[] = [
                'type' => 'critical',
                'category' => 'file_structure',
                'message' => 'No valid lines found in file',
                'location' => 'file_level',
                'severity' => 'error',
            ];
            return;
        }

        $firstLine = trim($lines[0]);
        if (!preg_match('/^0\s+HEAD/', $firstLine)) {
            $this->issues[] = [
                'type' => 'critical',
                'category' => 'file_structure',
                'message' => 'Invalid GEDCOM header. Expected "0 HEAD", found: ' . substr($firstLine, 0, 20),
                'location' => 'line_1',
                'severity' => 'error',
            ];
        }

        // Check for trailer
        $lastLine = trim($lines[count($lines) - 1]);
        if (!preg_match('/^0\s+TRLR/', $lastLine)) {
            $this->warnings[] = [
                'type' => 'structure',
                'category' => 'file_structure', 
                'message' => 'Missing or invalid GEDCOM trailer. Expected "0 TRLR"',
                'location' => 'file_end',
                'severity' => 'warning',
                'suggestion' => 'GEDCOM files should end with "0 TRLR"',
            ];
        }
    }

    /**
     * Parse GEDCOM for validation (more lenient than import parsing)
     */
    private function parseForValidation(string $filePath): ?\PhpGedcom\Gedcom
    {
        try {
            $parser = new Parser();
            $gedcom = $parser->parse($filePath);

            if (!$gedcom) {
                $this->issues[] = [
                    'type' => 'critical',
                    'category' => 'parsing',
                    'message' => 'Failed to parse GEDCOM file structure',
                    'location' => 'file_level',
                    'severity' => 'error',
                ];
                return null;
            }

            return $gedcom;

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Check for specific compatibility issues
            if (strpos($errorMessage, 'Unknown') !== false && strpos($errorMessage, '::') !== false) {
                $this->issues[] = [
                    'type' => 'critical',
                    'category' => 'version',
                    'message' => 'GEDCOM version compatibility issue: ' . $errorMessage,
                    'location' => 'file_level',
                    'severity' => 'error',
                    'suggestion' => 'This file appears to use GEDCOM features not supported by the current parser. Try exporting from your genealogy software using GEDCOM 5.5 format.',
                ];
            } elseif (strpos($errorMessage, 'phone') !== false || strpos($errorMessage, 'email') !== false) {
                $this->issues[] = [
                    'type' => 'critical',
                    'category' => 'version',
                    'message' => 'GEDCOM contains newer format fields that are not supported',
                    'location' => 'file_level',
                    'severity' => 'error',
                    'suggestion' => 'This appears to be a GEDCOM 7.0 file. Please export your family tree using GEDCOM 5.5 format for compatibility.',
                ];
            } else {
                $this->issues[] = [
                    'type' => 'critical',
                    'category' => 'parsing',
                    'message' => 'GEDCOM parsing error: ' . $errorMessage,
                    'location' => 'file_level',
                    'severity' => 'error',
                ];
            }
            return null;
        }
    }

    /**
     * Analyze GEDCOM content and extract metadata
     */
    private function analyzeContent(\PhpGedcom\Gedcom $gedcom): void
    {
        // Get header information
        $header = $gedcom->getHead();
        if ($header) {
            // Extract GEDCOM version
            $gedc = $header->getGedc();
            if ($gedc) {
                $this->stats['version'] = $gedc->getVers();
                
                // Check version compatibility
                if ($this->stats['version'] && !$this->isVersionSupported($this->stats['version'])) {
                    $this->warnings[] = [
                        'type' => 'compatibility',
                        'category' => 'version',
                        'message' => "GEDCOM version {$this->stats['version']} may have compatibility issues. Recommended: 5.5.x",
                        'location' => 'header',
                        'severity' => 'warning',
                        'suggestion' => 'Consider exporting from your genealogy software using GEDCOM 5.5 format',
                    ];
                }
            }

            // Extract source information
            $source = $header->getSour();
            if ($source) {
                $this->stats['source'] = $source->getSour();
            }

            // Extract character encoding
            $char = $header->getChar();
            if ($char) {
                $this->stats['encoding'] = $char->getChar();
                
                if ($this->stats['encoding'] && strtoupper($this->stats['encoding']) !== 'UTF-8') {
                    $this->warnings[] = [
                        'type' => 'encoding',
                        'category' => 'compatibility',
                        'message' => "Character encoding '{$this->stats['encoding']}' may cause display issues. UTF-8 recommended.",
                        'location' => 'header',
                        'severity' => 'warning',
                        'suggestion' => 'Export GEDCOM using UTF-8 encoding if possible',
                    ];
                }
            }
        }

        // Count records
        $individuals = $gedcom->getIndi() ?: [];
        $families = $gedcom->getFam() ?: [];

        $this->stats['individuals'] = count($individuals);
        $this->stats['families'] = count($families);

        // Check for reasonable limits
        if ($this->stats['individuals'] > 10000) {
            $this->warnings[] = [
                'type' => 'performance',
                'category' => 'size',
                'message' => "Large number of individuals ({$this->stats['individuals']}). Import may take significant time.",
                'location' => 'file_level',
                'severity' => 'info',
                'suggestion' => 'Consider importing during low-traffic periods',
            ];
        }

        if ($this->stats['individuals'] === 0) {
            $this->issues[] = [
                'type' => 'content',
                'category' => 'data',
                'message' => 'No individuals found in GEDCOM file',
                'location' => 'file_level',
                'severity' => 'error',
            ];
        }
    }

    /**
     * Validate individual records
     */
    private function validateIndividuals(\PhpGedcom\Gedcom $gedcom): void
    {
        $individuals = $gedcom->getIndi() ?: [];
        $issueCount = 0;

        foreach ($individuals as $gedcomId => $individual) {
            $issues = $this->validateIndividualRecord($gedcomId, $individual);
            $issueCount += count($issues);

            // Stop checking after too many issues to prevent overwhelming output
            if ($issueCount > 100) {
                $this->warnings[] = [
                    'type' => 'validation',
                    'category' => 'data_quality',
                    'message' => 'Validation stopped after 100 individual issues. There may be more data quality problems.',
                    'location' => 'individuals',
                    'severity' => 'warning',
                    'suggestion' => 'Review and clean data in source application before import',
                ];
                break;
            }
        }
    }

    /**
     * Validate a single individual record
     */
    private function validateIndividualRecord(string $gedcomId, $individual): array
    {
        $issues = [];

        // Check for required name
        $names = $individual->getName() ?: [];
        if (empty($names)) {
            $issues[] = [
                'type' => 'data',
                'category' => 'missing_data',
                'message' => "Individual {$gedcomId} has no name records",
                'location' => "individual_{$gedcomId}",
                'severity' => 'warning',
                'suggestion' => 'Add name information for better genealogy records',
            ];
            $this->warnings[] = end($issues);
        } else {
            // Validate name format
            foreach ($names as $nameRecord) {
                $fullName = $nameRecord->getName();
                if ($fullName && !preg_match('/\/.*\//', $fullName)) {
                    $issues[] = [
                        'type' => 'format',
                        'category' => 'name_format',
                        'message' => "Individual {$gedcomId} has name without surname delimiters: {$fullName}",
                        'location' => "individual_{$gedcomId}",
                        'severity' => 'info',
                        'suggestion' => 'GEDCOM names should use /Surname/ format',
                    ];
                    $this->warnings[] = end($issues);
                }
            }
        }

        // Check for sex
        $sex = $individual->getSex();
        if (!$sex) {
            $issues[] = [
                'type' => 'data',
                'category' => 'missing_data',
                'message' => "Individual {$gedcomId} has no sex/gender specified",
                'location' => "individual_{$gedcomId}",
                'severity' => 'info',
                'suggestion' => 'Adding sex information helps with family relationship validation',
            ];
            $this->warnings[] = end($issues);
        }

        return $issues;
    }

    /**
     * Validate family records
     */
    private function validateFamilies(\PhpGedcom\Gedcom $gedcom): void
    {
        $families = $gedcom->getFam() ?: [];

        foreach ($families as $gedcomId => $family) {
            $this->validateFamilyRecord($gedcomId, $family);
        }
    }

    /**
     * Validate a single family record
     */
    private function validateFamilyRecord(string $gedcomId, $family): void
    {
        $husband = $family->getHusb();
        $wife = $family->getWife();
        $children = $family->getChil() ?: [];

        // Check for at least one spouse
        if (!$husband && !$wife) {
            $this->warnings[] = [
                'type' => 'data',
                'category' => 'family_structure',
                'message' => "Family {$gedcomId} has no spouses defined",
                'location' => "family_{$gedcomId}",
                'severity' => 'warning',
                'suggestion' => 'Families should have at least one spouse defined',
            ];
        }

        // Check for children without parents
        if (empty($children) && (!$husband && !$wife)) {
            $this->warnings[] = [
                'type' => 'data',
                'category' => 'family_structure',
                'message' => "Family {$gedcomId} has no members (no spouses or children)",
                'location' => "family_{$gedcomId}",
                'severity' => 'warning',
                'suggestion' => 'Consider removing empty family records',
            ];
        }
    }

    /**
     * Validate cross-references between records
     */
    private function validateReferences(\PhpGedcom\Gedcom $gedcom): void
    {
        $individuals = $gedcom->getIndi() ?: [];
        $families = $gedcom->getFam() ?: [];
        $individualIds = array_keys($individuals);
        $familyIds = array_keys($families);

        // Check family references in individuals
        foreach ($individuals as $gedcomId => $individual) {
            // Check FAMC (family as child) references
            $famc = $individual->getFamc() ?: [];
            foreach ($famc as $famcRecord) {
                $familyId = $famcRecord->getFamc();
                if ($familyId && !in_array($familyId, $familyIds)) {
                    $this->issues[] = [
                        'type' => 'reference',
                        'category' => 'broken_reference',
                        'message' => "Individual {$gedcomId} references non-existent family {$familyId} (FAMC)",
                        'location' => "individual_{$gedcomId}",
                        'severity' => 'error',
                    ];
                }
            }

            // Check FAMS (family as spouse) references
            $fams = $individual->getFams() ?: [];
            foreach ($fams as $famsRecord) {
                $familyId = $famsRecord->getFams();
                if ($familyId && !in_array($familyId, $familyIds)) {
                    $this->issues[] = [
                        'type' => 'reference',
                        'category' => 'broken_reference',
                        'message' => "Individual {$gedcomId} references non-existent family {$familyId} (FAMS)",
                        'location' => "individual_{$gedcomId}",
                        'severity' => 'error',
                    ];
                }
            }
        }

        // Check individual references in families
        foreach ($families as $gedcomId => $family) {
            $husband = $family->getHusb();
            $wife = $family->getWife();
            $children = $family->getChil() ?: [];

            if ($husband && !in_array($husband, $individualIds)) {
                $this->issues[] = [
                    'type' => 'reference',
                    'category' => 'broken_reference',
                    'message' => "Family {$gedcomId} references non-existent individual {$husband} (HUSB)",
                    'location' => "family_{$gedcomId}",
                    'severity' => 'error',
                ];
            }

            if ($wife && !in_array($wife, $individualIds)) {
                $this->issues[] = [
                    'type' => 'reference',
                    'category' => 'broken_reference',
                    'message' => "Family {$gedcomId} references non-existent individual {$wife} (WIFE)",
                    'location' => "family_{$gedcomId}",
                    'severity' => 'error',
                ];
            }

            foreach ($children as $childId) {
                if (!in_array($childId, $individualIds)) {
                    $this->issues[] = [
                        'type' => 'reference',
                        'category' => 'broken_reference',
                        'message' => "Family {$gedcomId} references non-existent individual {$childId} (CHIL)",
                        'location' => "family_{$gedcomId}",
                        'severity' => 'error',
                    ];
                }
            }
        }
    }

    /**
     * Check if GEDCOM version is supported
     */
    private function isVersionSupported(string $version): bool
    {
        $supportedVersions = ['5.5', '5.5.1', '5.5.5'];
        return in_array($version, $supportedVersions) || str_starts_with($version, '5.5');
    }

    /**
     * Generate recommendations based on validation results
     */
    private function generateRecommendations(): array
    {
        $recommendations = [];

        if (empty($this->issues) && empty($this->warnings)) {
            $recommendations[] = 'File appears to be valid and ready for import.';
            return $recommendations;
        }

        if (!empty($this->issues)) {
            $recommendations[] = 'Fix critical issues before attempting import to ensure success.';
        }

        $issueTypes = array_unique(array_column($this->issues, 'category'));
        $warningTypes = array_unique(array_column($this->warnings, 'category'));

        if (in_array('broken_reference', $issueTypes)) {
            $recommendations[] = 'Repair broken family/individual references in your genealogy software.';
        }

        if (in_array('version', $warningTypes) || in_array('version', $issueTypes)) {
            $recommendations[] = 'Export using GEDCOM 5.5 format for best compatibility.';
            $recommendations[] = 'In Family Tree Maker: File → Export → GEDCOM, then select version 5.5.';
        }

        if (in_array('encoding', $warningTypes)) {
            $recommendations[] = 'Use UTF-8 encoding when exporting GEDCOM files.';
        }

        if (in_array('name_format', $warningTypes)) {
            $recommendations[] = 'Ensure surnames are properly formatted with /Surname/ delimiters.';
        }

        if ($this->stats['individuals'] > 5000) {
            $recommendations[] = 'Large import - consider importing during off-peak hours.';
        }

        // Family Tree Maker specific recommendations
        if ($this->stats['source'] && (
            strpos(strtolower($this->stats['source']), 'family tree maker') !== false ||
            strpos(strtolower($this->stats['source']), 'ftm') !== false
        )) {
            $recommendations[] = 'Family Tree Maker detected: Ensure you are exporting in GEDCOM 5.5 format for compatibility.';
        }

        // Version-specific recommendations
        if ($this->stats['version'] && version_compare($this->stats['version'], '6.0', '>=')) {
            $recommendations[] = 'GEDCOM version ' . $this->stats['version'] . ' detected: This newer format may cause import issues. Please export using GEDCOM 5.5.';
        }

        return $recommendations;
    }
}