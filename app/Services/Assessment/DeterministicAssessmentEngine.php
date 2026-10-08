<?php

namespace App\Services\Assessment;

use App\Enums\AssessmentReasonCode;
use App\Enums\RecommendedAction;
use App\Enums\SeverityLevel;
use App\Models\Visit;

final class DeterministicAssessmentEngine
{
    public function __construct(
        private readonly AssessmentQuestionnaire $questionnaire,
        private readonly VitalSignsSafetyEvaluator $vitalSignsEvaluator,
    ) {}

    /**
     * @param  array<string, string>  $answers
     * @return array{severity: SeverityLevel, recommended_action: RecommendedAction, symptom_summary: string, reason_codes: array<int, string>}
     */
    public function assess(Visit $visit, array $answers): array
    {
        $reasonCodes = [AssessmentReasonCode::NoRedFlagIdentified->value];
        $score = 0;
        $vitalSigns = $this->vitalSignsEvaluator->evaluate($visit);

        if ($vitalSigns['score'] > 0) {
            $score += 2;
            $reasonCodes[] = AssessmentReasonCode::AbnormalVitalSignsReported->value;
        }

        if (($answers['trend'] ?? null) === 'WORSENING') {
            $score += 2;
            $reasonCodes[] = AssessmentReasonCode::SymptomsWorsening->value;
        }

        if (($answers['intensity'] ?? null) === 'SEVERE') {
            $score += 2;
            $reasonCodes[] = AssessmentReasonCode::SevereReportedIntensity->value;
        } elseif (($answers['intensity'] ?? null) === 'MODERATE') {
            $score++;
        }

        if (($answers['daily_impact'] ?? null) === 'UNABLE') {
            $score += 2;
            $reasonCodes[] = AssessmentReasonCode::DailyActivityPrevented->value;
        } elseif (($answers['daily_impact'] ?? null) === 'LIMITED') {
            $score++;
            $reasonCodes[] = AssessmentReasonCode::DailyActivityLimited->value;
        }

        if (($answers['associated_symptoms'] ?? 'NONE') !== 'NONE') {
            $score++;
            $reasonCodes[] = AssessmentReasonCode::AssociatedSymptomsReported->value;
        }

        if (in_array($answers['duration'] ?? null, ['FOUR_TO_SEVEN_DAYS', 'MORE_THAN_WEEK'], true)) {
            $score++;
            $reasonCodes[] = AssessmentReasonCode::PersistentSymptoms->value;
        }

        $severity = $score >= 2 ? SeverityLevel::Medium : SeverityLevel::Low;
        $action = $severity === SeverityLevel::Medium
            ? RecommendedAction::SeekCareSoon
            : RecommendedAction::RoutineCareOrSelfCare;

        $mainConcern = trim((string) ($visit->intake_answers['main_complaint'] ?? 'The reported concern'));
        $summary = sprintf(
            '%s. Reported duration: %s. Pattern: %s. Current intensity: %s. Effect on daily activity: %s. Associated symptoms: %s.',
            rtrim($mainConcern, '.'),
            $this->questionnaire->label('duration', $answers['duration']),
            $this->questionnaire->label('trend', $answers['trend']),
            $this->questionnaire->label('intensity', $answers['intensity']),
            $this->questionnaire->label('daily_impact', $answers['daily_impact']),
            $this->questionnaire->label('associated_symptoms', $answers['associated_symptoms']),
        );

        return [
            'severity' => $severity,
            'recommended_action' => $action,
            'symptom_summary' => mb_substr($summary, 0, 1500),
            'reason_codes' => array_values(array_unique($reasonCodes)),
        ];
    }
}
