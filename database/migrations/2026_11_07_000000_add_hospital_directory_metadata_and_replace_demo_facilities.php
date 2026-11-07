<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, array<string, mixed>> */
    private array $facilities = [
        [
            'id' => '00000000-0000-4000-8000-000000000001',
            'slug' => 'korle-bu-teaching-hospital',
            'name' => 'Korle Bu Teaching Hospital',
            'capability' => 'ER_CAPABLE',
            'routing_mode' => 'MANUAL',
            'brand_name' => 'Korle Bu Teaching Hospital',
            'brand_color' => '#197F6D',
            'address' => 'Guggisberg Avenue, Korle Bu, Accra · GA-221-1570',
            'latitude' => 5.537430,
            'longitude' => -0.227400,
            'directory_source_url' => 'https://kbth.gov.gh/',
        ],
        [
            'id' => '00000000-0000-4000-8000-000000000002',
            'slug' => 'adabraka-polyclinic',
            'name' => 'Adabraka Polyclinic',
            'capability' => 'CLINIC_ONLY',
            'routing_mode' => 'MANUAL',
            'brand_name' => 'Adabraka Polyclinic',
            'brand_color' => '#197F6D',
            'address' => 'Castle Road, Adabraka, Accra · GA-050-3303',
            'latitude' => 5.561884,
            'longitude' => -0.205108,
            'directory_source_url' => 'https://www.ghaneps.gov.gh/epps/prepareViewCAOrganisation.do?id=20629',
        ],
        [
            'id' => '00000000-0000-4000-8000-000000000003',
            'slug' => 'greater-accra-regional-hospital',
            'name' => 'Greater Accra Regional Hospital',
            'capability' => 'ER_CAPABLE',
            'routing_mode' => 'MANUAL',
            'brand_name' => 'Greater Accra Regional Hospital',
            'brand_color' => '#197F6D',
            'address' => 'Castle Road, Ridge, Accra · GA-052-7021',
            'latitude' => 5.562110,
            'longitude' => -0.198930,
            'directory_source_url' => 'https://garh.gov.gh/services/accidents-emergency-service/',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('hospitals')) {
            return;
        }

        Schema::table('hospitals', function (Blueprint $table): void {
            if (! Schema::hasColumn('hospitals', 'is_live_partner')) {
                $table->boolean('is_live_partner')->default(false);
            }
            if (! Schema::hasColumn('hospitals', 'directory_source_url')) {
                $table->text('directory_source_url')->nullable();
            }
            if (! Schema::hasColumn('hospitals', 'directory_verified_at')) {
                $table->timestampTz('directory_verified_at')->nullable();
            }
        });

        foreach ($this->facilities as $facility) {
            $values = [
                ...$facility,
                'accepting_submissions' => true,
                'is_active' => true,
                'is_live_partner' => false,
                'directory_verified_at' => '2026-11-07 00:00:00+00',
                'updated_at' => now(),
            ];

            if (DB::table('hospitals')->where('id', $facility['id'])->exists()) {
                DB::table('hospitals')->where('id', $facility['id'])->update($values);
            } else {
                DB::table('hospitals')->insert([...$values, 'created_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Facility identity and submitted clinical references must not be rewritten by rollback.
    }
};
