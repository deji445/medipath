<?php

namespace App\Services\Routing;

use App\Enums\HospitalCapability;
use App\Enums\SeverityLevel;
use App\Models\Hospital;
use App\Models\Visit;

final class HospitalRoutingService
{
    /**
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     fullName: string,
     *     address: string|null,
     *     capability: string,
     *     routingMode: string,
     *     distanceKm: float|null,
     *     suitabilityRank: int,
     *     reasons: array<int, string>,
     *     recommended: bool
     * }>
     */
    public function recommendations(Visit $visit): array
    {
        $severity = $visit->assessment?->severity;

        if (! $severity) {
            return [];
        }

        $recommendations = Hospital::query()
            ->where('is_active', true)
            ->where('accepting_submissions', true)
            ->when(
                $severity === SeverityLevel::High,
                fn ($query) => $query->where('capability', HospitalCapability::EmergencyCapable),
            )
            ->get()
            ->map(function (Hospital $hospital) use ($visit, $severity): array {
                $distance = $this->distanceInKilometres($visit, $hospital);
                $suitability = $this->suitabilityRank($severity, $hospital->capability);

                return [
                    'id' => $hospital->getKey(),
                    'name' => $hospital->brand_name ?: $hospital->name,
                    'fullName' => $hospital->name,
                    'address' => $hospital->address,
                    'capability' => $hospital->capability->value,
                    'routingMode' => $hospital->routing_mode->value,
                    'distanceKm' => $distance,
                    'suitabilityRank' => $suitability,
                    'reasons' => $this->reasons($visit, $hospital, $severity, $distance),
                ];
            })
            ->all();

        usort($recommendations, function (array $left, array $right): int {
            $suitability = $left['suitabilityRank'] <=> $right['suitabilityRank'];

            if ($suitability !== 0) {
                return $suitability;
            }

            $leftDistance = $left['distanceKm'] ?? PHP_FLOAT_MAX;
            $rightDistance = $right['distanceKm'] ?? PHP_FLOAT_MAX;
            $distance = $leftDistance <=> $rightDistance;

            return $distance !== 0 ? $distance : $left['id'] <=> $right['id'];
        });

        return array_map(
            fn (array $hospital, int $index): array => [
                ...$hospital,
                'recommended' => $index === 0,
            ],
            $recommendations,
            array_keys($recommendations),
        );
    }

    public function eligibleHospital(Visit $visit, string $hospitalId): ?Hospital
    {
        $eligibleIds = array_column($this->recommendations($visit), 'id');

        if (! in_array($hospitalId, $eligibleIds, true)) {
            return null;
        }

        return Hospital::query()->find($hospitalId);
    }

    private function suitabilityRank(SeverityLevel $severity, HospitalCapability $capability): int
    {
        $preferred = match ($severity) {
            SeverityLevel::High, SeverityLevel::Medium => HospitalCapability::EmergencyCapable,
            SeverityLevel::Low => HospitalCapability::ClinicOnly,
        };

        return $capability === $preferred ? 0 : 1;
    }

    /** @return array<int, string> */
    private function reasons(
        Visit $visit,
        Hospital $hospital,
        SeverityLevel $severity,
        ?float $distance,
    ): array {
        $reasons = [];

        if ($hospital->capability === HospitalCapability::EmergencyCapable) {
            $reasons[] = $severity === SeverityLevel::High
                ? 'Can receive higher-acuity emergency cases'
                : 'Emergency-capable clinical services';
        } else {
            $reasons[] = 'Suitable for routine and lower-acuity concerns';
        }

        $reasons[] = 'Currently accepting MediPath prototype submissions';

        if ($distance !== null) {
            $reasons[] = number_format($distance, 1).' km from the supplied location';
        } elseif ($visit->manual_location) {
            $reasons[] = 'Available for the entered area; live distance is unavailable';
        } else {
            $reasons[] = 'Distance unavailable; ranked deterministically by suitability';
        }

        return $reasons;
    }

    private function distanceInKilometres(Visit $visit, Hospital $hospital): ?float
    {
        if ($visit->latitude === null || $visit->longitude === null
            || $hospital->latitude === null || $hospital->longitude === null) {
            return null;
        }

        $earthRadius = 6371;
        $latFrom = deg2rad((float) $visit->latitude);
        $lonFrom = deg2rad((float) $visit->longitude);
        $latTo = deg2rad((float) $hospital->latitude);
        $lonTo = deg2rad((float) $hospital->longitude);
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2,
        ));

        return round($earthRadius * $angle, 1);
    }
}
