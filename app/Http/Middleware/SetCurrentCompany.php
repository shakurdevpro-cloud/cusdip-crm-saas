<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentCompany
{
    public function __construct(
        protected CurrentCompany $currentCompany,
        protected PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        $companyId = $request->session()->get('current_company_id');

        $membershipQuery = $user->companies()
            ->wherePivot('status', 'active')
            ->where('companies.status', 'active');

        if ($companyId) {
            $company = (clone $membershipQuery)
                ->where('companies.id', $companyId)
                ->first();
        } else {
            $company = (clone $membershipQuery)
                ->wherePivot('is_default', true)
                ->first();
        }

        if (! $company) {
            $this->clearContext();

            abort(403, 'No active company is available for this account.');
        }

        $this->currentCompany->set($company);

        $this->permissionRegistrar->setPermissionsTeamId($company->id);

        try {
            return $next($request);
        } finally {
            $this->clearContext();
        }
    }

    /**
     * Clear the current company and Spatie team context.
     */
    protected function clearContext(): void
    {
        $this->permissionRegistrar->setPermissionsTeamId(null);

        $this->currentCompany->clear();
    }
}
