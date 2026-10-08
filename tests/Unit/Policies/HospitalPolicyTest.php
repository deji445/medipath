<?php

namespace Tests\Unit\Policies;

use App\Enums\StaffRole;
use App\Models\Hospital;
use App\Models\StaffUser;
use App\Policies\HospitalPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HospitalPolicyTest extends TestCase
{
    private HospitalPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new HospitalPolicy;
    }

    public function test_super_admin_can_view_and_update_any_hospital(): void
    {
        $staffUser = $this->staffUser(StaffRole::SuperAdmin, null);
        $hospital = $this->hospital('hospital-b');

        $this->assertTrue($this->policy->view($staffUser, $hospital));
        $this->assertTrue($this->policy->update($staffUser, $hospital));
    }

    public function test_hospital_admin_can_manage_only_their_hospital(): void
    {
        $staffUser = $this->staffUser(StaffRole::HospitalAdmin, 'hospital-a');

        $this->assertTrue($this->policy->manageStaff($staffUser, $this->hospital('hospital-a')));
        $this->assertFalse($this->policy->manageStaff($staffUser, $this->hospital('hospital-b')));
    }

    #[DataProvider('clinicalRoles')]
    public function test_clinical_staff_can_view_but_not_update_their_hospital(StaffRole $role): void
    {
        $staffUser = $this->staffUser($role, 'hospital-a');
        $hospital = $this->hospital('hospital-a');

        $this->assertTrue($this->policy->view($staffUser, $hospital));
        $this->assertFalse($this->policy->update($staffUser, $hospital));
    }

    public function test_inactive_staff_have_no_hospital_access(): void
    {
        $staffUser = $this->staffUser(StaffRole::HospitalAdmin, 'hospital-a', false);
        $hospital = $this->hospital('hospital-a');

        $this->assertFalse($this->policy->viewAny($staffUser));
        $this->assertFalse($this->policy->view($staffUser, $hospital));
        $this->assertFalse($this->policy->update($staffUser, $hospital));
    }

    /**
     * @return array<string, array{StaffRole}>
     */
    public static function clinicalRoles(): array
    {
        return [
            'triage' => [StaffRole::Triage],
            'doctor' => [StaffRole::Doctor],
        ];
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

    private function hospital(string $id): Hospital
    {
        $hospital = new Hospital;
        $hospital->setAttribute('id', $id);

        return $hospital;
    }
}
