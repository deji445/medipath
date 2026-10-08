<?php

namespace App\Services\Assessment;

use App\Models\Visit;

final class DeterministicAssessmentProvider implements AssessmentProvider
{
    public function __construct(private readonly DeterministicAssessmentEngine $engine) {}

    public function assess(Visit $visit, array $answers, string $idempotencyKey): AssessmentResult
    {
        $result = $this->engine->assess($visit, $answers);

        return new AssessmentResult(
            severity: $result['severity'],
            recommendedAction: $result['recommended_action'],
            symptomSummary: $result['symptom_summary'],
            redFlags: [],
            reasonCodes: $result['reason_codes'],
            schemaVersion: '1.0',
            providerModel: $this->model(),
        );
    }

    public function model(): string
    {
        return 'deterministic-prototype-v1';
    }
}
