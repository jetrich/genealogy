<?php

declare(strict_types=1);

namespace App\Php\Gedcom;

use App\Models\Team;
use App\Models\Person;
use App\Models\Couple;
use App\Models\User;
use PhpGedcom\Parser;
use PhpGedcom\Record\Indi;
use PhpGedcom\Record\Fam;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class Import
{
    private string $teamName;
    private ?string $teamDescription;
    private string $filename;
    private User $user;
    private array $gedcomPersonMap = []; // Maps GEDCOM IDs to Person IDs
    private array $importStats = [
        'individuals' => 0,
        'families' => 0,
        'errors' => 0,
    ];

    public function __construct(string $name, ?string $description, string $filename, User $user)
    {
        $this->teamName = $name;
        $this->teamDescription = $description;
        $this->filename = $filename;
        $this->user = $user;
    }

    /**
     * Import GEDCOM file and create team with genealogy data
     */
    public function import(string $gedcomFilePath): array
    {
        try {
            Log::info('Starting GEDCOM import', [
                'user_id' => $this->user->id,
                'team_name' => $this->teamName,
                'file' => $this->filename,
            ]);

            return DB::transaction(function () use ($gedcomFilePath) {
                // Create the team first
                $team = $this->createTeam();
                
                // Parse the GEDCOM file
                $gedcom = $this->parseGedcomFile($gedcomFilePath);
                
                if (!$gedcom) {
                    throw new \Exception('Failed to parse GEDCOM file');
                }

                Log::info('GEDCOM file parsed successfully', [
                    'individuals_count' => count($gedcom->getIndi() ?: []),
                    'families_count' => count($gedcom->getFam() ?: []),
                ]);

                // Import individuals first
                $this->importIndividuals($gedcom, $team);
                
                // Import families (relationships)
                $this->importFamilies($gedcom, $team);

                Log::info('GEDCOM import completed successfully', $this->importStats);

                return [
                    'success' => true,
                    'team' => $team,
                    'stats' => $this->importStats,
                ];
            });

        } catch (\Exception $e) {
            Log::error('GEDCOM import failed', [
                'error' => $e->getMessage(),
                'file' => $this->filename,
                'user_id' => $this->user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Create new team for the imported genealogy data
     */
    private function createTeam(): Team
    {
        $team = Team::create([
            'user_id' => $this->user->id,
            'name' => $this->teamName,
            'description' => $this->teamDescription,
            'personal_team' => false,
        ]);

        // Add the user to the team as owner
        $team->users()->attach($this->user->id, ['role' => 'administrator']);

        return $team;
    }

    /**
     * Parse GEDCOM file using PhpGedcom library
     */
    private function parseGedcomFile(string $filePath): ?\PhpGedcom\Gedcom
    {
        try {
            Log::info('Parsing GEDCOM file', ['path' => $filePath]);
            
            $parser = new Parser();
            $gedcom = $parser->parse($filePath);
            
            if (!$gedcom) {
                throw new \Exception('Parser returned null - invalid GEDCOM format');
            }

            return $gedcom;
            
        } catch (\Exception $e) {
            Log::error('GEDCOM parsing failed', [
                'error' => $e->getMessage(),
                'file' => $filePath,
            ]);
            throw new \Exception('Failed to parse GEDCOM file: ' . $e->getMessage());
        }
    }

    /**
     * Import individuals from GEDCOM
     */
    private function importIndividuals(\PhpGedcom\Gedcom $gedcom, Team $team): void
    {
        $individuals = $gedcom->getIndi();
        
        if (!$individuals || empty($individuals)) {
            Log::warning('No individuals found in GEDCOM file');
            return;
        }

        Log::info('Starting individual import', ['count' => count($individuals)]);

        foreach ($individuals as $gedcomId => $individual) {
            try {
                $person = $this->createPersonFromGedcom($individual, $team);
                $this->gedcomPersonMap[$gedcomId] = $person->id;
                $this->importStats['individuals']++;
                
                Log::debug('Individual imported successfully', [
                    'gedcom_id' => $gedcomId,
                    'person_id' => $person->id,
                    'name' => $person->firstname . ' ' . $person->surname,
                ]);
                
            } catch (\Exception $e) {
                Log::warning('Failed to import individual', [
                    'gedcom_id' => $gedcomId,
                    'error' => $e->getMessage(),
                ]);
                $this->importStats['errors']++;
            }
        }

        Log::info('Individual import completed', [
            'imported' => $this->importStats['individuals'],
            'errors' => $this->importStats['errors'],
        ]);
    }

    /**
     * Import families (relationships) from GEDCOM
     */
    private function importFamilies(\PhpGedcom\Gedcom $gedcom, Team $team): void
    {
        $families = $gedcom->getFam();
        
        if (!$families || empty($families)) {
            Log::warning('No families found in GEDCOM file');
            return;
        }

        Log::info('Starting family import', ['count' => count($families)]);

        foreach ($families as $gedcomId => $family) {
            try {
                $couple = $this->createCoupleFromGedcom($family, $team);
                if ($couple) {
                    $this->importStats['families']++;
                    
                    Log::debug('Family imported successfully', [
                        'gedcom_id' => $gedcomId,
                        'couple_id' => $couple->id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to import family', [
                    'gedcom_id' => $gedcomId,
                    'error' => $e->getMessage(),
                ]);
                $this->importStats['errors']++;
            }
        }

        // Second pass: update parent-child relationships
        $this->updateParentChildRelationships($gedcom);

        Log::info('Family import completed', [
            'imported' => $this->importStats['families'],
            'total_errors' => $this->importStats['errors'],
        ]);
    }

    /**
     * Create Person model from GEDCOM Individual record
     * This is a placeholder - will be implemented in Phase 3
     */
    private function createPersonFromGedcom(Indi $individual, Team $team): Person
    {
        // Phase 3 implementation placeholder
        Log::info('Person creation placeholder called', [
            'individual_id' => $individual->getId(),
        ]);
        
        // Return minimal person for now
        return Person::create([
            'firstname' => 'GEDCOM',
            'surname' => 'Import',
            'team_id' => $team->id,
        ]);
    }

    /**
     * Create Couple model from GEDCOM Family record
     * This is a placeholder - will be implemented in Phase 4
     */
    private function createCoupleFromGedcom(Fam $family, Team $team): ?Couple
    {
        // Phase 4 implementation placeholder
        Log::info('Couple creation placeholder called', [
            'family_id' => $family->getId(),
        ]);
        
        return null; // Skip for now
    }

    /**
     * Update parent-child relationships after all individuals are imported
     * This is a placeholder - will be implemented in Phase 4
     */
    private function updateParentChildRelationships(\PhpGedcom\Gedcom $gedcom): void
    {
        // Phase 4 implementation placeholder
        Log::info('Parent-child relationship update placeholder called');
    }

    /**
     * Parse GEDCOM date string to Carbon date
     */
    protected function parseGedcomDate(?string $dateString): ?Carbon
    {
        if (!$dateString) {
            return null;
        }

        // Remove GEDCOM date prefixes
        $dateString = preg_replace('/^(ABT|EST|CAL|AFT|BEF|BET)\s+/', '', $dateString);
        $dateString = trim($dateString);

        try {
            // Try to parse common date formats
            if (preg_match('/(\d{1,2})\s+(\w{3})\s+(\d{4})/', $dateString, $matches)) {
                // Format: DD MON YYYY
                return Carbon::createFromFormat('j M Y', $dateString);
            }
            
            if (preg_match('/(\w{3})\s+(\d{4})/', $dateString, $matches)) {
                // Format: MON YYYY
                return Carbon::createFromFormat('M Y', $dateString)->startOfMonth();
            }
            
            if (preg_match('/(\d{4})/', $dateString, $matches)) {
                // Format: YYYY
                return Carbon::createFromFormat('Y', $matches[1])->startOfYear();
            }

            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            Log::warning('Failed to parse GEDCOM date', [
                'date_string' => $dateString,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Find a specific event type from the events array
     * This helper will be used in Phase 3 for birth/death event extraction
     */
    protected function findEventByType(array $events, string $eventClass): ?object
    {
        foreach ($events as $event) {
            if ($event instanceof $eventClass) {
                return $event;
            }
        }
        return null;
    }
}