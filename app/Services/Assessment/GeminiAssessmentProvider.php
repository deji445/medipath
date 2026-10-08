<?php

namespace App\Services\Assessment;

use App\Enums\AssessmentReasonCode;
use App\Enums\RecommendedAction;
use App\Enums\RedFlagCode;
use App\Enums\SeverityLevel;
use App\Models\Visit;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;

final class GeminiAssessmentProvider implements AssessmentProvider
{
    public function __construct(private readonly AssessmentInputBuilder $inputBuilder) {}

    public function assess(Visit $visit, array $answers, string $idempotencyKey): AssessmentResult
    {
        $apiKey = (string) config('medipath.assessment.gemini.api_key');

        if ($apiKey === '') {
            throw new AssessmentUnavailableException('GEMINI_API_KEY_MISSING');
        }

        $payload = $this->requestPayload($this->inputBuilder->build($visit, $answers));
        $lastError = new AssessmentUnavailableException('GEMINI_UNAVAILABLE');

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->withHeaders([
                        'x-goog-api-key' => $apiKey,
                        'X-Request-Id' => $idempotencyKey,
                    ])
                    ->connectTimeout((int) config('medipath.assessment.gemini.connect_timeout', 5))
                    ->timeout((int) config('medipath.assessment.gemini.timeout', 20))
                    ->post((string) config('medipath.assessment.gemini.endpoint'), $payload);
            } catch (ConnectionException $exception) {
                $lastError = new AssessmentUnavailableException('GEMINI_CONNECTION_FAILED', $exception);

                if ($attempt === 1) {
                    $this->pauseBeforeRetry();

                    continue;
                }

                throw $lastError;
            }

            if (! $response->successful()) {
                $lastError = new AssessmentUnavailableException('GEMINI_HTTP_'.$response->status());

                if ($attempt === 1 && $this->isRetryable($response)) {
                    $this->pauseBeforeRetry();

                    continue;
                }

                throw $lastError;
            }

            try {
                return AssessmentResult::fromProviderPayload(
                    $this->decodeOutput($response),
                    $this->model(),
                );
            } catch (AssessmentUnavailableException $exception) {
                $lastError = $exception;

                if ($attempt === 1) {
                    $this->pauseBeforeRetry();

                    continue;
                }
            }
        }

        throw $lastError;
    }

    public function model(): string
    {
        return (string) config('medipath.assessment.gemini.model', 'gemini-3.8-flash');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function requestPayload(array $input): array
    {
        try {
            $encodedInput = json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new AssessmentUnavailableException('ASSESSMENT_INPUT_INVALID', $exception);
        }

        return [
            'model' => $this->model(),
            'system_instruction' => $this->systemInstruction(),
            'input' => $encodedInput,
            'store' => false,
            'generation_config' => [
                'max_output_tokens' => 1200,
                'thinking_level' => 'low',
            ],
            'response_format' => [
                'type' => 'text',
                'mime_type' => 'application/json',
                'schema' => $this->responseSchema(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function decodeOutput(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body) || ($body['status'] ?? null) !== 'completed') {
            throw new AssessmentUnavailableException('GEMINI_INCOMPLETE_RESPONSE');
        }

        $steps = $body['steps'] ?? null;

        if (! is_array($steps)) {
            throw new AssessmentUnavailableException('GEMINI_MALFORMED_RESPONSE');
        }

        $outputText = null;

        foreach (array_reverse($steps) as $step) {
            if (! is_array($step) || ($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            foreach (array_reverse(is_array($step['content'] ?? null) ? $step['content'] : []) as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'text' && is_string($content['text'] ?? null)) {
                    $outputText = $content['text'];
                    break 2;
                }
            }
        }

        if (! is_string($outputText) || $outputText === '') {
            throw new AssessmentUnavailableException('GEMINI_MISSING_OUTPUT');
        }

        try {
            $decoded = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AssessmentUnavailableException('GEMINI_INVALID_JSON', $exception);
        }

        if (! is_array($decoded)) {
            throw new AssessmentUnavailableException('GEMINI_INVALID_JSON');
        }

        return $decoded;
    }

    private function isRetryable(Response $response): bool
    {
        return in_array($response->status(), [408, 429], true) || $response->serverError();
    }

    private function pauseBeforeRetry(): void
    {
        $milliseconds = max(0, (int) config('medipath.assessment.gemini.retry_delay_ms', 250));

        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    private function systemInstruction(): string
    {
        return <<<'PROMPT'
You are MediPath's medical intake assistant for a dummy-data prototype. Use only facts in the supplied JSON.

Return the required JSON result only. Never diagnose a condition, prescribe or change medication, give a numeric probability, claim the patient is safe, invent facts, or mention hospital availability. Assign exactly one severity and its required action. HIGH maps to SEEK_EMERGENCY_CARE_NOW, MEDIUM to SEEK_CARE_SOON, and LOW to ROUTINE_CARE_OR_SELF_CARE.

The deterministic emergency screen has already run, but you must still report a known red-flag code if the supplied information indicates it. If information is contradictory or insufficient, use MEDIUM and SEEK_CARE_SOON. Use a short factual plain-text summary with no diagnosis, Markdown, or HTML. Use only the reason and red-flag codes allowed by the schema.
PROMPT;
    }

    /** @return array<string, mixed> */
    private function responseSchema(): array
    {
        $redFlagCodes = array_map(fn (RedFlagCode $code): string => $code->value, RedFlagCode::cases());
        $reasonCodes = [
            ...array_map(fn (AssessmentReasonCode $code): string => $code->value, AssessmentReasonCode::cases()),
            ...$redFlagCodes,
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'schema_version' => ['type' => 'string', 'enum' => ['1.0']],
                'severity' => [
                    'type' => 'string',
                    'enum' => array_map(fn (SeverityLevel $level): string => $level->value, SeverityLevel::cases()),
                ],
                'recommended_action' => [
                    'type' => 'string',
                    'enum' => array_map(fn (RecommendedAction $action): string => $action->value, RecommendedAction::cases()),
                ],
                'symptom_summary' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1500],
                'red_flags' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $redFlagCodes]],
                'reason_codes' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string', 'enum' => $reasonCodes]],
                'assessment_complete' => ['type' => 'boolean'],
            ],
            'required' => [
                'schema_version',
                'severity',
                'recommended_action',
                'symptom_summary',
                'red_flags',
                'reason_codes',
                'assessment_complete',
            ],
        ];
    }
}
