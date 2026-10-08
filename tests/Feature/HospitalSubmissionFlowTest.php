<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\HospitalSubmission;
use App\Models\StaffUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HospitalSubmissionFlowTest extends TestCase
{
    private const PATIENT_ID = '10000000-0000-4000-8000-000000000001';

    private const VISIT_ID = '20000000-0000-4000-8000-000000000001';

    private const HOSPITAL_ID = '30000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['hospital_submissions', 'consent_events', 'assessments', 'patient_medications', 'patient_allergies', 'patient_conditions', 'visits', 'hospitals', 'patients'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('patients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('normalized_phone')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->timestamps();
        });

        foreach (['patient_conditions', 'patient_allergies'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('patient_id');
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::create('patient_medications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->string('name');
            $table->string('dose')->nullable();
            $table->string('frequency')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

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

        Schema::create('visits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('preselected_hospital_id')->nullable();
            $table->string('status');
            $table->string('filling_as');
            $table->string('respondent_name')->nullable();
            $table->string('relationship_to_patient')->nullable();
            $table->unsignedSmallInteger('age_years');
            $table->string('sex_at_birth');
            $table->string('pregnancy_status');
            $table->json('intake_answers');
            $table->json('manual_vitals')->nullable();
            $table->json('wearable_vitals')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('manual_location')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('visit_id')->unique();
            $table->string('status');
            $table->string('severity')->nullable();
            $table->string('recommended_action')->nullable();
            $table->text('symptom_summary')->nullable();
            $table->json('red_flags');
            $table->json('reason_codes');
            $table->boolean('deterministic_red_flag_match')->default(false);
            $table->string('ai_schema_version')->default('1.0');
            $table->string('provider_model')->nullable();
            $table->unsignedSmallInteger('question_count')->default(0);
            $table->string('error_code')->nullable();
            $table->timestamps();
        });

        Schema::create('consent_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('visit_id');
            $table->uuid('hospital_id')->nullable();
            $table->string('consent_type');
            $table->string('consent_version');
            $table->boolean('granted');
            $table->timestamp('recorded_at');
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

        $this->seedCompletedVisit();
    }

    public function test_consent_review_names_the_destination_and_disclosure_scope(): void
    {
        $this->withSession($this->visitSession())
            ->get(route('submission.review'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('submission/Review')
                ->where('hospital.name', 'MediPath General')
                ->where('patient.name', 'Demo Patient')
                ->where('assessment.severity', 'MEDIUM')
                ->has('sharedGroups', 4));
    }

    public function test_submission_requires_explicit_consent(): void
    {
        $this->withSession($this->visitSession())
            ->post(route('submission.store'))
            ->assertSessionHasErrors('share_consent');

        $this->assertDatabaseCount('consent_events', 0);
        $this->assertDatabaseCount('hospital_submissions', 0);
    }

    public function test_consented_submission_reaches_only_the_selected_hospital_dashboard(): void
    {
        $this->withSession($this->visitSession())
            ->post(route('submission.store'), ['share_consent' => '1'])
            ->assertRedirect(route('submission.complete'));

        $this->assertDatabaseHas('consent_events', [
            'visit_id' => self::VISIT_ID,
            'hospital_id' => self::HOSPITAL_ID,
            'consent_type' => 'HOSPITAL_SHARE',
            'granted' => true,
        ]);
        $this->assertDatabaseHas('hospital_submissions', [
            'visit_id' => self::VISIT_ID,
            'hospital_id' => self::HOSPITAL_ID,
            'status' => 'RECEIVED',
        ]);
        $this->assertDatabaseHas('visits', [
            'id' => self::VISIT_ID,
            'status' => 'SUBMITTED',
        ]);

        $submission = HospitalSubmission::query()->firstOrFail();
        $this->assertSame('+233200000099', $submission->submission_snapshot['patient']['phone']);
        $this->assertSame(['Asthma'], $submission->submission_snapshot['conditions']);
        $this->assertSame('Accra', $submission->submission_snapshot['location']['manual']);

        $this->actingAs($this->staff())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.waiting', 1)
                ->has('submissions.data', 1)
                ->where('submissions.data.0.patient.name', 'Demo Patient')
                ->where('submissions.data.0.patient.phone', '+233200000099')
                ->where('submissions.data.0.location.manual', 'Accra'));
    }

    public function test_repeating_the_final_request_does_not_duplicate_the_submission(): void
    {
        $this->withSession($this->visitSession())
            ->post(route('submission.store'), ['share_consent' => '1'])
            ->assertRedirect(route('submission.complete'));

        $this->withSession($this->visitSession())
            ->post(route('submission.store'), ['share_consent' => '1'])
            ->assertRedirect(route('submission.complete'));

        $this->assertDatabaseCount('consent_events', 1);
        $this->assertDatabaseCount('hospital_submissions', 1);
    }

    public function test_a_walk_in_visit_is_submitted_with_the_walk_in_arrival_type(): void
    {
        DB::table('visits')->where('id', self::VISIT_ID)->update([
            'intake_answers' => json_encode([
                'main_complaint' => 'Persistent headache',
                'arrival_type' => 'WALK_IN_QR',
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->withSession($this->visitSession())
            ->post(route('submission.store'), ['share_consent' => '1'])
            ->assertRedirect(route('submission.complete'));

        $this->assertDatabaseHas('hospital_submissions', [
            'visit_id' => self::VISIT_ID,
            'arrival_type' => 'WALK_IN_QR',
        ]);
    }

    /** @return array<string, string> */
    private function visitSession(): array
    {
        return ['active_visit_reference' => self::VISIT_ID];
    }

    private function seedCompletedVisit(): void
    {
        $now = now();

        DB::table('patients')->insert([
            'id' => self::PATIENT_ID,
            'normalized_phone' => '+233200000099',
            'first_name' => 'Demo',
            'last_name' => 'Patient',
            'date_of_birth' => '1990-01-01',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('patient_conditions')->insert([
            'id' => '40000000-0000-4000-8000-000000000001',
            'patient_id' => self::PATIENT_ID,
            'name' => 'Asthma',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('patient_allergies')->insert([
            'id' => '50000000-0000-4000-8000-000000000001',
            'patient_id' => self::PATIENT_ID,
            'name' => 'Penicillin',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('patient_medications')->insert([
            'id' => '60000000-0000-4000-8000-000000000001',
            'patient_id' => self::PATIENT_ID,
            'name' => 'Salbutamol',
            'dose' => '2 puffs',
            'frequency' => 'As needed',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('hospitals')->insert([
            'id' => self::HOSPITAL_ID,
            'slug' => 'medipath-general',
            'name' => 'MediPath General Hospital',
            'brand_name' => 'MediPath General',
            'capability' => 'ER_CAPABLE',
            'routing_mode' => 'AUTO',
            'accepting_submissions' => true,
            'is_active' => true,
            'address' => 'Demo location A, Accra',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('visits')->insert([
            'id' => self::VISIT_ID,
            'patient_id' => self::PATIENT_ID,
            'preselected_hospital_id' => self::HOSPITAL_ID,
            'status' => 'ASSESSMENT_COMPLETE',
            'filling_as' => 'SELF',
            'age_years' => 36,
            'sex_at_birth' => 'FEMALE',
            'pregnancy_status' => 'NOT_PREGNANT',
            'intake_answers' => json_encode([
                'main_complaint' => 'Persistent headache',
                'symptom_details' => 'Pain worsens with movement',
                'symptom_onset' => 'Two days ago',
            ], JSON_THROW_ON_ERROR),
            'manual_vitals' => json_encode(['temperature_c' => 37.4], JSON_THROW_ON_ERROR),
            'manual_location' => 'Accra',
            'started_at' => $now,
            'completed_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('assessments')->insert([
            'id' => '70000000-0000-4000-8000-000000000001',
            'visit_id' => self::VISIT_ID,
            'status' => 'COMPLETE',
            'severity' => 'MEDIUM',
            'recommended_action' => 'SEEK_CARE_SOON',
            'symptom_summary' => 'Persistent headache that is getting worse.',
            'red_flags' => json_encode([], JSON_THROW_ON_ERROR),
            'reason_codes' => json_encode(['SYMPTOMS_WORSENING'], JSON_THROW_ON_ERROR),
            'deterministic_red_flag_match' => false,
            'question_count' => 6,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function staff(): StaffUser
    {
        $staff = new StaffUser;
        $staff->forceFill([
            'id' => '80000000-0000-4000-8000-000000000001',
            'hospital_id' => self::HOSPITAL_ID,
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
