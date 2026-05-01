<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use XetaSuite\Models\Permission;
use XetaSuite\Models\Role;
use XetaSuite\Models\Site;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = require database_path('seeders/data/permissions.php');

        // Create / sync permissions (global, not team-specific)
        foreach ($permissions as $name => $description) {
            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }

        // Define roles and their permissions
        $rolesDefinition = [
            'admin' => [
                'permissions' => array_keys($permissions),
                'level' => 100,
            ],
            'manager' => [
                'permissions' => [
                    'user.view',

                    'material.viewAny',
                    'material.view',
                    'material.create',
                    'material.update',
                    'material.delete',
                    'material.export',
                    'material.generateQrCode',
                    'material.scanQrCode',

                    'zone.viewAny',
                    'zone.view',
                    'zone.create',
                    'zone.update',
                    'zone.delete',

                     'cleaning.viewAny',
                    'cleaning.view',
                    'cleaning.create',
                    'cleaning.update',
                    'cleaning.delete',
                    'cleaning.export',
                    'cleaning.generatePlan',

                    'maintenance.viewAny',
                    'maintenance.view',
                    'maintenance.create',
                    'maintenance.update',
                    'maintenance.delete',
                    'maintenance.export',

                    'incident.viewAny',
                    'incident.view',
                    'incident.create',
                    'incident.update',
                    'incident.delete',
                    'incident.export',

                    'item.viewAny',
                    'item.view',
                    'item.create',
                    'item.update',
                    'item.delete',
                    'item.export',
                    'item.generateQrCode',
                    'item.scanQrCode',
                    'item.viewOthersSites',

                    'item-movement.viewAny',
                    'item-movement.view',
                    'item-movement.create',
                    'item-movement.update',
                    'item-movement.delete',
                    'item-movement.export',
                    'item-movement.viewOthersSites',

                    'assistant.use',
                ],
                'level' => 50,
            ],

            'operator' => [
                'permissions' => [
                    'material.viewAny',
                    'material.view',

                    'zone.viewAny',
                    'zone.view',

                     'cleaning.viewAny',
                    'cleaning.view',
                    'cleaning.create',
                    'cleaning.update',

                    'maintenance.viewAny',
                    'maintenance.view',
                    'maintenance.create',
                    'maintenance.update',

                    'incident.viewAny',
                    'incident.view',
                    'incident.create',
                    'incident.update',

                    'item.viewAny',
                    'item.view',
                    'item.scanQrCode',

                    'item-movement.viewAny',
                    'item-movement.view',
                    'item-movement.create',
                    'item-movement.update',

                    'assistant.use',
                ],
                'level' => 10,
            ],
        ];

        // Get all sites
        $sites = Site::all();

        // Create roles for each site
        foreach ($sites as $site) {
            foreach ($rolesDefinition as $roleName => $roleSettings) {
                $role = Role::updateOrCreate(
                    [
                        'name' => $roleName,
                        'site_id' => null,
                        'guard_name' => 'web',
                        'level' => $roleSettings['level'],
                    ],
                    ['description' => ucfirst($roleName).' role for '.$site->name]
                );

                // Sync permissions for this role and site
                $role->syncPermissions($roleSettings['permissions']);
            }
        }
    }
}
