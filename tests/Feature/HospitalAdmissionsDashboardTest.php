<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\StaffUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HospitalAdmissionsDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
            $table->string('logo_url')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->timestamps();
        });

        Schema::create('hospital_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('visit_id')->unique();
            $table->uuid('hospital_id');
            $table->uuid('consent_event_id')->unique();
            $table->string('status');
            $table->string('arrival_type');
            $table->string('severity');
            $table->string('recommended_action');
            $table->json('submission_snapshot');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        $this->hospital('00000000-0000-4000-8000-000000000001', 'hospital-a');
        $this->hospital('00000000-0000-4000-8000-000000000002', 'hospital-b');
    }

    public function test_dashboard_is_scoped_to_the_authenticated_staff_hospital(): void
    {
        $this->submission('10000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000000001', 'HIGH', 'RECEIVED', 'Ama Mensah');
        $this->submission('10000000-0000-4000-8000-000000000002', '00000000-0000-4000-8000-000000000002', 'HIGH', 'RECEIVED', 'Other Patient');

        $this->actingAs($this->staff('00000000-0000-4000-8000-000000000001'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('counts.highPriority', 1)
                ->has('submissions.data', 1)
                ->where('submissions.data.0.patient.name', 'Ama Mensah')
            );
    }

    public function test_staff_cannot_update_another_hospitals_submission(): void
    {
        $this->submission('10000000-0000-4000-8000-000000000003', '00000000-0000-4000-8000-000000000002', 'MEDIUM', 'RECEIVED', 'Other Patient');

        $this->actingAs($this->staff('00000000-0000-4000-8000-000000000001'))
            ->patch(route('dashboard.submissions.status', '10000000-0000-4000-8000-000000000003'), ['status' => 'ACKNOWLEDGED'])
            ->assertNotFound();

        $this->assertDatabaseHas('hospital_submissions', ['id' => '10000000-0000-4000-8000-000000000003', 'status' => 'RECEIVED']);
    }

    public function test_case_status_advances_in_the_expected_order(): void
    {
        $id = '10000000-0000-4000-8000-000000000004';
        $this->submission($id, '00000000-0000-4000-8000-000000000001', 'LOW', 'RECEIVED', 'Kojo Asare');

        $this->actingAs($this->staff('00000000-0000-4000-8000-000000000001'))
            ->patch(route('dashboard.submissions.status', $id), ['status' => 'ACKNOWLEDGED'])
            ->assertRedirect();

        $this->assertDatabaseHas('hospital_submissions', ['id' => $id, 'status' => 'ACKNOWLEDGED']);
    }

    private function hospital(string $id, string $slug): void
    {
        DB::table('hospitals')->insert([
            'id' => $id, 'slug' => $slug, 'name' => ucfirst($slug),
            'capability' => 'EMERGENCY_CAPABLE', 'routing_mode' => 'MANUAL',
            'accepting_submissions' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function submission(string $id, string $hospitalId, string $severity, string $status, string $patientName): void
    {
        DB::table('hospital_submissions')->insert([
            'id' => $id,
            'visit_id' => str_replace('10000000', '20000000', $id),
            'hospital_id' => $hospitalId,
            'consent_event_id' => str_replace('10000000', '30000000', $id),
            'status' => $status,
            'arrival_type' => 'PRE_ARRIVAL',
            'severity' => $severity,
            'recommended_action' => match ($severity) {
                'HIGH' => 'SEEK_EMERGENCY_CARE_NOW',
                'MEDIUM' => 'SEEK_CARE_SOON',
                default => 'ROUTINE_CARE_OR_SELF_CARE',
            },
            'submission_snapshot' => json_encode([
                'patient' => ['name' => $patientName, 'age' => 35, 'sex_at_birth' => 'FEMALE'],
                'main_concern' => 'Shortness of breath',
                'symptom_summary' => 'Symptoms reported during digital intake.',
                'red_flags' => $severity === 'HIGH' ? ['Difficulty breathing'] : [],
                'consent_recorded_at' => now()->toIso8601String(),
            ], JSON_THROW_ON_ERROR),
            'received_at' => now()->subMinutes(20),
            'updated_at' => now(),
        ]);
    }

    private function staff(string $hospitalId): StaffUser
    {
        $staff = new StaffUser;
        $staff->forceFill([
            'id' => '40000000-0000-4000-8000-000000000001',
            'hospital_id' => $hospitalId,
            'email' => 'triage@example.test',
            'first_name' => 'Triage',
            'last_name' => 'Nurse',
            'role' => StaffRole::Triage,
            'is_active' => true,
        ]);
        $staff->exists = true;

        return $staff;
    }
}
