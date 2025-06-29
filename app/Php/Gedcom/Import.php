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
     */
    private function createPersonFromGedcom(Indi $individual, Team $team): Person
    {
        $gedcomId = $individual->getId();
        
        Log::debug('Creating person from GEDCOM individual', [
            'gedcom_id' => $gedcomId,
            'team_id' => $team->id,
        ]);
        
        // Extract names from GEDCOM
        $names = $this->extractNamesFromGedcom($individual);
        
        // Extract birth information
        $birthInfo = $this->extractBirthInfoFromGedcom($individual);
        
        // Extract death information  
        $deathInfo = $this->extractDeathInfoFromGedcom($individual);
        
        // Extract sex/gender
        $sex = $this->extractSexFromGedcom($individual);
        
        // Create Person record
        $personData = [
            'team_id' => $team->id,
            'firstname' => $names['firstname'],
            'surname' => $names['surname'],
            'birthname' => $names['birthname'],
            'nickname' => $names['nickname'],
            'sex' => $sex,
        ];
        
        // Add birth data if available
        if ($birthInfo['dob']) {
            $personData['dob'] = $birthInfo['dob'];
        } elseif ($birthInfo['yob']) {
            $personData['yob'] = $birthInfo['yob'];
        }
        if ($birthInfo['pob']) {
            $personData['pob'] = $birthInfo['pob'];
        }
        
        // Add death data if available
        if ($deathInfo['dod']) {
            $personData['dod'] = $deathInfo['dod'];
        } elseif ($deathInfo['yod']) {
            $personData['yod'] = $deathInfo['yod'];
        }
        if ($deathInfo['pod']) {
            $personData['pod'] = $deathInfo['pod'];
        }
        
        $person = Person::create($personData);
        
        Log::debug('Person created successfully', [
            'person_id' => $person->id,
            'gedcom_id' => $gedcomId,
            'name' => $person->name,
            'birth_year' => $person->birthYear,
            'death_year' => $person->deathYear,
        ]);
        
        return $person;
    }

    /**
     * Create Couple model from GEDCOM Family record
     */
    private function createCoupleFromGedcom(Fam $family, Team $team): ?Couple
    {
        $familyId = $family->getId();
        
        Log::debug('Creating couple from GEDCOM family', [
            'gedcom_family_id' => $familyId,
            'team_id' => $team->id,
        ]);
        
        // Extract spouse information
        $husbandGedcomId = $family->getHusb();
        $wifeGedcomId = $family->getWife();
        
        if (!$husbandGedcomId && !$wifeGedcomId) {
            Log::warning('Family has no spouses defined', ['family_id' => $familyId]);
            return null;
        }
        
        // Map GEDCOM IDs to Person IDs
        $person1Id = $husbandGedcomId ? ($this->gedcomPersonMap[$husbandGedcomId] ?? null) : null;
        $person2Id = $wifeGedcomId ? ($this->gedcomPersonMap[$wifeGedcomId] ?? null) : null;
        
        if (!$person1Id && !$person2Id) {
            Log::warning('Could not find persons for family', [
                'family_id' => $familyId,
                'husband_gedcom_id' => $husbandGedcomId,
                'wife_gedcom_id' => $wifeGedcomId,
            ]);
            return null;
        }
        
        // Extract marriage information
        $marriageInfo = $this->extractMarriageInfoFromGedcom($family);
        
        // Create couple record
        $coupleData = [
            'team_id' => $team->id,
            'person1_id' => $person1Id,
            'person2_id' => $person2Id,
            'is_married' => $marriageInfo['is_married'],
            'has_ended' => $marriageInfo['has_ended'],
        ];
        
        if ($marriageInfo['date_start']) {
            $coupleData['date_start'] = $marriageInfo['date_start'];
        }
        if ($marriageInfo['date_end']) {
            $coupleData['date_end'] = $marriageInfo['date_end'];
        }
        
        $couple = Couple::create($coupleData);
        
        Log::debug('Couple created successfully', [
            'couple_id' => $couple->id,
            'gedcom_family_id' => $familyId,
            'person1_id' => $person1Id,
            'person2_id' => $person2Id,
            'is_married' => $marriageInfo['is_married'],
        ]);
        
        return $couple;
    }

    /**
     * Update parent-child relationships after all individuals are imported
     */
    private function updateParentChildRelationships(\PhpGedcom\Gedcom $gedcom): void
    {
        Log::info('Starting parent-child relationship update');
        
        $families = $gedcom->getFam();
        if (!$families || empty($families)) {
            Log::info('No families found for parent-child relationships');
            return;
        }
        
        $relationshipsUpdated = 0;
        
        foreach ($families as $gedcomId => $family) {
            try {
                $this->updateChildrenForFamily($family);
                $relationshipsUpdated++;
                
            } catch (\Exception $e) {
                Log::warning('Failed to update family relationships', [
                    'gedcom_family_id' => $gedcomId,
                    'error' => $e->getMessage(),
                ]);
                $this->importStats['errors']++;
            }
        }
        
        Log::info('Parent-child relationship update completed', [
            'families_processed' => $relationshipsUpdated,
            'total_errors' => $this->importStats['errors'],
        ]);
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
     * Extract names from GEDCOM Individual record
     */
    private function extractNamesFromGedcom(Indi $individual): array
    {
        $names = [
            'firstname' => null,
            'surname' => null,
            'birthname' => null,
            'nickname' => null,
        ];
        
        $nameRecords = $individual->getName();
        if (empty($nameRecords)) {
            return $names;
        }
        
        // Use first name record as primary
        $primaryName = $nameRecords[0];
        $fullName = $primaryName->getName();
        
        if ($fullName) {
            // Parse GEDCOM name format: "Given /Surname/" or "Given /Surname/ Suffix"
            if (preg_match('/^([^\/]*)\s*\/([^\/]*)\/', $fullName, $matches)) {
                $names['firstname'] = trim($matches[1]) ?: null;
                $names['surname'] = trim($matches[2]) ?: null;
            } else {
                // Fallback: treat as firstname if no surname delimiters
                $names['firstname'] = trim($fullName) ?: null;
            }
        }
        
        // Extract other name components
        if ($primaryName->getGivn()) {
            $names['firstname'] = $primaryName->getGivn();
        }
        if ($primaryName->getSurn()) {
            $names['surname'] = $primaryName->getSurn();
        }
        if ($primaryName->getNick()) {
            $names['nickname'] = $primaryName->getNick();
        }
        
        // Look for birth name in other name records
        foreach ($nameRecords as $nameRecord) {
            if ($nameRecord->getType() === 'BIRTH' && $nameRecord->getSurn()) {
                $names['birthname'] = $nameRecord->getSurn();
                break;
            }
        }
        
        return $names;
    }
    
    /**
     * Extract birth information from GEDCOM Individual record
     */
    private function extractBirthInfoFromGedcom(Indi $individual): array
    {
        $birthInfo = [
            'dob' => null,
            'yob' => null,
            'pob' => null,
        ];
        
        $events = $individual->getEven();
        if (empty($events)) {
            return $birthInfo;
        }
        
        $birthEvent = $this->findEventByType($events, 'PhpGedcom\\Record\\Indi\\Birt');
        if (!$birthEvent) {
            return $birthInfo;
        }
        
        // Extract birth date
        if ($birthEvent->getDate()) {
            $parsedDate = $this->parseGedcomDate($birthEvent->getDate());
            if ($parsedDate) {
                $birthInfo['dob'] = $parsedDate->format('Y-m-d');
                $birthInfo['yob'] = (int) $parsedDate->format('Y');
            }
        }
        
        // Extract birth place
        if ($birthEvent->getPlac() && $birthEvent->getPlac()->getPlac()) {
            $birthInfo['pob'] = $birthEvent->getPlac()->getPlac();
        }
        
        return $birthInfo;
    }
    
    /**
     * Extract death information from GEDCOM Individual record
     */
    private function extractDeathInfoFromGedcom(Indi $individual): array
    {
        $deathInfo = [
            'dod' => null,
            'yod' => null,
            'pod' => null,
        ];
        
        $events = $individual->getEven();
        if (empty($events)) {
            return $deathInfo;
        }
        
        $deathEvent = $this->findEventByType($events, 'PhpGedcom\\Record\\Indi\\Deat');
        if (!$deathEvent) {
            return $deathInfo;
        }
        
        // Extract death date
        if ($deathEvent->getDate()) {
            $parsedDate = $this->parseGedcomDate($deathEvent->getDate());
            if ($parsedDate) {
                $deathInfo['dod'] = $parsedDate->format('Y-m-d');
                $deathInfo['yod'] = (int) $parsedDate->format('Y');
            }
        }
        
        // Extract death place
        if ($deathEvent->getPlac() && $deathEvent->getPlac()->getPlac()) {
            $deathInfo['pod'] = $deathEvent->getPlac()->getPlac();
        }
        
        return $deathInfo;
    }
    
    /**
     * Extract sex/gender from GEDCOM Individual record
     */
    private function extractSexFromGedcom(Indi $individual): ?string
    {
        $sex = $individual->getSex();
        
        if (!$sex) {
            return null;
        }
        
        // Convert GEDCOM sex codes to Laravel application format
        switch (strtoupper($sex)) {
            case 'M':
                return 'M';
            case 'F':
                return 'F';
            case 'U':
            case 'X':
                return 'X'; // Unknown/Other
            default:
                return null;
        }
    }

    /**
     * Extract marriage information from GEDCOM Family record
     */
    private function extractMarriageInfoFromGedcom(Fam $family): array
    {
        $marriageInfo = [
            'is_married' => false,
            'has_ended' => false,
            'date_start' => null,
            'date_end' => null,
        ];
        
        $events = $family->getEven();
        if (empty($events)) {
            return $marriageInfo;
        }
        
        // Look for marriage event
        $marriageEvent = $this->findEventByType($events, 'PhpGedcom\\Record\\Fam\\Marr');
        if ($marriageEvent) {
            $marriageInfo['is_married'] = true;
            
            if ($marriageEvent->getDate()) {
                $parsedDate = $this->parseGedcomDate($marriageEvent->getDate());
                if ($parsedDate) {
                    $marriageInfo['date_start'] = $parsedDate->format('Y-m-d');
                }
            }
        }
        
        // Look for divorce events
        $divorceEvent = $this->findEventByType($events, 'PhpGedcom\\Record\\Fam\\Div');
        if ($divorceEvent) {
            $marriageInfo['has_ended'] = true;
            
            if ($divorceEvent->getDate()) {
                $parsedDate = $this->parseGedcomDate($divorceEvent->getDate());
                if ($parsedDate) {
                    $marriageInfo['date_end'] = $parsedDate->format('Y-m-d');
                }
            }
        }
        
        return $marriageInfo;
    }
    
    /**
     * Update children for a specific family
     */
    private function updateChildrenForFamily(Fam $family): void
    {
        $familyId = $family->getId();
        $children = $family->getChil();
        
        if (!$children || empty($children)) {
            Log::debug('No children found for family', ['family_id' => $familyId]);
            return;
        }
        
        // Get parent information
        $husbandGedcomId = $family->getHusb();
        $wifeGedcomId = $family->getWife();
        
        $fatherId = $husbandGedcomId ? ($this->gedcomPersonMap[$husbandGedcomId] ?? null) : null;
        $motherId = $wifeGedcomId ? ($this->gedcomPersonMap[$wifeGedcomId] ?? null) : null;
        
        // Find the couple record for this family (parents_id reference)
        $coupleId = null;
        if ($fatherId && $motherId) {
            $couple = Couple::where('person1_id', $fatherId)
                ->where('person2_id', $motherId)
                ->orWhere(function ($query) use ($fatherId, $motherId) {
                    $query->where('person1_id', $motherId)
                          ->where('person2_id', $fatherId);
                })
                ->first();
            
            if ($couple) {
                $coupleId = $couple->id;
            }
        }
        
        // Update each child
        foreach ($children as $childGedcomId) {
            $childPersonId = $this->gedcomPersonMap[$childGedcomId] ?? null;
            
            if (!$childPersonId) {
                Log::warning('Child person not found in mapping', [
                    'family_id' => $familyId,
                    'child_gedcom_id' => $childGedcomId,
                ]);
                continue;
            }
            
            $updateData = [];
            
            // Set individual parent references
            if ($fatherId) {
                $updateData['father_id'] = $fatherId;
            }
            if ($motherId) {
                $updateData['mother_id'] = $motherId;
            }
            
            // Set couple reference if both parents exist
            if ($coupleId) {
                $updateData['parents_id'] = $coupleId;
            }
            
            if (!empty($updateData)) {
                Person::where('id', $childPersonId)->update($updateData);
                
                Log::debug('Child relationships updated', [
                    'child_person_id' => $childPersonId,
                    'father_id' => $fatherId,
                    'mother_id' => $motherId,
                    'parents_id' => $coupleId,
                ]);
            }
        }
    }

    /**
     * Find a specific event type from the events array
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