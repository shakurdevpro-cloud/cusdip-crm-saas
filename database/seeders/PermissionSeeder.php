<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            'dashboard.view',

            // Leads
            'leads.view',
            'leads.create',
            'leads.update',
            'leads.delete',
            'leads.assign',
            'leads.convert',

            // Customers
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',

            // Opportunities
            'opportunities.view',
            'opportunities.create',
            'opportunities.update',
            'opportunities.delete',
            'opportunities.assign',
            'opportunities.close',

            // Activities
            'activities.view',
            'activities.create',
            'activities.update',
            'activities.delete',

            // Tasks
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.assign',

            // Reports
            'reports.view',

            // Documents
            'documents.view',
            'documents.upload',
            'documents.delete',

            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.deactivate',

            // Roles and permissions
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',

            // Company settings
            'company.settings.view',
            'company.settings.update',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
