<?php

namespace Tests\Unit;

use App\Models\Patient;
use App\Models\Visit;
use App\Services\Ai\AiCircuitBreaker;
use App\Services\Ai\AiRequestRecorder;
use App\Services\Assessment\AssessmentInputBuilder;
use App\Services\Assessment\GeminiAssessmentConversationProvider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeminiAssessmentConversationProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_it_requests_one_deidentified_follow_up_question(): void
    {
        config()->set([
            'medipath.assessment.gemini.api_key' => 'test-key',
            'medipath.assessment.gemini.model' => 'gemini-test',
            'medipath.assessment.gemini.endpoint' => 'https://generativelanguage.googleapis.com/v1/interactions',
            'medipath.assessment.gemini.retry_delay_ms' => 0,
        ]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'status' => 'completed',
                'steps' => [[
                    'type' => 'model_output',
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode([
                            'schema_version' => '1.0',
                            'assessment_complete' => false,
                            'question' => 'Does anything make the pain better or worse?',
                            'severity' => null,
                            'recommended_action' => null,
                            'symptom_summary' => null,
                            'red_flags' => [],
                            'reason_codes' => [],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);

        $turn = (new GeminiAssessmentConversationProvider(
            new AssessmentInputBuilder,
            new AiRequestRecorder,
            new AiCircuitBreaker,
        ))->nextTurn(
            $this->visit(),
            ['ai_1' => 'It started yesterday and is getting worse.'],
            ['ai_1' => 'When did it start and how has it changed?'],
            'conversation-request-1',
        );

        $this->assertSame('Does anything make the pain better or worse?', $turn->question);
        $this->assertNull($turn->result);
        $this->assertDatabaseHas('ai_request_events', [
            'feature' => 'assessment_conversation',
            'outcome' => 'SUCCEEDED',
            'attempt_count' => 1,
            'retry_count' => 0,
        ]);
        Http::assertSent(function (Request $request): bool {
            $input = json_decode((string) $request->data()['input'], true);

            $this->assertSame('Persistent headache', $input['conversation'][0]['answer']);
            $this->assertSame('It started yesterday and is getting worse.', $input['conversation'][1]['answer']);
            $this->assertArrayNotHasKey('manual_location', $input);

            return $request->hasHeader('X-Request-Id', 'conversation-request-1');
        });
    }

    private function visit(): Visit
    {
        $patient = new Patient([
            'normalized_phone' => '+233000000000',
            'first_name' => 'Dummy',
            'last_name' => 'Patient',
        ]);
        $patient->setAttribute('id', '10000000-0000-4000-8000-000000000001');
        $patient->setRelation('conditions', new Collection);
        $patient->setRelation('allergies', new Collection);
        $patient->setRelation('medications', new Collection);

        $visit = new Visit([
            'patient_id' => $patient->getKey(),
            'status' => 'ASSESSING',
            'filling_as' => 'SELF',
            'age_years' => 36,
            'sex_at_birth' => 'UNKNOWN',
            'pregnancy_status' => 'NOT_APPLICABLE',
            'intake_answers' => ['main_complaint' => 'Persistent headache'],
            'manual_location' => 'Private location',
        ]);
        $visit->setAttribute('id', '20000000-0000-4000-8000-000000000002');
        $visit->setRelation('patient', $patient);

        return $visit;
    }
}
