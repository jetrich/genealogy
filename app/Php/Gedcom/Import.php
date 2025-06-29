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
use Illuminate\Support\Facades\Storage;

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
        $parser = new Parser();
        return $parser->parse($filePath);
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

        foreach ($individuals as $gedcomId => $individual) {
            try {
                $person = $this->createPersonFromGedcom($individual, $team);
                $this->gedcomPersonMap[$gedcomId] = $person->id;
                $this->importStats['individuals']++;
            } catch (\Exception $e) {
                Log::warning('Failed to import individual', [
                    'gedcom_id' => $gedcomId,
                    'error' => $e->getMessage(),
                ]);
                $this->importStats['errors']++;
            }
        }
    }

    /**
     * Create Person model from GEDCOM Individual record
     */
    private function createPersonFromGedcom(Indi $individual, Team $team): Person
    {
        // Extract basic name information
        $names = $individual->getName();
        $name = $names ? $names[0] : null;
        
        $firstname = '';
        $surname = '';
        
        if ($name) {
            $firstname = $name->getGivn() ?? '';
            $surname = $name->getSurn() ?? '';
        }

        // Extract birth information
        $birth = $individual->getBirt();
        $birthDate = null;
        $birthPlace = '';
        
        if ($birth && $birth[0]) {
            $birthDate = $this->parseGedcomDate($birth[0]->getDate());
            $birthPlace = $birth[0]->getPlac() ? $birth[0]->getPlac()->getPlac() : '';
        }

        // Extract death information
        $death = $individual->getDeat();
        $deathDate = null;
        $deathPlace = '';
        
        if ($death && $death[0]) {
            $deathDate = $this->parseGedcomDate($death[0]->getDate());
            $deathPlace = $death[0]->getPlac() ? $death[0]->getPlac()->getPlac() : '';
        }

        // Extract sex
        $sex = $individual->getSex();
        $gender = $this->mapGedcomSexToGender($sex);

        return Person::create([
            'firstname' => $firstname,
            'surname' => $surname,
            'sex' => $sex === 'M' ? 'M' : ($sex === 'F' ? 'F' : ''),
            'gender_id' => $gender,
            'dob' => $birthDate,
            'pob' => $birthPlace,
            'dod' => $deathDate,
            'pod' => $deathPlace,
            'team_id' => $team->id,
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

        foreach ($families as $gedcomId => $family) {
            try {
                $this->createCoupleFromGedcom($family, $team);
                $this->importStats['families']++;
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
    }

    /**
     * Create Couple model from GEDCOM Family record
     */
    private function createCoupleFromGedcom(Fam $family, Team $team): ?Couple
    {
        $husbandId = $family->getHusb();
        $wifeId = $family->getWife();

        // Need at least one spouse to create a couple
        if (!$husbandId && !$wifeId) {
            return null;
        }

        $person1Id = null;
        $person2Id = null;

        if ($husbandId && isset($this->gedcomPersonMap[$husbandId])) {
            $person1Id = $this->gedcomPersonMap[$husbandId];
        }

        if ($wifeId && isset($this->gedcomPersonMap[$wifeId])) {
            $person2Id = $this->gedcomPersonMap[$wifeId];
        }

        // Need at least one valid person
        if (!$person1Id && !$person2Id) {
            return null;
        }

        // Extract marriage information
        $marriage = $family->getMarr();
        $marriageDate = null;
        $isMarried = false;

        if ($marriage && $marriage[0]) {
            $marriageDate = $this->parseGedcomDate($marriage[0]->getDate());
            $isMarried = true;
        }

        // Extract divorce information
        $divorce = $family->getDiv();
        $divorceDate = null;
        $hasEnded = false;

        if ($divorce && $divorce[0]) {
            $divorceDate = $this->parseGedcomDate($divorce[0]->getDate());
            $hasEnded = true;
        }

        return Couple::create([
            'person1_id' => $person1Id,
            'person2_id' => $person2Id,
            'date_start' => $marriageDate,
            'date_end' => $divorceDate,
            'is_married' => $isMarried,
            'has_ended' => $hasEnded,
            'team_id' => $team->id,
        ]);
    }

    /**
     * Update parent-child relationships after all individuals are imported
     */
    private function updateParentChildRelationships(\PhpGedcom\Gedcom $gedcom): void
    {
        $families = $gedcom->getFam();
        
        if (!$families) {
            return;
        }

        foreach ($families as $family) {
            $husbandId = $family->getHusb();
            $wifeId = $family->getWife();
            $children = $family->getChil();

            if (!$children) {
                continue;
            }

            $fatherId = $husbandId && isset($this->gedcomPersonMap[$husbandId]) 
                ? $this->gedcomPersonMap[$husbandId] : null;
            $motherId = $wifeId && isset($this->gedcomPersonMap[$wifeId]) 
                ? $this->gedcomPersonMap[$wifeId] : null;

            foreach ($children as $childGedcomId) {
                if (isset($this->gedcomPersonMap[$childGedcomId])) {
                    $childId = $this->gedcomPersonMap[$childGedcomId];
                    
                    Person::where('id', $childId)->update([
                        'father_id' => $fatherId,
                        'mother_id' => $motherId,
                    ]);
                }
            }
        }
    }

    /**
     * Parse GEDCOM date string to Carbon date
     */
    private function parseGedcomDate(?string $dateString): ?Carbon
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
     * Map GEDCOM sex to application gender
     */
    private function mapGedcomSexToGender(?string $sex): ?int
    {
        // This would need to be updated based on the actual Gender model structure
        // For now, return null and let the application handle defaults
        return null;
    }
}
