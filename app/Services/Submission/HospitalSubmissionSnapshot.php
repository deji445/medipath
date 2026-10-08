<?php

namespace App\Services\Submission;

use App\Models\ConsentEvent;
use App\Models\PatientMedication;
use App\Models\Visit;

final class HospitalSubmissionSnapshot
{
    /** @return array<string, mixed> */
    public function build(Visit $visit, ConsentEvent $consent): array
    {
        $visit->loadMissing([
            'patient.conditions',
            'patient.allergies',
            'patient.medications',
            'assessment',
        ]);

        $patient = $visit->patient;
        $assessment = $visit->assessment;
        $answers = $visit->intake_answers ?? [];

        return [
            'schema_version' => '1.0',
            'patient' => [
                'name' => trim($patient->first_name.' '.$patient->last_name),
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'phone' => $patient->normalized_phone,
                'age' => $visit->age_years,
                'sex_at_birth' => $visit->sex_at_birth->value,
                'date_of_birth' => $patient->date_of_birth?->toDateString(),
            ],
            'respondent' => [
                'filling_as' => $visit->filling_as->value,
                'name' => $visit->respondent_name,
                'relationship' => $visit->relationship_to_patient,
            ],
            'main_concern' => $answers['main_complaint'] ?? 'No concern recorded',
            'symptom_details' => $answers['symptom_details'] ?? null,
            'symptom_onset' => $answers['symptom_onset'] ?? null,
            'symptom_summary' => $assessment?->symptom_summary,
            'red_flags' => $assessment->red_flags,
            'reason_codes' => $assessment->reason_codes,
            'conditions' => $patient->conditions->where('is_active', true)->pluck('name')->values()->all(),
            'allergies' => $patient->allergies->where('is_active', true)->pluck('name')->values()->all(),
            'medications' => $patient->medications->where('is_active', true)->map(fn (PatientMedication $medication): array => [
                'name' => $medication->name,
                'dose' => $medication->dose,
                'frequency' => $medication->frequency,
            ])->values()->all(),
            'history_status' => $answers['history_status'] ?? null,
            'vitals' => [
                'manual' => $visit->manual_vitals,
                'wearable' => $visit->wearable_vitals,
            ],
            'location' => [
                'manual' => $visit->manual_location,
                'latitude' => $visit->latitude,
                'longitude' => $visit->longitude,
            ],
            'consent_recorded_at' => $consent->recorded_at?->toIso8601String(),
        ];
    }
}
