<?php

namespace Tests\Unit\Services\Assessment;

use App\Enums\AssessmentReasonCode;
use App\Enums\SeverityLevel;
use App\Models\Visit;
use App\Services\Assessment\AssessmentQuestionnaire;
use App\Services\Assessment\DeterministicAssessmentEngine;
use App\Services\Assessment\VitalSignsSafetyEvaluator;
use PHPUnit\Framework\TestCase;

class DeterministicAssessmentEngineTest extends TestCase
{
    public function test_noncritical_abnormal_adult_vitals_raise_the_assessment_severity(): void
    {
        $engine = new DeterministicAssessmentEngine(
            new AssessmentQuestionnaire,
            new VitalSignsSafetyEvaluator,
        );
        $visit = new Visit([
            'age_years' => 35,
            'manual_vitals' => ['oxygen_saturation_percent' => 95],
            'intake_answers' => ['main_complaint' => 'Feeling unwell'],
        ]);

        $result = $engine->assess($visit, [
            'duration' => 'LESS_THAN_DAY',
            'trend' => 'IMPROVING',
            'intensity' => 'MILD',
            'daily_impact' => 'LITTLE',
            'new_warning_sign' => 'NONE',
            'associated_symptoms' => 'NONE',
        ]);

        $this->assertSame(SeverityLevel::Medium, $result['severity']);
        $this->assertContains(
            AssessmentReasonCode::AbnormalVitalSignsReported->value,
            $result['reason_codes'],
        );
    }
}
