<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyMembershipService
{
    /**
     * Create a company and attach its creator as an active member.
     */

    public function createCompanyFor(
        User $user,
        array $companyData
    ): Company {
        return DB::transaction(function () use ($user, $companyData) {

            // Serialize company creation for this user.
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Check whether the user already has an active default company.
            $hasActiveDefault = DB::table('company_user')
                ->join('companies', 'companies.id', '=', 'company_user.company_id')
                ->where('company_user.user_id', $lockedUser->id)
                ->where('company_user.is_default', true)
                ->where('company_user.status', 'active')
                ->where('companies.status', 'active')
                ->whereNull('companies.deleted_at')
                ->exists();

            // Create the company.
            $company = Company::create([
                'name' => $companyData['name'],
                'slug' => $this->generateUniqueSlug($companyData['name']),
                'email' => $companyData['email'] ?? null,
                'phone' => $companyData['phone'] ?? null,
                'website' => $companyData['website'] ?? null,
                'country_code' => $companyData['country_code'] ?? null,
                'currency' => $companyData['currency'] ?? 'XOF',
                'timezone' => $companyData['timezone'] ?? 'Africa/Abidjan',
                'status' => 'active',
            ]);

            // If no active default company exists, clear any stale default flag.
            if (! $hasActiveDefault) {
                DB::table('company_user')
                    ->where('user_id', $lockedUser->id)
                    ->update([
                        'is_default' => false,
                        'updated_at' => now(),
                    ]);
            }

            
            // Attach the creator to the company.
            $lockedUser->companies()->attach($company->id, [
                'is_default' => ! $hasActiveDefault,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return $company;
        });
    }


    /**
     * Set one company as the user's default company.
     */
    public function setDefaultCompany(
        User $user,
        int $companyId
    ): Company {
        return DB::transaction(function () use ($user, $companyId) {

            // Lock the user row to serialize concurrent default changes.
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Verify that the user can access the requested company.
            $company = $this->getAccessibleCompany(
                $lockedUser,
                $companyId
            );

            // Remove the default flag from all the user's memberships.
            DB::table('company_user')
                ->where('user_id', $lockedUser->id)
                ->update([
                    'is_default' => false,
                    'updated_at' => now(),
                ]);

            // Assign the default flag to the selected company.
            DB::table('company_user')
                ->where('user_id', $lockedUser->id)
                ->where('company_id', $company->id)
                ->update([
                    'is_default' => true,
                    'updated_at' => now(),
                ]);

            return $company;
        });
    }

    /**
     * Retrieve a company only if the user has an active membership.
     */
    public function getAccessibleCompany(
        User $user,
        int $companyId
    ): Company {
        $company = $user->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('status', 'active')
            ->where('companies.status', 'active')
            ->first();

        if (! $company) {
            throw new AuthorizationException(
                'You do not have access to this company.'
            );
        }

        return $company;
    }

    /**
     * Generate a unique slug for a company.
     */
    protected function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'company';
        $slug = $baseSlug;
        $counter = 1;

        while (Company::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
