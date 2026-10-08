<?php

namespace Database\Factories;

use App\Enums\FillingAs;
use App\Enums\PregnancyStatus;
use App\Enums\SexAtBirth;
use App\Enums\VisitStatus;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Visit> */
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'status' => VisitStatus::Draft,
            'filling_as' => FillingAs::Self,
            'age_years' => fake()->numberBetween(1, 100),
            'sex_at_birth' => SexAtBirth::Unknown,
            'pregnancy_status' => PregnancyStatus::NotApplicable,
            'intake_answers' => [
                'main_complaint' => fake()->sentence(6),
            ],
        ];
    }
}
