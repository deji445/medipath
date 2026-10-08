<?php

namespace Tests\Unit\Models;

use App\Enums\StaffRole;
use App\Models\StaffUser;
use PHPUnit\Framework\TestCase;

class StaffUserTest extends TestCase
{
    public function test_it_maps_to_the_existing_staff_users_schema(): void
    {
        $staffUser = new StaffUser;

        $this->assertSame('staff_users', $staffUser->getTable());
        $this->assertSame('string', $staffUser->getKeyType());
        $this->assertFalse($staffUser->getIncrementing());
        $this->assertSame('password_hash', $staffUser->getAuthPasswordName());
        $this->assertSame('', $staffUser->getRememberTokenName());
    }

    public function test_it_exposes_a_display_name_and_casts_the_staff_role(): void
    {
        $staffUser = new StaffUser;
        $staffUser->forceFill([
            'first_name' => 'Ama',
            'last_name' => 'Mensah',
            'role' => StaffRole::Triage->value,
            'is_active' => true,
        ]);

        $this->assertSame('Ama Mensah', $staffUser->name);
        $this->assertSame(StaffRole::Triage, $staffUser->role);
        $this->assertTrue($staffUser->is_active);
    }
}
