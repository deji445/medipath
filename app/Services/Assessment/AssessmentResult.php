<?php

namespace App\Services\Assessment;

use App\Enums\AssessmentReasonCode;
use App\Enums\RecommendedAction;
use App\Enums\RedFlagCode;
use App\Enums\SeverityLevel;

final readonly class AssessmentResult
{
    /**
     * @param  array<int, string>  $redFlags
     * @param  array<int, string>  $reasonCodes
     */
    public function __construct(
        public SeverityLevel $severity,
        public RecommendedAction $recommendedAction,
        public string $symptomSummary,
        public array $redFlags,
        public array $reasonCodes,
        public string $schemaVersion,
        public string $providerModel,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromProviderPayload(array $payload, string $providerModel): self
    {
        $expectedKeys = [
            'assessment_complete',
            'reason_codes',
            'recommended_action',
            'red_flags',
            'schema_version',
            'severity',
            'symptom_summary',
        ];
        $actualKeys = array_keys($payload);
        sort($actualKeys);

        if ($actualKeys !== $expectedKeys) {
            throw new AssessmentUnavailableException('AI_RESPONSE_UNKNOWN_FIELDS');
        }

        if (($payload['schema_version'] ?? null) !== '1.0'
            || ($payload['assessment_complete'] ?? null) !== true) {
            throw new AssessmentUnavailableException('AI_RESPONSE_INVALID_SCHEMA');
        }

        $severity = is_string($payload['severity'] ?? null)
            ? SeverityLevel::tryFrom($payload['severity'])
            : null;
        $recommendedAction = is_string($payload['recommended_action'] ?? null)
            ? RecommendedAction::tryFrom($payload['recommended_action'])
            : null;

        if (! $severity || ! $recommendedAction || $recommendedAction !== self::actionFor($severity)) {
            throw new AssessmentUnavailableException('AI_RESPONSE_INVALID_SEVERITY');
        }

        $summary = $payload['symptom_summary'] ?? null;

        if (! is_string($summary)
            || mb_strlen(trim($summary)) < 1
            || mb_strlen($summary) > 1500
            || strip_tags($summary) !== $summary
            || self::containsMarkdown($summary)) {
            throw new AssessmentUnavailableException('AI_RESPONSE_INVALID_SUMMARY');
        }

        $redFlags = self::knownStringArray(
            $payload['red_flags'] ?? null,
            array_map(fn (RedFlagCode $code): string => $code->value, RedFlagCode::cases()),
            'AI_RESPONSE_INVALID_RED_FLAGS',
        );
        $knownReasonCodes = [
            ...array_map(fn (AssessmentReasonCode $code): string => $code->value, AssessmentReasonCode::cases()),
            ...array_map(fn (RedFlagCode $code): string => $code->value, RedFlagCode::cases()),
        ];
        $reasonCodes = self::knownStringArray(
            $payload['reason_codes'] ?? null,
            $knownReasonCodes,
            'AI_RESPONSE_INVALID_REASON_CODES',
        );

        if ($reasonCodes === [] || ($redFlags !== [] && $severity !== SeverityLevel::High)) {
            throw new AssessmentUnavailableException('AI_RESPONSE_INVALID_SAFETY_MAPPING');
        }

        return new self(
            severity: $severity,
            recommendedAction: $recommendedAction,
            symptomSummary: trim($summary),
            redFlags: $redFlags,
            reasonCodes: $reasonCodes,
            schemaVersion: '1.0',
            providerModel: $providerModel,
        );
    }

    private static function actionFor(SeverityLevel $severity): RecommendedAction
    {
        return match ($severity) {
            SeverityLevel::Low => RecommendedAction::RoutineCareOrSelfCare,
            SeverityLevel::Medium => RecommendedAction::SeekCareSoon,
            SeverityLevel::High => RecommendedAction::SeekEmergencyCareNow,
        };
    }

    /**
     * @param  array<int, string>  $knownValues
     * @return array<int, string>
     */
    private static function knownStringArray(mixed $value, array $knownValues, string $errorCode): array
    {
        if (! is_array($value) || count($value) > 20) {
            throw new AssessmentUnavailableException($errorCode);
        }

        foreach ($value as $item) {
            if (! is_string($item) || ! in_array($item, $knownValues, true)) {
                throw new AssessmentUnavailableException($errorCode);
            }
        }

        return array_values(array_unique($value));
    }

    private static function containsMarkdown(string $value): bool
    {
        return preg_match('/(^|\n)\s{0,3}(#{1,6}\s|[-*+]\s|\d+\.\s|>|```)/m', $value) === 1
            || preg_match('/!?\[[^\]]*\]\([^)]*\)/', $value) === 1;
    }
}
