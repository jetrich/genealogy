<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Permission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateToPermissionSystem extends Command
{
    protected $signature = 'permissions:migrate-developers {--dry-run : Show what would be done without making changes} {--force : Skip confirmation prompts}';
    protected $description = 'Migrate existing developers to new permission system';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Check if permissions exist
        $permissionCount = Permission::count();
        if ($permissionCount === 0) {
            $this->error('❌ No permissions found in database. Run php artisan db:seed --class=PermissionSeeder first.');
            return 1;
        }
        
        $this->info("✅ Found {$permissionCount} permissions in database");

        $developers = User::where('is_developer', true)->get();
        
        if ($developers->isEmpty()) {
            $this->info('ℹ️  No developers found with is_developer=true flag');
            return 0;
        }
        
        $this->info("🔍 Found {$developers->count()} developers to migrate:");
        foreach ($developers as $dev) {
            $this->line("   - {$dev->name} ({$dev->email})");
        }
        $this->newLine();

        if (!$force && !$dryRun) {
            if (!$this->confirm('Do you want to proceed with the migration?')) {
                $this->info('Migration cancelled');
                return 0;
            }
        }

        // Comprehensive permission set for developers
        $developerPermissions = [
            // User Management
            'admin.user_management.view',
            'admin.user_management.create',
            'admin.user_management.edit',
            'admin.user_management.delete',
            'admin.user_management.permissions',
            
            // Team Management
            'admin.team_management.view',
            'admin.team_management.edit',
            'admin.team_management.delete',
            
            // Cross-Team Access
            'admin.cross_team_access.people',
            'admin.cross_team_access.statistics',
            'admin.cross_team_access.couples',
            
            // System Administration
            'admin.system.logs',
            'admin.system.backup',
            'admin.system.settings',
            'admin.system.maintenance',
            
            // Developer Tools
            'admin.developer.database',
            'admin.developer.debug',
            'admin.developer.cache',
            
            // GEDCOM Management
            'admin.gedcom.import_export',
            'admin.gedcom.bulk_operations',
            
            // Media Management
            'admin.media.cross_team_access',
            'admin.media.bulk_operations',
        ];

        $this->info('📋 Permissions to be granted:');
        foreach ($developerPermissions as $permission) {
            $this->line("   ✓ {$permission}");
        }
        $this->newLine();

        $successCount = 0;
        $errorCount = 0;

        foreach ($developers as $developer) {
            $this->info("🔄 Migrating developer: {$developer->name} ({$developer->email})");
            
            $userSuccessCount = 0;
            $userErrorCount = 0;
            
            if (!$dryRun) {
                DB::beginTransaction();
                try {
                    foreach ($developerPermissions as $permissionName) {
                        $permission = Permission::where('name', $permissionName)->first();
                        
                        if (!$permission) {
                            $this->warn("   ⚠️  Permission not found: {$permissionName}");
                            $userErrorCount++;
                            continue;
                        }
                        
                        // Check if user already has this permission
                        if ($developer->hasPermission($permissionName)) {
                            $this->line("   - Already has: {$permissionName}");
                            continue;
                        }
                        
                        try {
                            $developer->grantPermission(
                                $permissionName,
                                $developer, // Self-granted during migration
                                'Migrated from is_developer flag during system upgrade to granular permissions'
                            );
                            $this->line("   ✅ Granted: {$permissionName}");
                            $userSuccessCount++;
                        } catch (\Exception $e) {
                            $this->error("   ❌ Failed to grant {$permissionName}: " . $e->getMessage());
                            $userErrorCount++;
                        }
                    }
                    
                    if ($userErrorCount === 0) {
                        DB::commit();
                        $this->info("   ✅ Successfully migrated {$userSuccessCount} permissions");
                        $successCount++;
                    } else {
                        DB::rollBack();
                        $this->error("   ❌ Migration failed due to {$userErrorCount} errors");
                        $errorCount++;
                    }
                    
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("   ❌ Transaction failed: " . $e->getMessage());
                    $errorCount++;
                }
            } else {
                foreach ($developerPermissions as $permissionName) {
                    $permission = Permission::where('name', $permissionName)->first();
                    
                    if (!$permission) {
                        $this->warn("   ⚠️  Permission not found: {$permissionName}");
                        continue;
                    }
                    
                    if ($developer->hasPermission($permissionName)) {
                        $this->line("   - Already has: {$permissionName}");
                    } else {
                        $this->line("   → Would grant: {$permissionName}");
                    }
                }
                $successCount++;
            }
            
            $this->newLine();
        }

        // Summary
        $this->info('📊 Migration Summary:');
        $this->line("   ✅ Successful: {$successCount} developers");
        if ($errorCount > 0) {
            $this->line("   ❌ Failed: {$errorCount} developers");
        }
        $this->newLine();

        if (!$dryRun && $successCount > 0) {
            $this->info('✅ Migration completed successfully!');
            $this->newLine();
            $this->warn('🔔 IMPORTANT NEXT STEPS:');
            $this->line('   1. Test the new permission system thoroughly');
            $this->line('   2. Update your routes to use the new HasPermission middleware');
            $this->line('   3. Consider setting is_developer=false for migrated users once testing is complete');
            $this->line('   4. Review and customize permissions as needed for each user');
            $this->newLine();
        } elseif ($dryRun) {
            $this->info('🔍 Dry run complete.');
            $this->line('   Run without --dry-run to execute the migration.');
        }

        return $errorCount > 0 ? 1 : 0;
    }
}