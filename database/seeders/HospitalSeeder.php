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
            ['id' => '00000000-0000-4000-8000-000000000001', 'slug' => 'korle-bu-teaching-hospital', 'name' => 'Korle Bu Teaching Hospital', 'capability' => HospitalCapability::EmergencyCapable, 'routing_mode' => RoutingMode::Manual, 'brand_name' => 'Korle Bu Teaching Hospital', 'brand_color' => '#197F6D', 'address' => 'Guggisberg Avenue, Korle Bu, Accra · GA-221-1570', 'latitude' => 5.537430, 'longitude' => -0.227400, 'directory_source_url' => 'https://kbth.gov.gh/'],
            ['id' => '00000000-0000-4000-8000-000000000002', 'slug' => 'adabraka-polyclinic', 'name' => 'Adabraka Polyclinic', 'capability' => HospitalCapability::ClinicOnly, 'routing_mode' => RoutingMode::Manual, 'brand_name' => 'Adabraka Polyclinic', 'brand_color' => '#197F6D', 'address' => 'Castle Road, Adabraka, Accra · GA-050-3303', 'latitude' => 5.561884, 'longitude' => -0.205108, 'directory_source_url' => 'https://www.ghaneps.gov.gh/epps/prepareViewCAOrganisation.do?id=20629'],
            ['id' => '00000000-0000-4000-8000-000000000003', 'slug' => 'greater-accra-regional-hospital', 'name' => 'Greater Accra Regional Hospital', 'capability' => HospitalCapability::EmergencyCapable, 'routing_mode' => RoutingMode::Manual, 'brand_name' => 'Greater Accra Regional Hospital', 'brand_color' => '#197F6D', 'address' => 'Castle Road, Ridge, Accra · GA-052-7021', 'latitude' => 5.562110, 'longitude' => -0.198930, 'directory_source_url' => 'https://garh.gov.gh/services/accidents-emergency-service/'],
        ];

        foreach ($hospitals as $hospital) {
            Hospital::query()->updateOrCreate(
                ['id' => $hospital['id']],
                [...$hospital, 'accepting_submissions' => true, 'is_active' => true, 'is_live_partner' => false, 'directory_verified_at' => '2026-11-07 00:00:00+00'],
            );
        }
    }
}
