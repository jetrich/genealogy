<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // User Management
            [
                'name' => 'admin.user_management.view',
                'description' => 'View user accounts and basic information',
                'category' => 'user_management',
                'is_sensitive' => false,
            ],
            [
                'name' => 'admin.user_management.create',
                'description' => 'Create new user accounts',
                'category' => 'user_management',
                'is_sensitive' => true,
            ],
            [
                'name' => 'admin.user_management.edit',
                'description' => 'Edit existing user accounts',
                'category' => 'user_management',
                'is_sensitive' => true,
            ],
            [
                'name' => 'admin.user_management.delete',
                'description' => 'Delete user accounts',
                'category' => 'user_management',
                'is_sensitive' => true,
            ],
            [
                'name' => 'admin.user_management.permissions',
                'description' => 'Grant and revoke user permissions',
                'category' => 'user_management',
                'is_sensitive' => true,
            ],

            // Team Management
            [
                'name' => 'admin.team_management.view',
                'description' => 'View team information and membership',
                'category' => 'team_management',
                'is_sensitive' => false,
            ],
            [
                'name' => 'admin.team_management.edit',
                'description' => 'Modify team settings and membership',
                'category' => 'team_management',
                'is_sensitive' => true,
            ],
            [
                'name' => 'admin.team_management.delete',
                'description' => 'Delete teams and transfer ownership',
                'category' => 'team_management',
                'is_sensitive' => true,
            ],

            // Cross-Team Access
            [
                'name' => 'admin.cross_team_access.people',
                'description' => 'Access genealogy data across all teams',
                'category' => 'cross_team_access',
                'is_sensitive' => true,
            ],
            [
                'name' => 'admin.cross_team_access.statistics',
                'description' => 'View cross-team statistics and analytics',
                'category' => 'cross_team_access',
                'is_sensitive' => false,
            ],
            [
                'name' => 'admin.cross_team_access.couples',
                'description' => 'Access couple relationships across all teams',
                'category' => 'cross_team_access',
                'is_sensitive' => true,
            ],

            // System Administration
            [
                'name' => 'admin.system.logs',
                'description' => 'View system logs and debugging information',
                'category' => 'system',
                'is_sensitive' => false,
            ],
            [
                'name' => 'admin.system.backup',
                'description' => 'Create and manage system backups',
                'category' => 'system',
                'is_sensitive' => true,
            ],
            [
                'name' => 'admin.system.settings',
                'description' => 'Modify system-wide settings and configuration',
                'category' => 'system',
                'is_sensitive' => true,
            ],
            [
                'name' => 'admin.system.maintenance',
                'description' => 'Perform system maintenance tasks',
                'category' => 'system',
                'is_sensitive' => true,
            ],

            // Developer Tools
            [
                'name' => 'admin.developer.database',
                'description' => 'Direct database access and queries',
                'category' => 'developer',
                'is_sensitive' => true,
            ],
            [
                'name' => 'admin.developer.debug',
                'description' => 'Access debugging tools and information',
                'category' => 'developer',
                'is_sensitive' => false,
            ],
            [
                'name' => 'admin.developer.cache',
                'description' => 'Manage application cache and optimization',
                'category' => 'developer',
                'is_sensitive' => false,
            ],

            // GEDCOM Management
            [
                'name' => 'admin.gedcom.import_export',
                'description' => 'Import and export GEDCOM files across teams',
                'category' => 'gedcom',
                'is_sensitive' => true,
            ],
            [
                'name' => 'admin.gedcom.bulk_operations',
                'description' => 'Perform bulk GEDCOM operations',
                'category' => 'gedcom',
                'is_sensitive' => true,
            ],

            // Media Management
            [
                'name' => 'admin.media.cross_team_access',
                'description' => 'Access and manage media files across all teams',
                'category' => 'media',
                'is_sensitive' => true,
            ],
            [
                'name' => 'admin.media.bulk_operations',
                'description' => 'Perform bulk media operations',
                'category' => 'media',
                'is_sensitive' => true,
            ],

            // Basic Genealogy Permissions (for regular users)
            [
                'name' => 'person:create',
                'description' => 'Create new people in genealogy data',
                'category' => 'genealogy',
                'is_sensitive' => false,
            ],
            [
                'name' => 'person:read',
                'description' => 'View people in genealogy data',
                'category' => 'genealogy',
                'is_sensitive' => false,
            ],
            [
                'name' => 'person:update',
                'description' => 'Edit people in genealogy data',
                'category' => 'genealogy',
                'is_sensitive' => false,
            ],
            [
                'name' => 'person:delete',
                'description' => 'Delete people from genealogy data',
                'category' => 'genealogy',
                'is_sensitive' => true,
            ],
            [
                'name' => 'couple:create',
                'description' => 'Create new couple relationships',
                'category' => 'genealogy',
                'is_sensitive' => false,
            ],
            [
                'name' => 'couple:read',
                'description' => 'View couple relationships',
                'category' => 'genealogy',
                'is_sensitive' => false,
            ],
            [
                'name' => 'couple:update',
                'description' => 'Edit couple relationships',
                'category' => 'genealogy',
                'is_sensitive' => false,
            ],
            [
                'name' => 'couple:delete',
                'description' => 'Delete couple relationships',
                'category' => 'genealogy',
                'is_sensitive' => true,
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}