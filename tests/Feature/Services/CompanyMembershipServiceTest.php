<?php

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyMembershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Database\Seeders\PermissionSeeder;
use RuntimeException;
use Spatie\Permission\Models\Role;


class CompanyMembershipServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_it_creates_a_company_and_attaches_creator_as_default_member(): void
    {
        $user = User::factory()->create();

        $company = app(CompanyMembershipService::class)->createCompanyFor(
            $user,
            ['name' => 'Acme']
        );

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Acme',
            'slug' => 'acme',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'is_default' => true,
            'status' => 'active',
        ]);

        // Verify that the three default roles are created automatically.
        $this->assertSame(
            3,
            Role::where('company_id', $company->id)->count()
        );

        foreach (['Admin', 'Sales Manager', 'Sales Agent'] as $roleName) {
            $this->assertDatabaseHas('roles', [
                'name' => $roleName,
                'guard_name' => 'web',
                'company_id' => $company->id,
            ]);
        }
    }

    public function test_it_does_not_assign_a_second_default_company_to_same_user(): void
    {
        $user = User::factory()->create();
        $service = app(CompanyMembershipService::class);

        $firstCompany = $service->createCompanyFor(
            $user,
            ['name' => 'First Company']
        );

        $secondCompany = $service->createCompanyFor(
            $user,
            ['name' => 'Second Company']
        );

        $this->assertDatabaseHas('company_user', [
            'company_id' => $firstCompany->id,
            'user_id' => $user->id,
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('company_user', [
            'company_id' => $secondCompany->id,
            'user_id' => $user->id,
            'is_default' => false,
        ]);

        $this->assertSame(
            1,
            DB::table('company_user')
                ->where('user_id', $user->id)
                ->where('is_default', true)
                ->count()
        );
    }

    public function test_it_replaces_a_stale_default_company(): void
    {
        $user = User::factory()->create();
        $service = app(CompanyMembershipService::class);

        $oldCompany = $service->createCompanyFor(
            $user,
            ['name' => 'Old Company']
        );

        $oldCompany->update(['status' => 'suspended']);

        $newCompany = $service->createCompanyFor(
            $user,
            ['name' => 'New Company']
        );

        $this->assertDatabaseHas('company_user', [
            'company_id' => $oldCompany->id,
            'user_id' => $user->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('company_user', [
            'company_id' => $newCompany->id,
            'user_id' => $user->id,
            'is_default' => true,
        ]);
    }

    public function test_it_generates_unique_slugs_for_duplicate_company_names(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $service = app(CompanyMembershipService::class);

        $firstCompany = $service->createCompanyFor(
            $firstUser,
            ['name' => 'Acme']
        );

        $secondCompany = $service->createCompanyFor(
            $secondUser,
            ['name' => 'Acme']
        );

        $this->assertSame('acme', $firstCompany->slug);
        $this->assertSame('acme-1', $secondCompany->slug);
    }

    public function test_it_changes_the_default_company(): void
    {
        $user = User::factory()->create();
        $service = app(CompanyMembershipService::class);

        $firstCompany = $service->createCompanyFor(
            $user,
            ['name' => 'First Company']
        );

        $secondCompany = Company::create([
            'name' => 'Second Company',
            'slug' => 'second-company',
            'status' => 'active',
        ]);

        $user->companies()->attach($secondCompany->id, [
            'is_default' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $service->setDefaultCompany($user, $secondCompany->id);

        $this->assertDatabaseHas('company_user', [
            'company_id' => $firstCompany->id,
            'user_id' => $user->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('company_user', [
            'company_id' => $secondCompany->id,
            'user_id' => $user->id,
            'is_default' => true,
        ]);
    }

    public function test_it_rejects_a_company_the_user_does_not_belong_to(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $company = app(CompanyMembershipService::class)->createCompanyFor(
            $otherUser,
            ['name' => 'Private Company']
        );

        $this->expectException(AuthorizationException::class);

        app(CompanyMembershipService::class)->getAccessibleCompany(
            $user,
            $company->id
        );
    }

    public function test_it_rejects_a_suspended_membership(): void
    {
        $user = User::factory()->create();

        $company = app(CompanyMembershipService::class)->createCompanyFor(
            $user,
            ['name' => 'Suspended Company']
        );

        DB::table('company_user')
            ->where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->update(['status' => 'suspended']);

        $this->expectException(AuthorizationException::class);

        app(CompanyMembershipService::class)->getAccessibleCompany(
            $user,
            $company->id
        );
    }

    public function test_it_rolls_back_company_creation_when_default_role_creation_fails(): void
{
    $user = User::factory()->create();

    // Remove a permission required by the Admin role.
    DB::table('permissions')
        ->where('name', 'company.settings.update')
        ->where('guard_name', 'web')
        ->delete();

    $companiesBefore = DB::table('companies')->count();
    $membershipsBefore = DB::table('company_user')->count();

    try {
        app(CompanyMembershipService::class)->createCompanyFor(
            $user,
            ['name' => 'Rollback Company']
        );

        $this->fail('An exception should have been thrown.');
    } catch (RuntimeException $exception) {
        $this->assertStringContainsString(
            'Some permissions are missing',
            $exception->getMessage()
        );
    }

    // The company creation must have been rolled back.
    $this->assertSame(
        $companiesBefore,
        DB::table('companies')->count()
    );

    // The membership creation must also have been rolled back.
    $this->assertSame(
        $membershipsBefore,
        DB::table('company_user')->count()
    );

    // No role should remain for the failed company creation.
    $this->assertSame(
        0,
        Role::count()
    );
}
}
