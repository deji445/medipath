<?php

namespace Tests\Unit\Models;

use App\Enums\HospitalCapability;
use App\Enums\RoutingMode;
use App\Models\Hospital;
use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class HospitalTest extends TestCase
{
    public function test_it_maps_uuid_and_configuration_fields(): void
    {
        $hospital = new Hospital;
        $hospital->forceFill([
            'capability' => HospitalCapability::EmergencyCapable->value,
            'routing_mode' => RoutingMode::Automatic->value,
            'accepting_submissions' => true,
            'is_active' => true,
        ]);

        $this->assertSame('string', $hospital->getKeyType());
        $this->assertFalse($hospital->getIncrementing());
        $this->assertSame(HospitalCapability::EmergencyCapable, $hospital->capability);
        $this->assertSame(RoutingMode::Automatic, $hospital->routing_mode);
        $this->assertTrue($hospital->accepting_submissions);
    }

    public function test_it_defines_the_staff_relationship(): void
    {
        $relation = (new Hospital)->staffUsers();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertSame(StaffUser::class, $relation->getRelated()::class);
        $this->assertSame('hospital_id', $relation->getForeignKeyName());
    }
}
