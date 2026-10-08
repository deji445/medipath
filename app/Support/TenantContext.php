<?php

namespace App\Support;

use App\Models\StaffUser;
use LogicException;

class TenantContext
{
    private ?StaffUser $staffUser = null;

    public function establish(StaffUser $staffUser): void
    {
        $this->staffUser = $staffUser;
    }

    public function staffUser(): StaffUser
    {
        return $this->staffUser
            ?? throw new LogicException('The staff tenant context has not been established.');
    }

    public function hospitalId(): ?string
    {
        return $this->staffUser()->hospital_id;
    }

    public function isSuperAdmin(): bool
    {
        return $this->staffUser()->isSuperAdmin();
    }
}
