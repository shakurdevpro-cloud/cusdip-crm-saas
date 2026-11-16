<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use App\Services\CompanyMembershipService;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use Database\Seeders\PermissionSeeder;

class SetCurrentCompanyTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        Route::middleware(['web', 'auth', 'company'])
            ->get('/_test/current-company', function (
                CurrentCompany $currentCompany,
                PermissionRegistrar $permissionRegistrar
            ) {
                return response()->json([
                    'company_id' => $currentCompany->id(),
                    'team_id' => $permissionRegistrar->getPermissionsTeamId(),
                ]);
            });
    }

    public function test_it_uses_the_company_selected_in_session(): void
    {
        $user = User::factory()->create();
        $service = app(CompanyMembershipService::class);

        $service->createCompanyFor($user, [
            'name' => 'First Company',
        ]);

        $selectedCompany = $service->createCompanyFor($user, [
            'name' => 'Second Company',
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $selectedCompany->id,
            ])
            ->getJson('/_test/current-company');

        $response->assertOk()
            ->assertJson([
                'company_id' => $selectedCompany->id,
                'team_id' => $selectedCompany->id,
            ]);
    }

    public function test_it_uses_the_default_company_when_no_company_is_selected(): void
    {
        $user = User::factory()->create();

        $company = app(CompanyMembershipService::class)->createCompanyFor(
            $user,
            ['name' => 'Default Company']
        );

        $response = $this->actingAs($user)
            ->getJson('/_test/current-company');

        $response->assertOk()
            ->assertJson([
                'company_id' => $company->id,
                'team_id' => $company->id,
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

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $company->id,
            ])
            ->getJson('/_test/current-company');

        $response->assertForbidden();
    }

    public function test_it_rejects_a_suspended_membership(): void
    {
        $user = User::factory()->create();

        $company = app(CompanyMembershipService::class)->createCompanyFor(
            $user,
            ['name' => 'Suspended Membership']
        );

        DB::table('company_user')
            ->where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->update(['status' => 'suspended']);

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $company->id,
            ])
            ->getJson('/_test/current-company');

        $response->assertForbidden();
    }

    public function test_it_rejects_a_suspended_company(): void
    {
        $user = User::factory()->create();

        $company = app(CompanyMembershipService::class)->createCompanyFor(
            $user,
            ['name' => 'Suspended Company']
        );

        $company->update(['status' => 'suspended']);

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $company->id,
            ])
            ->getJson('/_test/current-company');

        $response->assertForbidden();
    }

    public function test_it_rejects_a_user_without_an_active_default_company(): void
    {
        $user = User::factory()->create();

        $company = app(CompanyMembershipService::class)->createCompanyFor(
            $user,
            ['name' => 'Company Without Default']
        );

        DB::table('company_user')
            ->where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->update(['is_default' => false]);

        $response = $this->actingAs($user)
            ->getJson('/_test/current-company');

        $response->assertForbidden();
    }


    public function test_it_resets_the_company_context_between_requests(): void
    {
        $user = User::factory()->create();

        $service = app(CompanyMembershipService::class);

        $firstCompany = $service->createCompanyFor($user, [
            'name' => 'First Company',
        ]);

        $secondCompany = $service->createCompanyFor($user, [
            'name' => 'Second Company',
        ]);

        // First request: select the first company.
        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $firstCompany->id,
            ])
            ->getJson('/_test/current-company')
            ->assertOk()
            ->assertJson([
                'company_id' => $firstCompany->id,
                'team_id' => $firstCompany->id,
            ]);

        // The context must be cleared after the request.
        $this->assertFalse(
            app(CurrentCompany::class)->has()
        );

        $this->assertNull(
            app(PermissionRegistrar::class)->getPermissionsTeamId()
        );

        // Second request: select another company.
        $this->withSession([
            'current_company_id' => $secondCompany->id,
        ])
            ->getJson('/_test/current-company')
            ->assertOk()
            ->assertJson([
                'company_id' => $secondCompany->id,
                'team_id' => $secondCompany->id,
            ]);

        // The context must also be cleared after the second request.
        $this->assertFalse(
            app(CurrentCompany::class)->has()
        );

        $this->assertNull(
            app(PermissionRegistrar::class)->getPermissionsTeamId()
        );
    }
}
