<?php

namespace Tests\Feature;

use App\Enums\RecommendedAction;
use App\Enums\RedFlagCode;
use App\Enums\SeverityLevel;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\Assessment\AssessmentConversationProvider;
use App\Services\Assessment\AssessmentConversationTurn;
use App\Services\Assessment\AssessmentProvider;
use App\Services\Assessment\AssessmentResult;
use App\Services\Assessment\AssessmentUnavailableException;
use App\Services\Assessment\DeterministicAssessmentProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssessmentRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(AssessmentProvider::class, $this->app->make(DeterministicAssessmentProvider::class));

        foreach (['hospital_submissions', 'consent_events', 'assessments', 'visits', 'patient_medications', 'patient_allergies', 'patient_conditions', 'hospitals', 'patients'] as $table) {
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
            $table->json('red_flags')->nullable();
            $table->json('reason_codes')->nullable();
            $table->boolean('deterministic_red_flag_match')->default(false);
            $table->string('ai_schema_version')->default('1.0');
            $table->string('provider_model')->nullable();
            $table->unsignedSmallInteger('question_count')->default(0);
            $table->string('error_code')->nullable();
            $table->timestamps();
        });
    }

    public function test_the_assessment_uses_two_grouped_sections_and_saves_a_valid_result(): void
    {
        $visit = $this->createVisit();
        $session = ['active_visit_reference' => $visit->getKey()];

        $this->assertDatabaseCount('patient_conditions', 0);
        $this->assertDatabaseCount('patient_allergies', 0);
        $this->assertDatabaseCount('patient_medications', 0);

        $this->withSession($session)->get('/intake/assessment')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessment/Question')
                ->where('group.key', 'symptom_pattern')
                ->has('group.questions', 3)
                ->where('progress.current', 1));

        $this->withSession($session)->post('/intake/assessment', [
            'group_key' => 'symptom_pattern',
            'answers' => [
                'duration' => 'ONE_TO_THREE_DAYS',
                'trend' => 'WORSENING',
                'intensity' => 'MODERATE',
            ],
        ])->assertRedirect('/intake/assessment');

        $this->withSession($session)->get('/intake/assessment')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('group.key', 'impact_and_signs')
                ->has('group.questions', 3)
                ->where('progress.current', 2));

        $response = $this->withSession($session)->post('/intake/assessment', [
            'group_key' => 'impact_and_signs',
            'answers' => [
                'daily_impact' => 'LIMITED',
                'new_warning_sign' => 'NONE',
                'associated_symptoms' => 'FEVER',
            ],
        ]);

        $response->assertRedirect('/intake/routing');
        $this->assertDatabaseHas('assessments', [
            'visit_id' => $visit->getKey(),
            'status' => 'COMPLETE',
            'severity' => 'MEDIUM',
            'recommended_action' => 'SEEK_CARE_SOON',
            'question_count' => 6,
        ]);

        $this->withSession($session)->get('/intake/assessment/result')
            ->assertRedirect('/intake/routing');
    }

    public function test_provider_failure_is_saved_as_unavailable_and_never_as_low_priority(): void
    {
        $this->app->instance(AssessmentProvider::class, new class implements AssessmentProvider
        {
            public function assess(Visit $visit, array $answers, string $idempotencyKey): AssessmentResult
            {
                throw new AssessmentUnavailableException('TEST_PROVIDER_UNAVAILABLE');
            }

            public function model(): string
            {
                return 'test-unavailable-provider';
            }
        });

        $visit = $this->createVisit();
        $session = ['active_visit_reference' => $visit->getKey()];

        foreach ([
            'duration' => 'ONE_TO_THREE_DAYS',
            'trend' => 'UNCHANGED',
            'intensity' => 'MILD',
            'daily_impact' => 'LITTLE',
            'new_warning_sign' => 'NONE',
            'associated_symptoms' => 'NONE',
        ] as $question => $answer) {
            $response = $this->withSession($session)->post('/intake/assessment', [
                'question_key' => $question,
                'answer' => $answer,
            ]);
        }

        $response->assertRedirect('/intake/assessment/result');
        $this->assertDatabaseHas('assessments', [
            'visit_id' => $visit->getKey(),
            'status' => 'FAILED',
            'severity' => null,
            'recommended_action' => null,
            'provider_model' => 'test-unavailable-provider',
            'error_code' => 'TEST_PROVIDER_UNAVAILABLE',
        ]);

        $this->withSession($session)->get('/intake/assessment/result')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessment/Unavailable'));
    }

    public function test_an_emergency_answer_interrupts_the_assessment_and_cannot_be_downgraded(): void
    {
        $visit = $this->createVisit([
            'duration' => 'LESS_THAN_DAY',
            'trend' => 'UNCHANGED',
            'intensity' => 'MILD',
            'daily_impact' => 'LITTLE',
        ]);

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->post('/intake/assessment', [
                'question_key' => 'new_warning_sign',
                'answer' => RedFlagCode::ConcerningChestPain->value,
            ])
            ->assertRedirect('/intake/assessment/emergency');

        $this->assertDatabaseHas('assessments', [
            'visit_id' => $visit->getKey(),
            'status' => 'COMPLETE',
            'severity' => 'HIGH',
            'recommended_action' => 'SEEK_EMERGENCY_CARE_NOW',
            'deterministic_red_flag_match' => true,
        ]);
        $this->assertDatabaseHas('visits', [
            'id' => $visit->getKey(),
            'status' => 'ASSESSMENT_COMPLETE',
        ]);

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->post('/intake/assessment', [
                'question_key' => 'associated_symptoms',
                'answer' => 'NONE',
            ])
            ->assertRedirect('/intake/assessment/emergency');
    }

    public function test_gemini_mode_runs_a_one_question_at_a_time_conversation(): void
    {
        config()->set('medipath.assessment.driver', 'gemini');
        $this->app->instance(AssessmentConversationProvider::class, new class implements AssessmentConversationProvider
        {
            public function nextTurn(
                Visit $visit,
                array $answers,
                array $questions,
                string $idempotencyKey,
                bool $mustComplete = false,
            ): AssessmentConversationTurn {
                if (count($answers) === 1) {
                    return AssessmentConversationTurn::question(
                        'What makes the headache better or worse?',
                        $this->model(),
                    );
                }

                return AssessmentConversationTurn::complete(new AssessmentResult(
                    severity: SeverityLevel::Medium,
                    recommendedAction: RecommendedAction::SeekCareSoon,
                    symptomSummary: 'The patient reported a persistent headache that needs professional assessment.',
                    redFlags: [],
                    reasonCodes: ['SYMPTOMS_WORSENING'],
                    schemaVersion: '1.0',
                    providerModel: $this->model(),
                ));
            }

            public function model(): string
            {
                return 'gemini-conversation-test';
            }
        });

        $visit = $this->createVisit();
        $session = ['active_visit_reference' => $visit->getKey()];

        $this->withSession($session)->get('/intake/assessment')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessment/Question')
                ->where('conversationMode', true)
                ->where('question.key', 'ai_1')
                ->where('progress.current', 1));

        $this->withSession($session)->post('/intake/assessment', [
            'question_key' => 'ai_1',
            'answer' => 'It began yesterday and is getting worse.',
        ])->assertRedirect('/intake/assessment');

        $this->withSession($session)->get('/intake/assessment')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('question.key', 'ai_2')
                ->where('question.question', 'What makes the headache better or worse?')
                ->has('transcript', 1));

        $this->withSession($session)->post('/intake/assessment', [
            'question_key' => 'ai_2',
            'answer' => 'Bright light makes it worse.',
        ])->assertRedirect('/intake/routing');

        $this->assertDatabaseHas('assessments', [
            'visit_id' => $visit->getKey(),
            'status' => 'COMPLETE',
            'severity' => 'MEDIUM',
            'question_count' => 2,
            'provider_model' => 'gemini-conversation-test',
        ]);
    }

    public function test_gemini_conversation_uses_safe_guidance_when_the_provider_is_unavailable(): void
    {
        config()->set('medipath.assessment.driver', 'gemini');
        $this->app->instance(AssessmentConversationProvider::class, new class implements AssessmentConversationProvider
        {
            public function nextTurn(
                Visit $visit,
                array $answers,
                array $questions,
                string $idempotencyKey,
                bool $mustComplete = false,
            ): AssessmentConversationTurn {
                throw new AssessmentUnavailableException('GEMINI_CONNECTION_FAILED');
            }

            public function model(): string
            {
                return 'gemini-unavailable-test';
            }
        });

        $visit = $this->createVisit();

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->post('/intake/assessment', [
                'question_key' => 'ai_1',
                'answer' => 'It began yesterday and is getting worse.',
            ])->assertRedirect('/intake/routing');

        $this->assertDatabaseHas('assessments', [
            'visit_id' => $visit->getKey(),
            'status' => 'COMPLETE',
            'severity' => 'MEDIUM',
            'recommended_action' => 'SEEK_CARE_SOON',
            'provider_model' => 'gemini-unavailable-test-safe-fallback',
            'question_count' => 1,
            'error_code' => 'GEMINI_CONNECTION_FAILED',
        ]);

        $this->assertSame(
            ['ASSESSMENT_SERVICE_UNAVAILABLE'],
            $visit->fresh('assessment')->assessment->reason_codes,
        );
    }

    public function test_a_failed_conversation_can_recover_using_its_saved_answers(): void
    {
        config()->set('medipath.assessment.driver', 'gemini');
        $visit = $this->createVisit(['ai_1' => 'It began yesterday and is getting worse.']);
        $visit->assessment()->update([
            'status' => 'FAILED',
            'provider_model' => 'gemini-legacy-test',
            'error_code' => 'GEMINI_CONNECTION_FAILED',
        ]);

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->post('/intake/assessment/result/recover')
            ->assertRedirect('/intake/assessment/result');

        $this->assertDatabaseHas('assessments', [
            'visit_id' => $visit->getKey(),
            'status' => 'COMPLETE',
            'severity' => 'MEDIUM',
            'recommended_action' => 'SEEK_CARE_SOON',
            'question_count' => 1,
            'error_code' => 'GEMINI_CONNECTION_FAILED',
        ]);
    }

    public function test_gemini_conversation_stops_at_ten_questions_when_the_provider_requests_more(): void
    {
        config()->set([
            'medipath.assessment.driver' => 'gemini',
            'medipath.assessment.max_questions' => 10,
        ]);

        $provider = new class implements AssessmentConversationProvider
        {
            public bool $mustComplete = false;

            public function nextTurn(
                Visit $visit,
                array $answers,
                array $questions,
                string $idempotencyKey,
                bool $mustComplete = false,
            ): AssessmentConversationTurn {
                $this->mustComplete = $mustComplete;

                return AssessmentConversationTurn::question('One more question?', $this->model());
            }

            public function model(): string
            {
                return 'gemini-question-limit-test';
            }
        };
        $this->app->instance(AssessmentConversationProvider::class, $provider);

        $answers = [];
        for ($index = 1; $index <= 9; $index++) {
            $answers['ai_'.$index] = 'Saved answer '.$index;
        }

        $visit = $this->createVisit($answers);

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->post('/intake/assessment', [
                'question_key' => 'ai_10',
                'answer' => 'The final answer.',
            ])->assertRedirect('/intake/routing');

        $this->assertTrue($provider->mustComplete);
        $this->assertDatabaseHas('assessments', [
            'visit_id' => $visit->getKey(),
            'status' => 'COMPLETE',
            'severity' => 'MEDIUM',
            'question_count' => 10,
            'provider_model' => 'gemini-question-limit-test',
        ]);
    }

    public function test_answers_cannot_skip_the_current_question(): void
    {
        $visit = $this->createVisit();

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->post('/intake/assessment', [
                'question_key' => 'intensity',
                'answer' => 'SEVERE',
            ])
            ->assertSessionHasErrors('answer');

        $this->assertDatabaseHas('assessments', [
            'visit_id' => $visit->getKey(),
            'status' => 'PENDING',
            'question_count' => 0,
        ]);
    }

    public function test_emergency_help_is_available_without_starting_an_intake(): void
    {
        $eligible = $this->hospital('emergency-centre', 'ER_CAPABLE');
        $this->hospital('community-clinic', 'CLINIC_ONLY');
        $this->hospital('inactive-emergency', 'ER_CAPABLE', false);

        $this->get('/emergency')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('emergency/Index')
                ->where('emergencyContact.phone', '112')
                ->has('hospitals', 1)
                ->where('hospitals.0.id', $eligible->getKey())
                ->where('hospitals.0.latitude', null)
                ->where('hospitals.0.longitude', null));
    }

    public function test_high_priority_routing_excludes_clinics_and_inactive_hospitals(): void
    {
        $visit = $this->createCompletedVisit('HIGH');
        $emergency = $this->hospital('er', 'ER_CAPABLE');
        $this->hospital('clinic', 'CLINIC_ONLY');
        $this->hospital('inactive-er', 'ER_CAPABLE', false);

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->get('/intake/routing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('routing/Index')
                ->has('hospitals', 1)
                ->where('hospitals.0.id', $emergency->getKey())
                ->where('hospitals.0.recommended', true));
    }

    public function test_medium_priority_routing_is_deterministic_and_selection_is_saved_without_sharing(): void
    {
        $visit = $this->createCompletedVisit('MEDIUM');
        $firstEr = $this->hospital('a-er', 'ER_CAPABLE', true, '00000000-0000-4000-8000-000000000001');
        $this->hospital('b-er', 'ER_CAPABLE', true, '00000000-0000-4000-8000-000000000002');
        $clinic = $this->hospital('clinic', 'CLINIC_ONLY');

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->get('/intake/routing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hospitals.0.id', $firstEr->getKey())
                ->where('hospitals.0.recommended', true)
                ->where('hospitals.0.requiresOverrideConfirmation', false)
                ->where('hospitals.2.requiresOverrideConfirmation', true)
                ->where('hospitals.2.id', $clinic->getKey()));

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->post('/intake/routing', ['hospital_id' => $clinic->getKey()])
            ->assertSessionHasErrors('override_confirmation');

        $this->assertNull($visit->fresh()->preselected_hospital_id);

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->post('/intake/routing', [
                'hospital_id' => $clinic->getKey(),
                'override_confirmation' => '1',
            ])
            ->assertRedirect('/intake/routing/selected');

        $visit->refresh();
        $this->assertSame($clinic->getKey(), $visit->preselected_hospital_id);
        $this->assertSame($clinic->getKey(), $visit->intake_answers['routing']['selected_hospital_id']);
        $this->assertNotEmpty($visit->intake_answers['routing']['override_confirmed_at']);
        $this->assertFalse(Schema::hasTable('consent_events'));
        $this->assertFalse(Schema::hasTable('hospital_submissions'));
    }

    public function test_a_high_priority_visit_cannot_select_a_clinic(): void
    {
        $visit = $this->createCompletedVisit('HIGH');
        $this->hospital('er', 'ER_CAPABLE');
        $clinic = $this->hospital('clinic', 'CLINIC_ONLY');

        $this->withSession(['active_visit_reference' => $visit->getKey()])
            ->post('/intake/routing', ['hospital_id' => $clinic->getKey()])
            ->assertSessionHasErrors('hospital_id');

        $this->assertNull($visit->fresh()->preselected_hospital_id);
    }

    /** @param array<string, string> $answers */
    private function createVisit(array $answers = []): Visit
    {
        $patient = Patient::query()->create([
            'normalized_phone' => '+233200000099',
            'first_name' => 'Demo',
            'last_name' => 'Patient',
            'date_of_birth' => '1990-01-01',
        ]);

        $visit = $patient->visits()->create([
            'status' => $answers === [] ? 'DRAFT' : 'ASSESSING',
            'filling_as' => 'SELF',
            'age_years' => 36,
            'sex_at_birth' => 'UNKNOWN',
            'pregnancy_status' => 'NOT_APPLICABLE',
            'manual_location' => 'Accra',
            'intake_answers' => [
                'main_complaint' => 'Persistent headache',
                'safety_screen' => ['completed_at' => now()->toIso8601String(), 'red_flags' => []],
                'assessment_conversation' => $answers,
            ],
        ]);

        $visit->assessment()->create([
            'status' => $answers === [] ? 'PENDING' : 'PROCESSING',
            'question_count' => count($answers),
        ]);

        return $visit;
    }

    private function createCompletedVisit(string $severity): Visit
    {
        $visit = $this->createVisit();
        $action = $severity === 'HIGH' ? 'SEEK_EMERGENCY_CARE_NOW' : 'SEEK_CARE_SOON';
        $visit->assessment()->update([
            'status' => 'COMPLETE',
            'severity' => $severity,
            'recommended_action' => $action,
            'symptom_summary' => 'A factual test summary.',
            'red_flags' => $severity === 'HIGH' ? [RedFlagCode::ConcerningChestPain->value] : [],
            'reason_codes' => ['TEST_REASON'],
            'deterministic_red_flag_match' => $severity === 'HIGH',
        ]);

        return $visit->fresh(['assessment']);
    }

    private function hospital(
        string $slug,
        string $capability,
        bool $active = true,
        ?string $id = null,
    ): Hospital {
        return Hospital::query()->create([
            'id' => $id ?? fake()->uuid(),
            'slug' => $slug,
            'name' => ucfirst($slug).' Hospital',
            'brand_name' => ucfirst($slug),
            'capability' => $capability,
            'routing_mode' => 'AUTO',
            'accepting_submissions' => true,
            'is_active' => $active,
            'address' => 'Demo location, Accra',
        ]);
    }
}
