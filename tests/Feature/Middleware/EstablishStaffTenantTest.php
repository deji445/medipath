<?php

namespace Tests\Feature\Middleware;

use App\Enums\StaffRole;
use App\Http\Middleware\EstablishStaffTenant;
use App\Models\StaffUser;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EstablishStaffTenantTest extends TestCase
{
    public function test_it_establishes_a_hospital_staff_tenant(): void
    {
        $staffUser = $this->staffUser(StaffRole::Doctor, 'hospital-a');
        $request = $this->requestFor($staffUser);
        $tenantContext = new TenantContext;

        $response = (new EstablishStaffTenant($tenantContext))->handle(
            $request,
            fn (): Response => new Response('ok'),
        );

        $this->assertSame('ok', $response->getContent());
        $this->assertSame('hospital-a', $tenantContext->hospitalId());
        $this->assertSame('hospital-a', $request->attributes->get('tenant_hospital_id'));
    }

    public function test_it_establishes_a_platform_context_for_super_admin(): void
    {
        $staffUser = $this->staffUser(StaffRole::SuperAdmin, null);
        $tenantContext = new TenantContext;

        (new EstablishStaffTenant($tenantContext))->handle(
            $this->requestFor($staffUser),
            fn (): Response => new Response('ok'),
        );

        $this->assertTrue($tenantContext->isSuperAdmin());
        $this->assertNull($tenantContext->hospitalId());
    }

    public function test_it_rejects_inactive_staff(): void
    {
        try {
            (new EstablishStaffTenant(new TenantContext))->handle(
                $this->requestFor($this->staffUser(StaffRole::Doctor, 'hospital-a', false)),
                fn (): Response => new Response('not reached'),
            );

            $this->fail('Inactive staff should have been rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_it_rejects_hospital_staff_without_a_hospital(): void
    {
        try {
            (new EstablishStaffTenant(new TenantContext))->handle(
                $this->requestFor($this->staffUser(StaffRole::HospitalAdmin, null)),
                fn (): Response => new Response('not reached'),
            );

            $this->fail('Hospital staff without a hospital should have been rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function requestFor(StaffUser $staffUser): Request
    {
        $request = Request::create('/staff');
        $request->setUserResolver(fn (): StaffUser => $staffUser);

        return $request;
    }

    private function staffUser(StaffRole $role, ?string $hospitalId, bool $isActive = true): StaffUser
    {
        $staffUser = new StaffUser;
        $staffUser->forceFill([
            'role' => $role->value,
            'hospital_id' => $hospitalId,
            'is_active' => $isActive,
        ]);

        return $staffUser;
    }
}
