<?php

namespace Tests\Feature\Intake;

use App\Enums\RecommendedAction;
use App\Enums\RedFlagCode;
use App\Enums\SeverityLevel;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\Assessment\AssessmentProvider;
use App\Services\Assessment\AssessmentResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PatientIntakeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-04 12:00:00');
        config()->set('medipath.phone_verification.demo_code', '123456');

        foreach (['consent_events', 'assessments', 'visits', 'patient_medications', 'patient_allergies', 'patient_conditions', 'patients'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('patients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('normalized_phone')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('sex_at_birth')->default('UNKNOWN');
            $table->timestamps();
        });

        foreach (['patient_conditions', 'patient_allergies'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('patient_id');
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['patient_id', 'name']);
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
            $table->json('red_flags')->nullable();
            $table->json('reason_codes')->nullable();
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
    }

    public function test_sms_delivery_failure_returns_a_retryable_form_error(): void
    {
        config()->set([
            'medipath.phone_verification.driver' => 'bms',
            'medipath.phone_verification.bms.endpoint' => 'https://bms.test/api/sms/quick',
            'medipath.phone_verification.bms.api_key' => 'private-test-key',
            'medipath.phone_verification.bms.sender_id' => 'MediPath',
        ]);
        Http::fake(['https://bms.test/api/sms/quick*' => Http::response([], 503)]);

        $this->from('/intake')
            ->post('/intake/lookup', ['phone_number' => '0241234567'])
            ->assertRedirect('/intake')
            ->assertSessionHasErrors([
                'phone_number' => 'The verification message could not be sent right now. Please wait a moment and try again.',
            ]);

        Http::assertSentCount(2);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_chat_handoff_survives_phone_lookup_and_prefills_the_visit_concern(): void
    {
        $handoff = [
            'main_complaint' => 'A dull stomach pain since this morning.',
            'message_count' => 2,
            'started_at' => now()->toIso8601String(),
        ];

        $this->withSession(['intake.chat_handoff' => $handoff])
            ->post('/intake/lookup', [
                'phone_number' => '024 123 4567',
                'streamlined' => '1',
            ])
            ->assertRedirect('/intake/verify-phone')
            ->assertSessionHas('intake.chat_handoff', $handoff);

        $this->withSession([
            'intake.identity' => [
                'first_name' => 'Ama',
                'last_name' => 'Mensah',
                'date_of_birth' => '1995-04-10',
                'sex_at_birth' => 'FEMALE',
            ],
            'intake.history' => [
                'conditions' => [],
                'allergies' => [],
                'medications' => [],
            ],
            'intake.chat_handoff' => $handoff,
        ])->get('/intake/visit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('intake/Visit')
                ->where('visit.main_complaint', $handoff['main_complaint']));
    }

    public function test_a_new_patient_can_complete_the_intake_workflow(): void
    {
        $this->startVerifiedIntake('024 123 4567')
            ->assertRedirect('/intake/identity')
            ->assertSessionHas('intake.phone', '+233241234567');

        $this->post('/intake/identity', [
            'first_name' => 'Ama',
            'last_name' => 'Mensah',
            'date_of_birth' => '1995-06-12',
            'sex_at_birth' => 'FEMALE',
        ])->assertRedirect('/intake/history');

        $this->post('/intake/history', [
            'conditions_text' => "Asthma\nMigraine",
            'allergies_text' => 'Penicillin',
            'medications_text' => 'Salbutamol | 2 puffs | As needed',
        ])->assertRedirect('/intake/visit');

        $this->get('/intake/visit')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('intake/Visit')
                ->where('patientAge', 31),
        );

        $this->post('/intake/visit', $this->validVisitData())
            ->assertRedirect('/intake/review')
            ->assertSessionHas('intake.visit.age_years', 31);

        $this->get('/intake/review')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('intake/Review')
                ->where('identity.first_name', 'Ama')
                ->where('visit.main_complaint', 'Wheezing and shortness of breath'),
        );

        $this->post('/intake/review')->assertRedirect('/intake/complete');

        $this->assertDatabaseHas('patients', ['normalized_phone' => '+233241234567']);
        $this->assertDatabaseHas('patient_conditions', ['name' => 'Asthma', 'is_active' => true]);
        $this->assertDatabaseHas('patient_allergies', ['name' => 'Penicillin', 'is_active' => true]);
        $this->assertDatabaseHas('patient_medications', ['name' => 'Salbutamol', 'dose' => '2 puffs']);
        $this->assertDatabaseHas('visits', ['status' => 'DRAFT', 'age_years' => 31]);
        $this->assertDatabaseHas('assessments', ['status' => 'PENDING']);
        $this->assertDatabaseCount('consent_events', 2);
        $this->get('/intake/complete')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('intake/Complete')->has('reference'),
        );

        $this->get('/intake/assessment/safety')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('assessment/Safety')
                ->missing('warnings'),
        );
    }

    public function test_a_clear_safety_screen_is_saved_without_claiming_a_severity(): void
    {
        $visit = $this->createVisitReadyForAssessment();

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->post('/intake/assessment/safety', [
                'answers_confirmed' => '1',
            ])
            ->assertRedirect('/intake/assessment');

        $visit->refresh();

        $this->assertSame([], $visit->intake_answers['safety_screen']['red_flags']);
        $this->assertNotEmpty($visit->intake_answers['safety_screen']['completed_at']);
        $this->assertDatabaseHas('assessments', [
            'visit_id' => $visit->getKey(),
            'status' => 'PENDING',
            'severity' => null,
            'deterministic_red_flag_match' => false,
        ]);

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->get('/intake/assessment')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessment/Question')
                ->where('mainConcern', 'Wheezing and shortness of breath')
                ->where('group.key', 'symptom_pattern')
                ->where('progress.current', 1)
                ->where('progress.total', 2));
    }

    public function test_the_streamlined_patient_flow_runs_the_safety_screen_and_opens_the_assessment(): void
    {
        $this->startVerifiedIntake('024 123 4567', true)
            ->assertRedirect('/intake');

        $this->get('/intake')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('intake/Start')
                ->where('phoneNumber', '+233241234567'),
        );

        $this->post('/intake/profile', [
            'first_name' => 'Ama',
            'last_name' => 'Mensah',
            'date_of_birth' => '1995-06-12',
            'sex_at_birth' => 'FEMALE',
            'history_mode' => 'UPDATE',
            'conditions_status' => 'NONE',
            'allergies_status' => 'UNSURE',
            'medications_status' => 'NONE',
        ])->assertRedirect('/intake/visit');

        $this->post('/intake/visit', [
            ...$this->validVisitData(),
            'streamlined' => '1',
        ])->assertRedirect('/intake/assessment');

        $this->assertDatabaseHas('patients', ['normalized_phone' => '+233241234567']);
        $this->assertDatabaseHas('visits', ['status' => 'DRAFT', 'age_years' => 31]);
        $this->assertDatabaseHas('assessments', ['status' => 'PENDING']);
        $visit = Visit::query()->latest('started_at')->firstOrFail();
        $this->assertSame('automated_concern_screen', $visit->intake_answers['safety_screen']['source']);
        $this->assertSame([], $visit->intake_answers['safety_screen']['red_flags']);
    }

    #[DataProvider('urgentMainConcerns')]
    public function test_urgent_language_in_the_main_concern_opens_emergency_help_immediately(
        string $mainConcern,
        RedFlagCode $expectedFlag,
    ): void {
        $response = $this->withSession([
            'intake' => [
                'phone' => '+233241234577',
                'identity' => [
                    'patient_id' => null,
                    'first_name' => 'Esi',
                    'last_name' => 'Boateng',
                    'date_of_birth' => '1988-01-10',
                    'sex_at_birth' => 'FEMALE',
                ],
                'history' => [
                    'conditions' => [],
                    'allergies' => [],
                    'medications' => [],
                ],
            ],
        ])->post('/intake/visit', [
            ...$this->validVisitData(),
            'main_complaint' => $mainConcern,
            'streamlined' => '1',
        ]);

        $response->assertRedirect('/intake/assessment/emergency');
        $this->assertDatabaseHas('assessments', [
            'status' => 'COMPLETE',
            'severity' => 'HIGH',
            'deterministic_red_flag_match' => true,
        ]);
        $visit = Visit::query()->latest('started_at')->firstOrFail();
        $this->assertContains($expectedFlag->value, $visit->assessment?->red_flags ?? []);
    }

    /** @return array<string, array{string, RedFlagCode}> */
    public static function urgentMainConcerns(): array
    {
        return [
            'unable to breathe' => [
                'I am choking and cannot breathe',
                RedFlagCode::SevereBreathingDifficulty,
            ],
            'bleeding eyes' => [
                'My eyes are bleeding',
                RedFlagCode::SevereUncontrolledBleeding,
            ],
            'bleeding ears and nose' => [
                'Bleeding from my ears and nose',
                RedFlagCode::SevereUncontrolledBleeding,
            ],
            'plain-language chest pain' => [
                'My chest hurts',
                RedFlagCode::ConcerningChestPain,
            ],
            'severed limb' => [
                'My leg was cut off',
                RedFlagCode::MajorTraumaBurnOrPoisoning,
            ],
        ];
    }

    public function test_critical_adult_vitals_open_emergency_help_before_the_questionnaire(): void
    {
        $response = $this->withSession([
            'intake' => [
                'phone' => '+233241234579',
                'identity' => [
                    'patient_id' => null,
                    'first_name' => 'Kofi',
                    'last_name' => 'Adu',
                    'date_of_birth' => '1987-05-10',
                    'sex_at_birth' => 'MALE',
                ],
                'history' => [
                    'conditions' => [],
                    'allergies' => [],
                    'medications' => [],
                ],
            ],
        ])->post('/intake/visit', [
            ...$this->validVisitData(),
            'main_complaint' => 'Feeling generally unwell',
            'oxygen_saturation_percent' => 89,
            'streamlined' => '1',
        ]);

        $response->assertRedirect('/intake/assessment/emergency');
        $visit = Visit::query()->latest('started_at')->firstOrFail();
        $this->assertContains(
            RedFlagCode::CriticalVitalSigns->value,
            $visit->assessment?->red_flags ?? [],
        );
        $this->assertNotEmpty($visit->intake_answers['safety_screen']['vital_findings']);
    }

    public function test_gemini_can_escalate_the_initial_concern_before_the_questionnaire(): void
    {
        config()->set('medipath.assessment.driver', 'gemini');
        config()->set('medipath.assessment.initial_ai_safety_screen', true);
        $this->app->instance(AssessmentProvider::class, new class implements AssessmentProvider
        {
            public function assess(Visit $visit, array $answers, string $idempotencyKey): AssessmentResult
            {
                return new AssessmentResult(
                    severity: SeverityLevel::High,
                    recommendedAction: RecommendedAction::SeekEmergencyCareNow,
                    symptomSummary: 'The initial concern needs immediate emergency assessment.',
                    redFlags: [RedFlagCode::ConcerningChestPain->value],
                    reasonCodes: [RedFlagCode::ConcerningChestPain->value],
                    schemaVersion: '1.0',
                    providerModel: 'gemini-test-model',
                );
            }

            public function model(): string
            {
                return 'gemini-test-model';
            }
        });

        $response = $this->withSession([
            'intake' => [
                'phone' => '+233241234578',
                'identity' => [
                    'patient_id' => null,
                    'first_name' => 'Yaw',
                    'last_name' => 'Asare',
                    'date_of_birth' => '1985-03-02',
                    'sex_at_birth' => 'MALE',
                ],
                'history' => [
                    'conditions' => [],
                    'allergies' => [],
                    'medications' => [],
                ],
            ],
        ])->post('/intake/visit', [
            ...$this->validVisitData(),
            'main_complaint' => 'A new uncomfortable feeling in my chest',
            'streamlined' => '1',
        ]);

        $response->assertRedirect('/intake/assessment/emergency');
        $this->assertDatabaseHas('assessments', [
            'status' => 'COMPLETE',
            'severity' => 'HIGH',
            'provider_model' => 'gemini-test-model',
            'deterministic_red_flag_match' => false,
        ]);
    }

    public function test_assessment_back_navigation_reopens_and_updates_the_current_concern(): void
    {
        $visit = $this->createVisitReadyForAssessment();
        $visit->update([
            'intake_answers' => [
                ...$visit->intake_answers,
                'safety_screen' => [
                    'completed_at' => now()->toIso8601String(),
                    'red_flags' => [],
                    'source' => 'automated_concern_screen',
                ],
                'assessment_conversation' => ['duration' => 'LESS_THAN_DAY'],
            ],
        ]);

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->get('/intake/visit/current')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('intake/Visit')
                ->where('visit.main_complaint', 'Wheezing and shortness of breath')
                ->where('formAction', '/intake/visit/current')
                ->where('formMethod', 'patch')
                ->where('backHref', '/intake/assessment'));

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->patch('/intake/visit/current', [
                ...$this->validVisitData(),
                'main_complaint' => 'A mild headache since this morning',
                'streamlined' => '1',
            ])
            ->assertRedirect('/intake/assessment');

        $visit->refresh();
        $this->assertDatabaseCount('visits', 1);
        $this->assertSame('A mild headache since this morning', $visit->intake_answers['main_complaint']);
        $this->assertArrayNotHasKey('assessment_conversation', $visit->intake_answers);
        $this->assertSame('automated_concern_screen', $visit->intake_answers['safety_screen']['source']);
        $this->assertDatabaseHas('assessments', [
            'visit_id' => $visit->getKey(),
            'status' => 'PENDING',
            'severity' => null,
        ]);
    }

    public function test_a_returning_patient_can_continue_without_reentering_their_date_of_birth(): void
    {
        Patient::query()->create([
            'normalized_phone' => '+233200000001',
            'first_name' => 'Demo',
            'last_name' => 'Patient',
            'date_of_birth' => '1990-01-15',
            'sex_at_birth' => 'FEMALE',
        ]);

        $this->startVerifiedIntake('0200000001', true)
            ->assertRedirect('/intake');

        $this->post('/intake/profile', [
            'use_existing' => '1',
            'history_mode' => 'UNCHANGED',
        ])->assertRedirect('/intake/visit');
    }

    public function test_a_red_flag_immediately_creates_a_high_severity_emergency_result(): void
    {
        $visit = $this->createVisitReadyForAssessment();
        $redFlag = RedFlagCode::SevereBreathingDifficulty;

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->post('/intake/assessment/safety', [
                'red_flags' => [$redFlag->value],
                'answers_confirmed' => '1',
            ])
            ->assertRedirect('/intake/assessment/emergency');

        $this->assertDatabaseHas('assessments', [
            'visit_id' => $visit->getKey(),
            'status' => 'COMPLETE',
            'severity' => 'HIGH',
            'recommended_action' => 'SEEK_EMERGENCY_CARE_NOW',
            'deterministic_red_flag_match' => true,
            'question_count' => 0,
        ]);
        $this->assertDatabaseHas('visits', [
            'id' => $visit->getKey(),
            'status' => 'ASSESSMENT_COMPLETE',
        ]);

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->get('/intake/assessment/emergency')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessment/Emergency')
                ->has('matchedWarnings', 1)
                ->where('matchedWarnings.0.code', $redFlag->value));
    }

    public function test_the_one_click_safety_concern_opens_emergency_help(): void
    {
        $visit = $this->createVisitReadyForAssessment();

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->post('/intake/assessment/safety', [
                'safety_status' => 'CONCERN',
                'answers_confirmed' => '1',
            ])
            ->assertRedirect('/intake/assessment/emergency');

        $this->assertDatabaseHas('assessments', [
            'visit_id' => $visit->getKey(),
            'severity' => 'HIGH',
            'deterministic_red_flag_match' => true,
        ]);
    }

    public function test_profile_sex_cannot_be_overridden_during_the_visit(): void
    {
        $patient = Patient::query()->create([
            'normalized_phone' => '+233241234569',
            'first_name' => 'Kojo',
            'last_name' => 'Mensah',
            'date_of_birth' => '1990-01-15',
            'sex_at_birth' => 'MALE',
        ]);

        $this->withSession([
            'intake' => [
                'phone' => $patient->normalized_phone,
                'identity' => [
                    'patient_id' => $patient->getKey(),
                    'first_name' => $patient->first_name,
                    'last_name' => $patient->last_name,
                    'date_of_birth' => '1990-01-15',
                    'sex_at_birth' => 'MALE',
                ],
                'history' => [
                    'conditions' => [],
                    'allergies' => [],
                    'medications' => [],
                ],
            ],
        ])->post('/intake/visit', [
            ...$this->validVisitData(),
            'sex_at_birth' => 'FEMALE',
            'pregnancy_status' => 'PREGNANT',
            'streamlined' => '1',
        ])->assertRedirect('/intake/assessment');

        $this->assertDatabaseHas('visits', [
            'patient_id' => $patient->getKey(),
            'sex_at_birth' => 'MALE',
            'pregnancy_status' => 'NOT_APPLICABLE',
        ]);
    }

    public function test_the_safety_screen_requires_an_explicit_confirmation(): void
    {
        $visit = $this->createVisitReadyForAssessment();

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->post('/intake/assessment/safety')
            ->assertSessionHasErrors('answers_confirmed');
    }

    public function test_a_returning_patient_is_identified_without_exposing_their_full_name(): void
    {
        Patient::query()->create([
            'normalized_phone' => '+233200000001',
            'first_name' => 'Demo',
            'last_name' => 'Patient',
            'date_of_birth' => '1990-01-15',
            'sex_at_birth' => 'FEMALE',
        ]);

        $this->startVerifiedIntake('0200000001')
            ->assertRedirect('/intake/identity');

        $this->get('/intake/identity')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('intake/Identity')
                ->where('existingPatient.maskedName', 'D*** P***')
                ->where('phoneNumber', '•••••••••0001'),
        );

        $this->post('/intake/identity', ['use_existing' => '1'])
            ->assertRedirect('/intake/history');
        $this->post('/intake/history')->assertRedirect('/intake/visit');
        $this->get('/intake/visit')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('intake/Visit')
                ->where('patientAge', 36),
        );

        $this->post('/intake/visit', $this->validVisitData())
            ->assertSessionHas('intake.visit.age_years', 36);
    }

    public function test_patient_data_is_not_loaded_until_the_phone_is_verified(): void
    {
        $patient = Patient::query()->create([
            'normalized_phone' => '+233200000001',
            'first_name' => 'Private',
            'last_name' => 'Patient',
            'date_of_birth' => '1990-01-15',
            'sex_at_birth' => 'FEMALE',
        ]);

        $this->post('/intake/lookup', ['phone_number' => '0200000001'])
            ->assertRedirect('/intake/verify-phone')
            ->assertSessionMissing('intake.phone')
            ->assertSessionMissing('intake.patient_id')
            ->assertSessionHas('intake.pending_phone_verification.phone', '+233200000001');

        $this->get('/intake')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('intake/Start')
                ->where('existingPatient', null),
        );

        $this->post('/intake/verify-phone', ['code' => '123456'])
            ->assertRedirect('/intake/identity')
            ->assertSessionHas('intake.phone', '+233200000001')
            ->assertSessionHas('intake.patient_id', $patient->getKey())
            ->assertSessionMissing('intake.pending_phone_verification');
    }

    public function test_an_incorrect_phone_code_is_rejected_and_counted(): void
    {
        $this->post('/intake/lookup', ['phone_number' => '0241234567']);

        $this->post('/intake/verify-phone', ['code' => '000000'])
            ->assertSessionHasErrors('code')
            ->assertSessionHas('intake.pending_phone_verification.attempts', 1)
            ->assertSessionMissing('intake.phone');
    }

    public function test_an_expired_phone_code_is_rejected(): void
    {
        $this->post('/intake/lookup', ['phone_number' => '0241234567']);
        CarbonImmutable::setTestNow('2026-10-04 12:11:00');

        $this->post('/intake/verify-phone', ['code' => '123456'])
            ->assertSessionHasErrors('code')
            ->assertSessionMissing('intake.phone');
    }

    public function test_the_combined_intake_notice_is_required(): void
    {
        $response = $this->withSession([
            'intake' => [
                'phone' => '+233241234567',
                'identity' => ['first_name' => 'Ama', 'last_name' => 'Mensah'],
                'history' => ['conditions' => [], 'allergies' => [], 'medications' => []],
            ],
        ])->post('/intake/visit', array_diff_key(
            $this->validVisitData(),
            array_flip(['intake_consent_acknowledged']),
        ));

        $response->assertSessionHasErrors('intake_consent_acknowledged');
    }

    public function test_browser_location_is_saved_with_a_separate_location_consent(): void
    {
        $response = $this->withSession([
            'intake' => [
                'phone' => '+233241234588',
                'identity' => [
                    'patient_id' => null,
                    'first_name' => 'Location',
                    'last_name' => 'Demo',
                    'date_of_birth' => '1990-01-01',
                    'sex_at_birth' => 'FEMALE',
                ],
                'history' => ['conditions' => [], 'allergies' => [], 'medications' => []],
            ],
        ])->post('/intake/visit', [
            ...$this->validVisitData(),
            'latitude' => '5.603717',
            'longitude' => '-0.186964',
            'location_permission_granted' => '1',
            'streamlined' => '1',
        ]);

        $response->assertRedirect('/intake/assessment');
        $visit = Visit::query()->latest('started_at')->firstOrFail();

        $this->assertSame('5.603717', $visit->latitude);
        $this->assertSame('-0.186964', $visit->longitude);
        $this->assertDatabaseHas('consent_events', [
            'visit_id' => $visit->getKey(),
            'consent_type' => 'LOCATION_ACCESS',
            'granted' => true,
        ]);
    }

    /** @return array<string, int|string> */
    private function validVisitData(): array
    {
        return [
            'age_years' => 99,
            'filling_as' => 'SELF',
            'sex_at_birth' => 'FEMALE',
            'pregnancy_status' => 'NOT_PREGNANT',
            'main_complaint' => 'Wheezing and shortness of breath',
            'symptom_details' => 'Worse when walking',
            'symptom_onset' => 'This morning',
            'manual_location' => 'Accra',
            'temperature_c' => '37.2',
            'heart_rate_bpm' => 92,
            'oxygen_saturation_percent' => 97,
            'intake_consent_acknowledged' => '1',
        ];
    }

    private function startVerifiedIntake(
        string $phoneNumber,
        bool $streamlined = false,
    ): TestResponse {
        $lookup = $this->post('/intake/lookup', array_filter([
            'phone_number' => $phoneNumber,
            'streamlined' => $streamlined ? '1' : null,
        ]));

        $lookup->assertRedirect('/intake/verify-phone')
            ->assertSessionMissing('intake.phone')
            ->assertSessionMissing('intake.patient_id');

        return $this->post('/intake/verify-phone', ['code' => '123456']);
    }

    private function createVisitReadyForAssessment(): Visit
    {
        $patient = Patient::query()->create([
            'normalized_phone' => '+233241234568',
            'first_name' => 'Akosua',
            'last_name' => 'Owusu',
            'date_of_birth' => '1992-04-10',
            'sex_at_birth' => 'FEMALE',
        ]);

        $visit = $patient->visits()->create([
            'status' => 'DRAFT',
            'filling_as' => 'SELF',
            'age_years' => 34,
            'sex_at_birth' => 'FEMALE',
            'pregnancy_status' => 'NOT_PREGNANT',
            'intake_answers' => [
                'main_complaint' => 'Wheezing and shortness of breath',
            ],
        ]);

        $visit->assessment()->create(['status' => 'PENDING']);

        return $visit;
    }
}
