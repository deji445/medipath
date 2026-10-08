<?php

namespace App\Services\Assessment;

use App\Models\PatientMedication;
use App\Models\Visit;

final class AssessmentInputBuilder
{
    /**
     * @param  array<string, string>  $answers
     * @return array<string, mixed>
     */
    public function build(Visit $visit, array $answers): array
    {
        $visit->loadMissing([
            'patient.conditions',
            'patient.allergies',
            'patient.medications',
        ]);

        $conversation = [[
            'question' => 'What is the main health concern?',
            'answer' => trim((string) ($visit->intake_answers['main_complaint'] ?? 'Not supplied.')),
        ]];

        foreach ($answers as $key => $answer) {
            $conversation[] = [
                'question_key' => $key,
                'answer_code' => $answer,
            ];
        }

        return [
            'schema_version' => '1.0',
            'visit_id' => (string) $visit->getKey(),
            'age_years' => $visit->age_years,
            'sex_at_birth' => $visit->sex_at_birth->value,
            'pregnancy_status' => $visit->pregnancy_status->value,
            'conditions' => $visit->patient->conditions->where('is_active', true)->pluck('name')->values()->all(),
            'allergies' => $visit->patient->allergies->where('is_active', true)->pluck('name')->values()->all(),
            'medications' => $visit->patient->medications->where('is_active', true)
                ->map(fn (PatientMedication $medication): array => [
                    'name' => $medication->name,
                    'dose' => $medication->dose,
                    'frequency' => $medication->frequency,
                ])->values()->all(),
            'manual_vitals' => $visit->manual_vitals,
            'wearable_vitals' => $visit->wearable_vitals,
            'conversation' => $conversation,
        ];
    }

    /**
     * @param  array<string, string>  $answers
     * @param  array<string, string>  $questions
     * @return array<string, mixed>
     */
    public function buildConversation(Visit $visit, array $answers, array $questions): array
    {
        $input = $this->build($visit, []);

        foreach ($answers as $key => $answer) {
            $input['conversation'][] = [
                'question' => $questions[$key] ?? 'Follow-up question',
                'answer' => $answer,
            ];
        }

        return $input;
    }
}
