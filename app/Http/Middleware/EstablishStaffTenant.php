<?php

namespace App\Http\Middleware;

use App\Models\StaffUser;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstablishStaffTenant
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $staffUser = $request->user();

        abort_unless($staffUser instanceof StaffUser && $staffUser->is_active, 403);
        abort_if($staffUser->isSuperAdmin() && $staffUser->hospital_id !== null, 403);
        abort_if(! $staffUser->isSuperAdmin() && $staffUser->hospital_id === null, 403);

        $this->tenantContext->establish($staffUser);
        $request->attributes->set('tenant_hospital_id', $staffUser->hospital_id);

        return $next($request);
    }
}
