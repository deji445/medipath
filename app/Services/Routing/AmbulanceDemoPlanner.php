<?php

namespace App\Services\Routing;

use App\Models\Hospital;
use App\Models\Visit;

final class AmbulanceDemoPlanner
{
    /**
     * @return array{
     *     distanceKm: float,
     *     etaMinMinutes: int,
     *     etaMaxMinutes: int,
     *     usesEstimatedLocation: bool,
     *     demoDurationSeconds: int,
     *     patientLocation: string
     * }
     */
    public function plan(Visit $visit, Hospital $hospital): array
    {
        $distance = $this->distanceInKilometres($visit, $hospital);
        $usesEstimatedLocation = $distance === null;
        $distance ??= 6.4;

        // The simulation assumes urban traffic at roughly 33 km/h and a
        // three-minute mobilisation period. It is not an operational ETA.
        $etaMin = max(6, (int) ceil(($distance / 33) * 60) + 3);

        return [
            'distanceKm' => round($distance, 1),
            'etaMinMinutes' => $etaMin,
            'etaMaxMinutes' => $etaMin + 4,
            'usesEstimatedLocation' => $usesEstimatedLocation,
            'demoDurationSeconds' => 72,
            'patientLocation' => trim((string) $visit->manual_location) !== ''
                ? (string) $visit->manual_location
                : 'Patient location',
        ];
    }

    private function distanceInKilometres(Visit $visit, Hospital $hospital): ?float
    {
        if ($visit->latitude === null || $visit->longitude === null
            || $hospital->latitude === null || $hospital->longitude === null) {
            return null;
        }

        $earthRadius = 6371;
        $latFrom = deg2rad((float) $hospital->latitude);
        $lonFrom = deg2rad((float) $hospital->longitude);
        $latTo = deg2rad((float) $visit->latitude);
        $lonTo = deg2rad((float) $visit->longitude);
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        $angle = 2 * asin(sqrt(
            sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2,
        ));

        return $earthRadius * $angle;
    }
}
