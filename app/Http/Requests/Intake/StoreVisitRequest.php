<?php

namespace App\Http\Requests\Intake;

use App\Enums\FillingAs;
use App\Enums\PregnancyStatus;
use App\Enums\SexAtBirth;
use App\Models\Visit;
use App\Support\PatientAge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $identity = $this->session()->get('intake.identity');
        $sexAtBirth = is_array($identity) ? ($identity['sex_at_birth'] ?? null) : null;
        $age = PatientAge::fromDateOfBirth(
            is_array($identity) ? ($identity['date_of_birth'] ?? null) : null,
        );

        if (! is_array($identity)) {
            $reference = $this->session()->get('active_visit_reference');
            $activeVisit = is_string($reference)
                ? Visit::query()->with('patient')->find($reference)
                : null;
            $sexAtBirth = $activeVisit?->sex_at_birth->value;
            $age = $activeVisit
                ? PatientAge::fromDateOfBirth($activeVisit->patient->date_of_birth?->format('Y-m-d'))
                    ?? $activeVisit->age_years
                : null;
        }

        $derived = [];

        if ($age !== null) {
            $derived['age_years'] = $age;
        }

        if (is_string($sexAtBirth) && $sexAtBirth !== '') {
            $derived['sex_at_birth'] = $sexAtBirth;

            if ($sexAtBirth !== SexAtBirth::Female->value) {
                $derived['pregnancy_status'] = PregnancyStatus::NotApplicable->value;
            }
        }

        if ($this->boolean('ai_disclaimer_acknowledged') && $this->boolean('privacy_notice_acknowledged')) {
            $derived['intake_consent_acknowledged'] = true;
        }

        $this->merge($derived);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'age_years' => ['required', 'integer', 'between:0,120'],
            'filling_as' => ['required', Rule::enum(FillingAs::class)],
            'respondent_name' => ['nullable', 'required_unless:filling_as,SELF', 'string', 'max:160'],
            'relationship_to_patient' => ['nullable', 'required_unless:filling_as,SELF', 'string', 'max:100'],
            'sex_at_birth' => ['required', Rule::enum(SexAtBirth::class)],
            'pregnancy_status' => [
                Rule::requiredIf($this->input('sex_at_birth') === SexAtBirth::Female->value),
                Rule::enum(PregnancyStatus::class),
            ],
            'main_complaint' => ['required', 'string', 'max:500'],
            'symptom_details' => ['nullable', 'string', 'max:1500'],
            'symptom_onset' => ['nullable', 'string', 'max:200'],
            'manual_location' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'location_permission_granted' => ['nullable', 'boolean'],
            'temperature_c' => ['nullable', 'numeric', 'between:30,45'],
            'heart_rate_bpm' => ['nullable', 'integer', 'between:20,250'],
            'oxygen_saturation_percent' => ['nullable', 'integer', 'between:50,100'],
            'intake_consent_acknowledged' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'intake_consent_acknowledged.accepted' => 'Agree to the intake notice before continuing.',
        ];
    }
}
