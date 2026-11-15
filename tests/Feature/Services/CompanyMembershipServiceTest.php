<?php

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyMembershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyMembershipServiceTest extends TestCase
{
    use RefreshDatabase;

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
}
