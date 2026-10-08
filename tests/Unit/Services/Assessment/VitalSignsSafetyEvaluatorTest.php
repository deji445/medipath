<?php

namespace Tests\Unit\Services\Assessment;

use App\Models\Visit;
use App\Services\Assessment\VitalSignsSafetyEvaluator;
use PHPUnit\Framework\TestCase;

class VitalSignsSafetyEvaluatorTest extends TestCase
{
    public function test_a_critical_adult_oxygen_reading_interrupts_the_flow(): void
    {
        $result = (new VitalSignsSafetyEvaluator)->evaluate(new Visit([
            'age_years' => 35,
            'manual_vitals' => ['oxygen_saturation_percent' => 89],
        ]));

        $this->assertTrue($result['critical']);
        $this->assertSame(3, $result['score']);
        $this->assertNotEmpty($result['findings']);
    }

    public function test_normal_adult_vitals_do_not_raise_the_score(): void
    {
        $result = (new VitalSignsSafetyEvaluator)->evaluate(new Visit([
            'age_years' => 35,
            'manual_vitals' => [
                'oxygen_saturation_percent' => 98,
                'heart_rate_bpm' => 72,
                'temperature_c' => 37,
            ],
        ]));

        $this->assertFalse($result['critical']);
        $this->assertSame(0, $result['score']);
    }

    public function test_adult_thresholds_are_not_applied_to_children(): void
    {
        $result = (new VitalSignsSafetyEvaluator)->evaluate(new Visit([
            'age_years' => 10,
            'manual_vitals' => ['heart_rate_bpm' => 140],
        ]));

        $this->assertFalse($result['critical']);
        $this->assertSame(0, $result['score']);
    }
}
