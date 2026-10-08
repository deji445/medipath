<?php

namespace Database\Factories;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Assessment> */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'status' => AssessmentStatus::Pending,
            'red_flags' => [],
            'reason_codes' => [],
            'deterministic_red_flag_match' => false,
            'ai_schema_version' => '1.0',
            'question_count' => 0,
        ];
    }
}
