<?php

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyMembershipService;
use App\Services\CompanyRoleService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompanyRoleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CompanyMembershipService $membershipService;

    protected CompanyRoleService $roleService;

    protected function setUp(): void
    {
        parent::setUp();

        // Load the permissions required by the role service.
        $this->seed(PermissionSeeder::class);

        $this->membershipService = app(CompanyMembershipService::class);
        $this->roleService = app(CompanyRoleService::class);
    }

    public function test_it_creates_the_three_default_roles_for_a_company(): void
    {
        $user = User::factory()->create();

        $company = $this->membershipService->createCompanyFor(
            $user,
            ['name' => 'Acme']
        );

        $this->roleService->createDefaultRoles($company);

        foreach (['Admin', 'Sales Manager', 'Sales Agent'] as $roleName) {
            $this->assertDatabaseHas('roles', [
                'name' => $roleName,
                'guard_name' => 'web',
                'company_id' => $company->id,
            ]);
        }

        $this->assertSame(
            3,
            Role::where('company_id', $company->id)->count()
        );
    }

    public function test_it_assigns_the_expected_permissions_to_each_role(): void
    {
        $user = User::factory()->create();

        $company = $this->membershipService->createCompanyFor(
            $user,
            ['name' => 'Acme']
        );

        $this->roleService->createDefaultRoles($company);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($company->id);

        try {
            $admin = Role::findByName('Admin', 'web');
            $manager = Role::findByName('Sales Manager', 'web');
            $agent = Role::findByName('Sales Agent', 'web');

            $this->assertTrue($admin->hasPermissionTo('company.settings.update'));
            $this->assertTrue($admin->hasPermissionTo('roles.create'));

            $this->assertTrue($manager->hasPermissionTo('reports.view'));
            $this->assertFalse($manager->hasPermissionTo('company.settings.update'));
            $this->assertFalse($manager->hasPermissionTo('roles.create'));

            $this->assertTrue($agent->hasPermissionTo('leads.create'));
            $this->assertFalse($agent->hasPermissionTo('leads.delete'));
            $this->assertFalse($agent->hasPermissionTo('users.create'));
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }

    public function test_it_creates_roles_separately_for_each_company(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $firstCompany = $this->membershipService->createCompanyFor(
            $firstUser,
            ['name' => 'First Company']
        );

        $secondCompany = $this->membershipService->createCompanyFor(
            $secondUser,
            ['name' => 'Second Company']
        );

        $this->roleService->createDefaultRoles($firstCompany);
        $this->roleService->createDefaultRoles($secondCompany);

        $this->assertDatabaseHas('roles', [
            'name' => 'Admin',
            'company_id' => $firstCompany->id,
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Admin',
            'company_id' => $secondCompany->id,
        ]);

        $this->assertSame(
            3,
            Role::where('company_id', $firstCompany->id)->count()
        );

        $this->assertSame(
            3,
            Role::where('company_id', $secondCompany->id)->count()
        );
    }

    public function test_it_does_not_create_duplicate_roles_when_executed_twice(): void
    {
        $user = User::factory()->create();

        $company = $this->membershipService->createCompanyFor(
            $user,
            ['name' => 'Acme']
        );

        $this->roleService->createDefaultRoles($company);
        $this->roleService->createDefaultRoles($company);

        $this->assertSame(
            3,
            Role::where('company_id', $company->id)->count()
        );
    }

    public function test_it_restores_the_previous_spatie_team_context(): void
    {
        $user = User::factory()->create();

        $firstCompany = $this->membershipService->createCompanyFor(
            $user,
            ['name' => 'First Company']
        );

        $secondCompany = $this->membershipService->createCompanyFor(
            $user,
            ['name' => 'Second Company']
        );

        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId($firstCompany->id);

        $this->roleService->createDefaultRoles($secondCompany);

        $this->assertSame(
            $firstCompany->id,
            $registrar->getPermissionsTeamId()
        );

        $registrar->setPermissionsTeamId(null);
    }

    public function test_it_rolls_back_role_creation_when_a_permission_is_missing(): void
    {
        // Create a company without automatically creating its default roles.
        $company = Company::create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        // Remove one required permission.
        DB::table('permissions')
            ->where('name', 'company.settings.update')
            ->where('guard_name', 'web')
            ->delete();

        // The service must throw an exception and roll back role creation.
        $this->expectException(RuntimeException::class);

        try {
            $this->roleService->createDefaultRoles($company);
        } finally {
            $this->assertSame(
                0,
                Role::where('company_id', $company->id)->count()
            );
        }
    }

    public function test_it_assigns_a_role_to_a_user_in_a_company(): void
    {
        $user = User::factory()->create();

        $company = $this->membershipService->createCompanyFor(
            $user,
            ['name' => 'Acme']
        );

        $this->roleService->createDefaultRoles($company);

        $role = $this->roleService->syncRoleForUser(
            $user,
            $company,
            'Admin'
        );

        $this->assertSame('Admin', $role->name);
        $this->assertSame($company->id, $role->company_id);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($company->id);

        try {
            $user->unsetRelation('roles');

            $this->assertTrue($user->hasRole('Admin'));
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }

    public function test_it_replaces_the_user_role_in_the_same_company(): void
    {
        $user = User::factory()->create();

        $company = $this->membershipService->createCompanyFor(
            $user,
            ['name' => 'Acme']
        );

        $this->roleService->createDefaultRoles($company);

        $this->roleService->syncRoleForUser($user, $company, 'Admin');

        $this->roleService->syncRoleForUser($user, $company, 'Sales Agent');

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($company->id);

        try {
            $user->unsetRelation('roles');

            $this->assertTrue($user->hasRole('Sales Agent'));
            $this->assertFalse($user->hasRole('Admin'));
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }

    public function test_it_preserves_user_roles_across_different_companies(): void
    {
        $user = User::factory()->create();

        $firstCompany = $this->membershipService->createCompanyFor(
            $user,
            ['name' => 'First Company']
        );

        $secondCompany = $this->membershipService->createCompanyFor(
            $user,
            ['name' => 'Second Company']
        );

        $this->roleService->createDefaultRoles($firstCompany);
        $this->roleService->createDefaultRoles($secondCompany);

        $this->roleService->syncRoleForUser(
            $user,
            $firstCompany,
            'Admin'
        );

        $this->roleService->syncRoleForUser(
            $user,
            $secondCompany,
            'Sales Agent'
        );

        $registrar = app(PermissionRegistrar::class);

        try {
            // Verify the role in the first company.
            $registrar->setPermissionsTeamId($firstCompany->id);
            $user->unsetRelation('roles');

            $this->assertTrue($user->hasRole('Admin'));
            $this->assertFalse($user->hasRole('Sales Agent'));

            // Verify the role in the second company.
            $registrar->setPermissionsTeamId($secondCompany->id);
            $user->unsetRelation('roles');

            $this->assertTrue($user->hasRole('Sales Agent'));
            $this->assertFalse($user->hasRole('Admin'));
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }

    public function test_it_rejects_role_assignment_without_active_membership(): void
    {
        $companyOwner = User::factory()->create();
        $otherUser = User::factory()->create();

        $company = $this->membershipService->createCompanyFor(
            $companyOwner,
            ['name' => 'Acme']
        );

        $this->roleService->createDefaultRoles($company);

        $this->expectException(AuthorizationException::class);

        $this->roleService->syncRoleForUser(
            $otherUser,
            $company,
            'Admin'
        );
    }

    public function test_it_rejects_a_role_that_does_not_belong_to_the_company(): void
    {
        $user = User::factory()->create();

        $firstCompany = $this->membershipService->createCompanyFor(
            $user,
            ['name' => 'First Company']
        );

        $secondUser = User::factory()->create();

        $secondCompany = $this->membershipService->createCompanyFor(
            $secondUser,
            ['name' => 'Second Company']
        );

        // Remove the Admin role from the first company.
        Role::query()
            ->where('company_id', $firstCompany->id)
            ->where('name', 'Admin')
            ->delete();

        // The Admin role still exists, but only for the second company.
        $this->assertDatabaseHas('roles', [
            'name' => 'Admin',
            'company_id' => $secondCompany->id,
        ]);

        $this->assertDatabaseMissing('roles', [
            'name' => 'Admin',
            'company_id' => $firstCompany->id,
        ]);

        $this->expectException(AuthorizationException::class);

        $this->roleService->syncRoleForUser(
            $user,
            $firstCompany,
            'Admin'
        );
    }
}
