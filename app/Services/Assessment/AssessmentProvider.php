<?php

namespace App\Services\Assessment;

use App\Models\Visit;

interface AssessmentProvider
{
    /** @param array<string, string> $answers */
    public function assess(Visit $visit, array $answers, string $idempotencyKey): AssessmentResult;

    public function model(): string;
}
