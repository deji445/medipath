<?php

namespace App\Services\Assessment;

use App\Enums\RedFlagCode;
use App\Models\Visit;

final class DeterministicEmergencyScreener
{
    public function __construct(
        private readonly EmergencyConcernDetector $concernDetector,
        private readonly VitalSignsSafetyEvaluator $vitalSignsEvaluator,
    ) {}

    /** @return array<int, RedFlagCode> */
    public function screenText(string $text): array
    {
        return $this->concernDetector->detect($text);
    }

    /**
     * @return array{red_flags: array<int, RedFlagCode>, vital_findings: array<int, string>}
     */
    public function screenVisit(Visit $visit): array
    {
        $redFlags = $this->screenText(
            trim((string) ($visit->intake_answers['main_complaint'] ?? '')),
        );
        $vitalSigns = $this->vitalSignsEvaluator->evaluate($visit);

        if ($vitalSigns['critical']) {
            $redFlags[] = RedFlagCode::CriticalVitalSigns;
        }

        return [
            'red_flags' => array_values(array_unique($redFlags, SORT_REGULAR)),
            'vital_findings' => $vitalSigns['findings'],
        ];
    }

    public function emergencyChatMessage(): string
    {
        $contact = trim((string) config('medipath.emergency.phone', '112'));

        return __('consultant.emergency_message', [
            'phone' => $contact !== '' ? $contact : '112',
        ]);
    }
}
