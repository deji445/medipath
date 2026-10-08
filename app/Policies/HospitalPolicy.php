<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Hospital;
use App\Models\StaffUser;

class HospitalPolicy
{
    public function viewAny(StaffUser $staffUser): bool
    {
        return $staffUser->is_active;
    }

    public function view(StaffUser $staffUser, Hospital $hospital): bool
    {
        return $staffUser->is_active
            && ($staffUser->isSuperAdmin() || $staffUser->belongsToHospital($hospital));
    }

    public function update(StaffUser $staffUser, Hospital $hospital): bool
    {
        if (! $staffUser->is_active) {
            return false;
        }

        return $staffUser->isSuperAdmin()
            || ($staffUser->role === StaffRole::HospitalAdmin && $staffUser->belongsToHospital($hospital));
    }

    public function manageStaff(StaffUser $staffUser, Hospital $hospital): bool
    {
        return $this->update($staffUser, $hospital);
    }
}
