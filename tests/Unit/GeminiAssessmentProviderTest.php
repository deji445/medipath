<?php

namespace Tests\Unit;

use App\Models\Patient;
use App\Models\PatientCondition;
use App\Models\PatientMedication;
use App\Models\Visit;
use App\Services\Ai\AiCircuitBreaker;
use App\Services\Ai\AiRequestRecorder;
use App\Services\Assessment\AssessmentInputBuilder;
use App\Services\Assessment\AssessmentUnavailableException;
use App\Services\Assessment\GeminiAssessmentProvider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeminiAssessmentProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'medipath.assessment.gemini.api_key' => 'test-gemini-key',
            'medipath.assessment.gemini.model' => 'gemini-3.8-flash',
            'medipath.assessment.gemini.endpoint' => 'https://generativelanguage.googleapis.com/v1/interactions',
            'medipath.assessment.gemini.retry_delay_ms' => 0,
        ]);

        Schema::create('ai_request_events', function (Blueprint $table): void {
            $table->id();
            $table->string('feature');
            $table->string('provider');
            $table->string('model');
            $table->string('outcome');
            $table->unsignedInteger('duration_ms');
            $table->unsignedTinyInteger('attempt_count');
            $table->unsignedTinyInteger('retry_count');
            $table->string('error_code')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function test_it_sends_only_the_deidentified_contract_and_accepts_valid_structured_output(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse($this->validResult()), 200),
        ]);

        $result = $this->provider()->assess(
            $this->visit(),
            $this->answers(),
            'stable-request-key',
        );

        $this->assertSame('MEDIUM', $result->severity->value);
        $this->assertSame('SEEK_CARE_SOON', $result->recommendedAction->value);
        $this->assertSame('gemini-3.8-flash', $result->providerModel);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $input = json_decode((string) ($payload['input'] ?? ''), true);

            $this->assertTrue($request->hasHeader('x-goog-api-key', 'test-gemini-key'));
            $this->assertTrue($request->hasHeader('X-Request-Id', 'stable-request-key'));
            $this->assertFalse($payload['store']);
            $this->assertSame('application/json', $payload['response_format']['mime_type']);
            $this->assertSame('Persistent headache', $input['conversation'][0]['answer']);
            $this->assertSame(['Asthma'], $input['conditions']);
            $this->assertArrayNotHasKey('first_name', $input);
            $this->assertArrayNotHasKey('last_name', $input);
            $this->assertArrayNotHasKey('phone_number', $input);
            $this->assertArrayNotHasKey('manual_location', $input);

            return true;
        });
        $this->assertDatabaseHas('ai_request_events', [
            'feature' => 'structured_assessment',
            'outcome' => 'SUCCEEDED',
            'attempt_count' => 1,
            'retry_count' => 0,
        ]);
    }

    public function test_it_retries_invalid_output_once_with_the_same_request_key(): void
    {
        $invalid = $this->validResult();
        $invalid['recommended_action'] = 'ROUTINE_CARE_OR_SELF_CARE';

        Http::fakeSequence()
            ->push($this->geminiResponse($invalid), 200)
            ->push($this->geminiResponse($this->validResult()), 200);

        $result = $this->provider()->assess($this->visit(), $this->answers(), 'same-key');

        $this->assertSame('MEDIUM', $result->severity->value);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Request-Id', 'same-key'));
        $this->assertDatabaseHas('ai_request_events', [
            'feature' => 'structured_assessment',
            'outcome' => 'SUCCEEDED',
            'attempt_count' => 2,
            'retry_count' => 1,
        ]);
    }

    public function test_two_provider_failures_return_unavailable_instead_of_a_default_result(): void
    {
        Http::fakeSequence()
            ->push(['error' => ['message' => 'Unavailable']], 503)
            ->push(['error' => ['message' => 'Unavailable']], 503);

        try {
            $this->provider()->assess($this->visit(), $this->answers(), 'same-key');
            $this->fail('Expected the assessment provider to fail safely.');
        } catch (AssessmentUnavailableException $exception) {
            $this->assertSame('GEMINI_HTTP_503', $exception->errorCode);
        }

        Http::assertSentCount(2);
        $this->assertDatabaseHas('ai_request_events', [
            'feature' => 'structured_assessment',
            'outcome' => 'FAILED',
            'attempt_count' => 2,
            'retry_count' => 1,
            'error_code' => 'GEMINI_HTTP_503',
        ]);
    }

    private function provider(): GeminiAssessmentProvider
    {
        return new GeminiAssessmentProvider(
            new AssessmentInputBuilder,
            new AiRequestRecorder,
            new AiCircuitBreaker,
        );
    }

    /** @return array<string, string> */
    private function answers(): array
    {
        return [
            'duration' => 'ONE_TO_THREE_DAYS',
            'trend' => 'WORSENING',
            'intensity' => 'MODERATE',
            'daily_impact' => 'LIMITED',
            'new_warning_sign' => 'NONE',
            'associated_symptoms' => 'FEVER',
        ];
    }

    private function visit(): Visit
    {
        $patient = new Patient([
            'normalized_phone' => '+233000000000',
            'first_name' => 'Dummy',
            'last_name' => 'Patient',
        ]);
        $patient->setAttribute('id', '10000000-0000-4000-8000-000000000001');
        $patient->setRelation('conditions', new Collection([
            new PatientCondition(['name' => 'Asthma', 'is_active' => true]),
        ]));
        $patient->setRelation('allergies', new Collection);
        $patient->setRelation('medications', new Collection([
            new PatientMedication([
                'name' => 'Dummy inhaler',
                'dose' => null,
                'frequency' => 'As recorded',
                'is_active' => true,
            ]),
        ]));

        $visit = new Visit([
            'patient_id' => $patient->getKey(),
            'status' => 'ASSESSING',
            'filling_as' => 'SELF',
            'age_years' => 36,
            'sex_at_birth' => 'UNKNOWN',
            'pregnancy_status' => 'NOT_APPLICABLE',
            'intake_answers' => ['main_complaint' => 'Persistent headache'],
            'manual_location' => 'Private exact address',
        ]);
        $visit->setAttribute('id', '20000000-0000-4000-8000-000000000002');
        $visit->setRelation('patient', $patient);

        return $visit;
    }

    /** @return array<string, mixed> */
    private function validResult(): array
    {
        return [
            'schema_version' => '1.0',
            'severity' => 'MEDIUM',
            'recommended_action' => 'SEEK_CARE_SOON',
            'symptom_summary' => 'The patient reported a persistent headache that is worsening and limiting daily activity.',
            'red_flags' => [],
            'reason_codes' => ['SYMPTOMS_WORSENING', 'DAILY_ACTIVITY_LIMITED'],
            'assessment_complete' => true,
        ];
    }

    /** @param array<string, mixed> $result */
    private function geminiResponse(array $result): array
    {
        return [
            'status' => 'completed',
            'steps' => [[
                'type' => 'model_output',
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode($result, JSON_THROW_ON_ERROR),
                ]],
            ]],
        ];
    }
}
