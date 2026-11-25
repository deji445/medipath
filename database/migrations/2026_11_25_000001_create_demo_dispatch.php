<?php

use App\Support\DemoHospital;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hospitals')) {
            return;
        }

        Schema::create('ambulance_vehicles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('ambulance_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals');
            $table->foreignUuid('submission_id')->unique()->constrained('hospital_submissions');
            $table->foreignUuid('vehicle_id')->nullable()->constrained('ambulance_vehicles');
            $table->uuid('assigned_by')->nullable();
            $table->string('crew')->nullable();
            $table->string('status')->default('REQUESTED');
            $table->unsignedSmallInteger('eta_minutes')->nullable();
            $table->json('events');
            $table->timestamps();
            $table->index(['hospital_id', 'status']);
        });
        DB::table('hospitals')->insertOrIgnore([
            'id' => DemoHospital::ID, 'slug' => DemoHospital::SLUG,
            'name' => 'MediPath Demo Hospital', 'brand_name' => 'MediPath Demo Hospital',
            'capability' => 'ER_CAPABLE', 'routing_mode' => 'MANUAL',
            'is_active' => true, 'accepting_submissions' => true, 'is_live_partner' => false,
            'address' => 'Fictional demonstration facility, Accra',
            'latitude' => 5.556, 'longitude' => -0.1969,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([1, 2] as $number) {
            DB::table('ambulance_vehicles')->insert([
                'id' => sprintf('de000000-0000-4000-8000-%012d', $number + 10),
                'hospital_id' => DemoHospital::ID, 'name' => 'Demo ambulance '.$number,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulance_requests');
        Schema::dropIfExists('ambulance_vehicles');
        // Keep the demo hospital: patient records may refer to it.
    }
};
