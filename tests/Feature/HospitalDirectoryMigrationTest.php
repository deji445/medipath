<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HospitalDirectoryMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('hospitals');
        Schema::create('hospitals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('capability');
            $table->string('routing_mode');
            $table->boolean('accepting_submissions')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('brand_name')->nullable();
            $table->string('brand_color')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->timestamps();
        });

        foreach ([1, 2, 3] as $index) {
            DB::table('hospitals')->insert([
                'id' => sprintf('00000000-0000-4000-8000-%012d', $index),
                'slug' => 'hospital-'.chr(96 + $index),
                'name' => 'MediPath Demo Hospital '.$index,
                'capability' => $index === 2 ? 'CLINIC_ONLY' : 'ER_CAPABLE',
                'routing_mode' => 'MANUAL',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_demo_records_are_replaced_without_changing_their_ids(): void
    {
        $migration = require database_path(
            'migrations/2026_11_07_000000_add_hospital_directory_metadata_and_replace_demo_facilities.php',
        );

        $migration->up();

        $this->assertTrue(Schema::hasColumns('hospitals', [
            'is_live_partner',
            'directory_source_url',
            'directory_verified_at',
        ]));
        $this->assertDatabaseHas('hospitals', [
            'id' => '00000000-0000-4000-8000-000000000001',
            'slug' => 'korle-bu-teaching-hospital',
            'name' => 'Korle Bu Teaching Hospital',
            'is_live_partner' => false,
        ]);
        $this->assertDatabaseHas('hospitals', [
            'id' => '00000000-0000-4000-8000-000000000002',
            'slug' => 'adabraka-polyclinic',
            'name' => 'Adabraka Polyclinic',
            'is_live_partner' => false,
        ]);
        $this->assertDatabaseHas('hospitals', [
            'id' => '00000000-0000-4000-8000-000000000003',
            'slug' => 'greater-accra-regional-hospital',
            'name' => 'Greater Accra Regional Hospital',
            'is_live_partner' => false,
        ]);
        $this->assertDatabaseCount('hospitals', 3);
    }
}
