<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Permission;
use Illuminate\Console\Command;

class GrantDefaultUserPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:grant-default-user-permissions {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant default permissions to users who need them for GEDCOM imports and basic genealogy operations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        
        // Find developer user who can grant permissions
        $developer = User::where('is_developer', true)->first();
        if (!$developer) {
            $developer = User::find(1);
        }
        
        if (!$developer) {
            $this->error('No developer user found to grant permissions');
            return 1;
        }

        // Define essential permissions for genealogy operations
        $essentialPermissions = [
            'person:create' => 'Essential for GEDCOM imports and adding people',
            'person:read' => 'Essential for viewing genealogy data',
            'person:update' => 'Essential for editing people',
            'couple:create' => 'Essential for creating family relationships',
            'couple:read' => 'Essential for viewing relationships',
            'couple:update' => 'Essential for editing relationships',
        ];

        // Get all regular users (non-developers) who don't have person:create permission
        $usersNeedingPermissions = User::where('is_developer', false)
            ->whereDoesntHave('permissions', function ($query) {
                $query->where('name', 'person:create');
            })
            ->get();

        if ($usersNeedingPermissions->isEmpty()) {
            $this->info('All users already have the necessary permissions.');
            return 0;
        }

        $this->info("Found {$usersNeedingPermissions->count()} users who need default permissions.");
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->table(['User ID', 'Name', 'Email', 'Missing Permissions'], 
                $usersNeedingPermissions->map(function ($user) use ($essentialPermissions) {
                    $missingPermissions = [];
                    foreach (array_keys($essentialPermissions) as $permission) {
                        if (!$user->hasPermission($permission)) {
                            $missingPermissions[] = $permission;
                        }
                    }
                    return [
                        $user->id,
                        $user->name,
                        $user->email,
                        implode(', ', $missingPermissions)
                    ];
                })->toArray()
            );
            return 0;
        }

        // Confirm before making changes
        if (!$this->confirm('Grant default permissions to these users?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($usersNeedingPermissions as $user) {
            $this->line("Processing user: {$user->name} ({$user->email})");
            
            foreach ($essentialPermissions as $permission => $justification) {
                try {
                    // Check if permission exists in the system
                    if (!Permission::where('name', $permission)->exists()) {
                        $this->warn("  Permission '{$permission}' does not exist in system - skipping");
                        continue;
                    }

                    // Check if user already has this permission
                    if ($user->hasPermission($permission)) {
                        $this->line("  Already has: {$permission}");
                        continue;
                    }

                    // Grant the permission
                    $user->grantPermission($permission, $developer, $justification);
                    $this->info("  ✓ Granted: {$permission}");
                    
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed to grant {$permission}: " . $e->getMessage());
                    $errorCount++;
                }
            }
            
            $successCount++;
        }

        $this->newLine();
        $this->info("✓ Successfully processed {$successCount} users");
        
        if ($errorCount > 0) {
            $this->warn("⚠ {$errorCount} errors occurred during processing");
        }

        $this->info('Users can now perform GEDCOM imports and basic genealogy operations.');
        
        return 0;
    }
}