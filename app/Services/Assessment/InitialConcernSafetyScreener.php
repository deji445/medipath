<?php

namespace App\Services\Assessment;

use App\Enums\AssessmentStatus;
use App\Enums\RecommendedAction;
use App\Enums\RedFlagCode;
use App\Enums\SeverityLevel;
use App\Enums\VisitStatus;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Throwable;

final class InitialConcernSafetyScreener
{
    public function __construct(
        private readonly AssessmentProvider $provider,
        private readonly DeterministicEmergencyScreener $emergencyScreener,
    ) {}

    public function screen(Visit $visit): bool
    {
        $concern = trim((string) ($visit->intake_answers['main_complaint'] ?? ''));
        $screening = $this->emergencyScreener->screenVisit($visit);
        $deterministicFlags = array_map(
            fn (RedFlagCode $code): string => $code->value,
            $screening['red_flags'],
        );
        $aiResult = null;
        $providerError = null;

        if ($deterministicFlags === []
            && config('medipath.assessment.driver') === 'gemini'
            && config('medipath.assessment.initial_ai_safety_screen')) {
            try {
                $aiResult = $this->provider->assess(
                    $visit->fresh(['patient', 'assessment']),
                    [],
                    hash('sha256', 'medipath-initial-safety:'.$visit->getKey().':1.0'),
                );
            } catch (Throwable $exception) {
                report($exception);
                $providerError = $exception instanceof AssessmentUnavailableException
                    ? $exception->errorCode
                    : 'AUTOMATED_SCREEN_UNAVAILABLE';
            }
        }

        $matchedFlags = $deterministicFlags;

        if ($aiResult?->severity === SeverityLevel::High) {
            $matchedFlags = array_values(array_unique([
                ...$matchedFlags,
                ...($aiResult->redFlags ?: [RedFlagCode::UnspecifiedEmergencyConcern->value]),
            ]));
        }

        DB::transaction(function () use (
            $visit,
            $concern,
            $deterministicFlags,
            $matchedFlags,
            $aiResult,
            $providerError,
            $screening,
        ): void {
            $intakeAnswers = $visit->intake_answers ?? [];
            $intakeAnswers['safety_screen'] = array_filter([
                'completed_at' => now()->toIso8601String(),
                'red_flags' => $matchedFlags,
                'source' => 'automated_concern_screen',
                'provider_model' => $aiResult?->providerModel,
                'provider_error' => $providerError,
                'vital_findings' => $screening['vital_findings'] ?: null,
            ], fn (mixed $value): bool => $value !== null);
            $visit->update(['intake_answers' => $intakeAnswers]);

            if ($matchedFlags === []) {
                return;
            }

            $summary = $aiResult !== null
                ? $aiResult->symptomSummary
                : 'The main concern contains language that may indicate an emergency warning sign.';

            if ($aiResult === null && $concern !== '') {
                $summary .= ' Main concern: '.$concern;
            }

            $assessmentUpdate = [
                'status' => AssessmentStatus::Complete,
                'severity' => SeverityLevel::High,
                'recommended_action' => RecommendedAction::SeekEmergencyCareNow,
                'symptom_summary' => mb_substr($summary, 0, 1500),
                'red_flags' => $matchedFlags,
                'reason_codes' => $matchedFlags,
                'deterministic_red_flag_match' => $deterministicFlags !== [],
                'question_count' => 0,
                'error_code' => null,
            ];

            if ($aiResult !== null) {
                $assessmentUpdate['ai_schema_version'] = $aiResult->schemaVersion;
                $assessmentUpdate['provider_model'] = $aiResult->providerModel;
            }

            $visit->assessment()->update($assessmentUpdate);
            $visit->update([
                'status' => VisitStatus::AssessmentComplete,
                'completed_at' => now(),
            ]);
        });

        return $matchedFlags !== [];
    }
}
