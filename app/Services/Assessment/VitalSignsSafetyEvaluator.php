<?php

namespace App\Services\Assessment;

use App\Models\Visit;

final class VitalSignsSafetyEvaluator
{
    /**
     * Uses the adult NEWS2 ranges available from this intake. A partial score
     * never substitutes for clinical assessment.
     *
     * @return array{score: int, critical: bool, findings: array<int, string>}
     */
    public function evaluate(Visit $visit): array
    {
        if ($visit->age_years < 16) {
            return ['score' => 0, 'critical' => false, 'findings' => []];
        }

        $vitals = $visit->manual_vitals ?? [];
        $score = 0;
        $hasSingleCriticalReading = false;
        $findings = [];

        if (isset($vitals['oxygen_saturation_percent']) && is_numeric($vitals['oxygen_saturation_percent'])) {
            $oxygen = (float) $vitals['oxygen_saturation_percent'];
            $oxygenScore = match (true) {
                $oxygen <= 91 => 3,
                $oxygen <= 93 => 2,
                $oxygen <= 95 => 1,
                default => 0,
            };
            $score += $oxygenScore;

            if ($oxygenScore > 0) {
                $findings[] = 'Oxygen saturation is outside the usual adult range';
            }

            if ($oxygenScore === 3) {
                $hasSingleCriticalReading = true;
            }
        }

        if (isset($vitals['heart_rate_bpm']) && is_numeric($vitals['heart_rate_bpm'])) {
            $heartRate = (float) $vitals['heart_rate_bpm'];
            $heartRateScore = match (true) {
                $heartRate <= 40, $heartRate >= 131 => 3,
                $heartRate >= 111 => 2,
                $heartRate <= 50, $heartRate >= 91 => 1,
                default => 0,
            };
            $score += $heartRateScore;

            if ($heartRateScore > 0) {
                $findings[] = 'Heart rate is outside the usual adult range';
            }

            if ($heartRateScore === 3) {
                $hasSingleCriticalReading = true;
            }
        }

        if (isset($vitals['temperature_c']) && is_numeric($vitals['temperature_c'])) {
            $temperature = (float) $vitals['temperature_c'];
            $temperatureScore = match (true) {
                $temperature <= 35 => 3,
                $temperature >= 39.1 => 2,
                $temperature <= 36, $temperature >= 38.1 => 1,
                default => 0,
            };
            $score += $temperatureScore;

            if ($temperatureScore > 0) {
                $findings[] = 'Temperature is outside the usual adult range';
            }

            if ($temperatureScore === 3) {
                $hasSingleCriticalReading = true;
            }
        }

        return [
            'score' => $score,
            'critical' => $hasSingleCriticalReading || $score >= 5,
            'findings' => $findings,
        ];
    }
}
