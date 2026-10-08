<?php

namespace App\Services\Assessment;

final readonly class AssessmentConversationTurn
{
    private function __construct(
        public ?string $question,
        public ?AssessmentResult $result,
        public string $providerModel,
    ) {}

    public static function question(string $question, string $providerModel): self
    {
        $question = trim($question);

        if ($question === '' || mb_strlen($question) > 240 || strip_tags($question) !== $question) {
            throw new AssessmentUnavailableException('AI_RESPONSE_INVALID_QUESTION');
        }

        return new self($question, null, $providerModel);
    }

    public static function complete(AssessmentResult $result): self
    {
        return new self(null, $result, $result->providerModel);
    }

    /** @param array<string, mixed> $payload */
    public static function fromProviderPayload(array $payload, string $providerModel): self
    {
        $expectedKeys = [
            'assessment_complete',
            'question',
            'reason_codes',
            'recommended_action',
            'red_flags',
            'schema_version',
            'severity',
            'symptom_summary',
        ];
        $actualKeys = array_keys($payload);
        sort($actualKeys);

        if ($actualKeys !== $expectedKeys || ($payload['schema_version'] ?? null) !== '1.0') {
            throw new AssessmentUnavailableException('AI_RESPONSE_INVALID_CONVERSATION_SCHEMA');
        }

        if (($payload['assessment_complete'] ?? null) === false) {
            if (($payload['severity'] ?? null) !== null
                || ($payload['recommended_action'] ?? null) !== null
                || ($payload['symptom_summary'] ?? null) !== null
                || ($payload['red_flags'] ?? null) !== []
                || ($payload['reason_codes'] ?? null) !== []) {
                throw new AssessmentUnavailableException('AI_RESPONSE_INVALID_QUESTION_STATE');
            }

            return self::question((string) ($payload['question'] ?? ''), $providerModel);
        }

        if (($payload['assessment_complete'] ?? null) !== true || ($payload['question'] ?? null) !== null) {
            throw new AssessmentUnavailableException('AI_RESPONSE_INVALID_COMPLETION_STATE');
        }

        unset($payload['question']);

        return self::complete(AssessmentResult::fromProviderPayload($payload, $providerModel));
    }
}
