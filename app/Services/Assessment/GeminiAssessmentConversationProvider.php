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

final class GeminiAssessmentConversationProvider implements AssessmentConversationProvider
{
    public function __construct(private readonly AssessmentInputBuilder $inputBuilder) {}

    public function nextTurn(
        Visit $visit,
        array $answers,
        array $questions,
        string $idempotencyKey,
        bool $mustComplete = false,
    ): AssessmentConversationTurn {
        $apiKey = (string) config('medipath.assessment.gemini.api_key');

        if ($apiKey === '') {
            throw new AssessmentUnavailableException('GEMINI_API_KEY_MISSING');
        }

        $input = $this->inputBuilder->buildConversation($visit, $answers, $questions);
        $maxQuestions = max(1, min((int) config('medipath.assessment.max_questions', 10), 10));
        $input['question_limit'] = $maxQuestions;
        $input['conversation_instruction'] = $mustComplete
            ? 'The question limit has been reached. Return a completed result now. If uncertain, use MEDIUM.'
            : 'Return a completed result as soon as the supplied information is sufficient. Otherwise return one next question.';
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
                    ->post((string) config('medipath.assessment.gemini.endpoint'), [
                        'model' => $this->model(),
                        'system_instruction' => $this->systemInstruction(),
                        'input' => json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
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
                    ]);
            } catch (ConnectionException $exception) {
                $lastError = new AssessmentUnavailableException('GEMINI_CONNECTION_FAILED', $exception);

                if ($attempt === 1) {
                    $this->pauseBeforeRetry();

                    continue;
                }

                throw $lastError;
            } catch (JsonException $exception) {
                throw new AssessmentUnavailableException('ASSESSMENT_INPUT_INVALID', $exception);
            }

            if (! $response->successful()) {
                $lastError = new AssessmentUnavailableException('GEMINI_HTTP_'.$response->status());

                if ($attempt === 1 && ($response->status() === 408 || $response->status() === 429 || $response->serverError())) {
                    $this->pauseBeforeRetry();

                    continue;
                }

                throw $lastError;
            }

            try {
                return AssessmentConversationTurn::fromProviderPayload(
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

    /** @return array<string, mixed> */
    private function decodeOutput(Response $response): array
    {
        $body = $response->json();
        $steps = is_array($body) ? ($body['steps'] ?? null) : null;

        if (($body['status'] ?? null) !== 'completed' || ! is_array($steps)) {
            throw new AssessmentUnavailableException('GEMINI_INCOMPLETE_RESPONSE');
        }

        foreach (array_reverse($steps) as $step) {
            if (! is_array($step) || ($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            foreach (array_reverse(is_array($step['content'] ?? null) ? $step['content'] : []) as $content) {
                if (! is_array($content) || ($content['type'] ?? null) !== 'text' || ! is_string($content['text'] ?? null)) {
                    continue;
                }

                try {
                    $decoded = json_decode($content['text'], true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new AssessmentUnavailableException('GEMINI_INVALID_JSON', $exception);
                }

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        throw new AssessmentUnavailableException('GEMINI_MISSING_OUTPUT');
    }

    private function systemInstruction(): string
    {
        $maxQuestions = max(1, min((int) config('medipath.assessment.max_questions', 10), 10));

        return <<<PROMPT
You are MediPath's medical intake assistant for a dummy-data prototype. Use only facts in the supplied JSON.

Conduct a short symptom intake by either asking exactly one concise plain-language question or returning the completed structured assessment. Do not repeat a question already present in the conversation. Prioritize onset, duration, location, intensity, associated symptoms, relevant context, and effect on normal activity. Ask only what changes urgency. Finish early whenever the information is sufficient, and never ask more than {$maxQuestions} questions.

For a question response, set assessment_complete to false, put the question in question, set severity, recommended_action, and symptom_summary to null, and use empty arrays for red_flags and reason_codes.

For a completed response, set assessment_complete to true and question to null. Never diagnose, prescribe, change medication, give probabilities, claim the patient is safe, invent facts, or mention hospital availability. HIGH maps to SEEK_EMERGENCY_CARE_NOW, MEDIUM to SEEK_CARE_SOON, and LOW to ROUTINE_CARE_OR_SELF_CARE. If information is contradictory, unclear, or still insufficient at the question limit, use MEDIUM. Use only schema-listed codes and return JSON only.
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
                'assessment_complete' => ['type' => 'boolean'],
                'question' => ['type' => ['string', 'null'], 'maxLength' => 240],
                'severity' => ['type' => ['string', 'null'], 'enum' => [...array_map(fn (SeverityLevel $level): string => $level->value, SeverityLevel::cases()), null]],
                'recommended_action' => ['type' => ['string', 'null'], 'enum' => [...array_map(fn (RecommendedAction $action): string => $action->value, RecommendedAction::cases()), null]],
                'symptom_summary' => ['type' => ['string', 'null'], 'maxLength' => 1500],
                'red_flags' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $redFlagCodes]],
                'reason_codes' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $reasonCodes]],
            ],
            'required' => [
                'schema_version', 'assessment_complete', 'question', 'severity',
                'recommended_action', 'symptom_summary', 'red_flags', 'reason_codes',
            ],
        ];
    }

    private function pauseBeforeRetry(): void
    {
        $milliseconds = max(0, (int) config('medipath.assessment.gemini.retry_delay_ms', 250));

        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
