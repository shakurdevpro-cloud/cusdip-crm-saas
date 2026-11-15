<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CompanyRoleService
{
    /**
     * Create the default roles and permissions for a company.
     */

public function createDefaultRoles(Company $company): void
{
    $registrar = app(PermissionRegistrar::class);

    // Preserve the previous company context.
    $previousTeamId = $registrar->getPermissionsTeamId();

    try {
        $registrar->setPermissionsTeamId($company->id);

        DB::transaction(function () use ($company) {
            $rolePermissions = $this->getDefaultRolePermissions();

            foreach ($rolePermissions as $roleName => $permissions) {
                $role = Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'company_id' => $company->id,
                ]);

                $permissionModels = Permission::query()
                    ->where('guard_name', 'web')
                    ->whereIn('name', $permissions)
                    ->get();

                if ($permissionModels->count() !== count($permissions)) {
                    throw new \RuntimeException(
                        "Some permissions are missing for role: {$roleName}."
                    );
                }

                $role->syncPermissions($permissionModels);
            }
        });
    } finally {
        // Restore the previous context, even if an exception occurs.
        $registrar->setPermissionsTeamId($previousTeamId);
    }
}


    /**
     * Define the default permissions for each company role.
     */
    protected function getDefaultRolePermissions(): array
    {
        return [
            'Admin' => [
                'dashboard.view',

                'leads.view',
                'leads.create',
                'leads.update',
                'leads.delete',
                'leads.assign',
                'leads.convert',

                'customers.view',
                'customers.create',
                'customers.update',
                'customers.delete',

                'opportunities.view',
                'opportunities.create',
                'opportunities.update',
                'opportunities.delete',
                'opportunities.assign',
                'opportunities.close',

                'activities.view',
                'activities.create',
                'activities.update',
                'activities.delete',

                'tasks.view',
                'tasks.create',
                'tasks.update',
                'tasks.delete',
                'tasks.assign',

                'reports.view',

                'documents.view',
                'documents.upload',
                'documents.delete',

                'users.view',
                'users.create',
                'users.update',
                'users.deactivate',

                'roles.view',
                'roles.create',
                'roles.update',
                'roles.delete',

                'company.settings.view',
                'company.settings.update',
            ],

            'Sales Manager' => [
                'dashboard.view',

                'leads.view',
                'leads.create',
                'leads.update',
                'leads.delete',
                'leads.assign',
                'leads.convert',

                'customers.view',
                'customers.create',
                'customers.update',

                'opportunities.view',
                'opportunities.create',
                'opportunities.update',
                'opportunities.assign',
                'opportunities.close',

                'activities.view',
                'activities.create',
                'activities.update',
                'activities.delete',

                'tasks.view',
                'tasks.create',
                'tasks.update',
                'tasks.delete',
                'tasks.assign',

                'reports.view',

                'documents.view',
                'documents.upload',
                'documents.delete',

                'users.view',
            ],

            'Sales Agent' => [
                'dashboard.view',

                'leads.view',
                'leads.create',
                'leads.update',
                'leads.convert',

                'customers.view',
                'customers.create',
                'customers.update',

                'opportunities.view',
                'opportunities.create',
                'opportunities.update',
                'opportunities.close',

                'activities.view',
                'activities.create',
                'activities.update',

                'tasks.view',
                'tasks.create',
                'tasks.update',

                'documents.view',
                'documents.upload',
            ],
        ];
    }

}
