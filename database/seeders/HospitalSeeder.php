<?php

namespace Database\Seeders;

use App\Enums\HospitalCapability;
use App\Enums\RoutingMode;
use App\Models\Hospital;
use Illuminate\Database\Seeder;

class HospitalSeeder extends Seeder
{
    public function run(): void
    {
        $hospitals = [
            ['id' => '00000000-0000-4000-8000-000000000001', 'slug' => 'hospital-a', 'name' => 'MediPath Demo General Hospital', 'capability' => HospitalCapability::EmergencyCapable, 'routing_mode' => RoutingMode::Automatic, 'brand_name' => 'MediPath General', 'brand_color' => '#0F5C5E', 'address' => 'Demo location A, Accra', 'latitude' => 5.603717, 'longitude' => -0.186964],
            ['id' => '00000000-0000-4000-8000-000000000002', 'slug' => 'hospital-b', 'name' => 'MediPath Demo Community Clinic', 'capability' => HospitalCapability::ClinicOnly, 'routing_mode' => RoutingMode::Manual, 'brand_name' => 'Community Clinic', 'brand_color' => '#315A8C', 'address' => 'Demo location B, Accra', 'latitude' => 5.613717, 'longitude' => -0.176964],
            ['id' => '00000000-0000-4000-8000-000000000003', 'slug' => 'hospital-c', 'name' => 'MediPath Demo Emergency Centre', 'capability' => HospitalCapability::EmergencyCapable, 'routing_mode' => RoutingMode::Manual, 'brand_name' => 'MediPath Emergency Centre', 'brand_color' => '#374151', 'address' => 'Demo location C, Accra', 'latitude' => 5.593717, 'longitude' => -0.196964],
        ];

        foreach ($hospitals as $hospital) {
            Hospital::query()->updateOrCreate(
                ['slug' => $hospital['slug']],
                [...$hospital, 'accepting_submissions' => true, 'is_active' => true],
            );
        }
    }
}
