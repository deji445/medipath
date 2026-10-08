<?php

namespace Database\Seeders;

use App\Enums\ArrivalType;
use App\Enums\AssessmentStatus;
use App\Enums\ConsentType;
use App\Enums\FillingAs;
use App\Enums\PregnancyStatus;
use App\Enums\RecommendedAction;
use App\Enums\SeverityLevel;
use App\Enums\SexAtBirth;
use App\Enums\StaffRole;
use App\Enums\SubmissionStatus;
use App\Enums\VisitStatus;
use App\Models\Assessment;
use App\Models\ConsentEvent;
use App\Models\Hospital;
use App\Models\HospitalSubmission;
use App\Models\Patient;
use App\Models\StaffUser;
use App\Models\Visit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HospitalAdmissionsDemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'Admissions demo data is local-only.');

        $hospital = Hospital::query()->where('slug', 'hospital-a')->firstOrFail();
        $demoPassword = config('medipath.demo_staff_password');

        if (is_string($demoPassword) && $demoPassword !== '') {
            StaffUser::query()->updateOrCreate(
                ['email' => 'triage.demo@medipath.local'],
                [
                    'id' => '50000000-0000-4000-8000-000000000001',
                    'hospital_id' => $hospital->id,
                    'password_hash' => $demoPassword,
                    'first_name' => 'Adwoa',
                    'last_name' => 'Owusu',
                    'role' => StaffRole::Triage,
                    'is_active' => true,
                ],
            );
        }

        $cases = [
            ['Ama', 'Mensah', 'FEMALE', 42, 'HIGH', 'RECEIVED', 38, 'Crushing chest pressure and difficulty breathing', ['CHEST_PAIN', 'DIFFICULTY_BREATHING'], ['Asthma'], ['Penicillin'], [['name' => 'Salbutamol inhaler', 'dose' => '2 puffs', 'frequency' => 'As needed']], ['heart_rate' => '118 bpm', 'oxygen_saturation' => '91%', 'blood_pressure' => '158/96 mmHg']],
            ['Kwame', 'Boateng', 'MALE', 67, 'HIGH', 'ACKNOWLEDGED', 24, 'Sudden facial weakness and slurred speech', ['STROKE_SIGNS'], ['Hypertension'], [], [['name' => 'Amlodipine', 'dose' => '5 mg', 'frequency' => 'Daily']], ['heart_rate' => '92 bpm', 'blood_pressure' => '181/104 mmHg']],
            ['Efua', 'Darko', 'FEMALE', 29, 'MEDIUM', 'RECEIVED', 31, 'Persistent lower abdominal pain with nausea', [], [], [], [], ['heart_rate' => '96 bpm', 'temperature' => '37.8 °C']],
            ['Kojo', 'Asare', 'MALE', 51, 'MEDIUM', 'IN_TRIAGE', 17, 'Worsening wheeze since this morning', [], ['Asthma'], [], [['name' => 'Budesonide inhaler', 'frequency' => 'Twice daily']], ['heart_rate' => '101 bpm', 'oxygen_saturation' => '95%']],
            ['Abena', 'Ofori', 'FEMALE', 34, 'LOW', 'RECEIVED', 12, 'Mild ankle swelling after a fall', [], [], [], [], ['heart_rate' => '76 bpm']],
            ['Yaw', 'Addo', 'MALE', 46, 'LOW', 'CLOSED', 90, 'Minor cut requiring dressing review', [], [], [], [], ['heart_rate' => '72 bpm']],
        ];

        foreach ($cases as $index => $case) {
            $this->createCase($hospital, $index + 1, $case);
        }
    }

    /** @param array<int, mixed> $case */
    private function createCase(Hospital $hospital, int $number, array $case): void
    {
        $visitId = sprintf('60000000-0000-4000-8000-%012d', $number);
        if (HospitalSubmission::query()->where('visit_id', $visitId)->exists()) {
            return;
        }

        [$firstName, $lastName, $sex, $age, $severity, $status, $minutesAgo, $concern, $redFlags, $conditions, $allergies, $medications, $manualVitals] = $case;
        $severityEnum = SeverityLevel::from($severity);
        $action = match ($severityEnum) {
            SeverityLevel::High => RecommendedAction::SeekEmergencyCareNow,
            SeverityLevel::Medium => RecommendedAction::SeekCareSoon,
            SeverityLevel::Low => RecommendedAction::RoutineCareOrSelfCare,
        };

        DB::transaction(function () use ($hospital, $number, $visitId, $firstName, $lastName, $sex, $age, $severityEnum, $status, $minutesAgo, $concern, $redFlags, $conditions, $allergies, $medications, $manualVitals, $action): void {
            $patient = Patient::query()->updateOrCreate(
                ['normalized_phone' => sprintf('+233200001%03d', $number)],
                ['first_name' => $firstName, 'last_name' => $lastName, 'date_of_birth' => now()->subYears($age)->toDateString()],
            );

            $visit = new Visit;
            $visit->forceFill([
                'id' => $visitId,
                'patient_id' => $patient->id,
                'preselected_hospital_id' => $hospital->id,
                'status' => VisitStatus::AssessmentComplete,
                'filling_as' => FillingAs::Self,
                'age_years' => $age,
                'sex_at_birth' => SexAtBirth::from($sex),
                'pregnancy_status' => PregnancyStatus::NotApplicable,
                'intake_answers' => ['main_complaint' => $concern],
                'manual_vitals' => $manualVitals,
                'started_at' => now()->subMinutes($minutesAgo + 8),
            ])->save();

            $assessment = new Assessment;
            $assessment->forceFill([
                'id' => sprintf('70000000-0000-4000-8000-%012d', $number),
                'visit_id' => $visit->id,
                'status' => AssessmentStatus::Complete,
                'severity' => $severityEnum,
                'recommended_action' => $action,
                'symptom_summary' => $concern.'. Details were supplied by the patient during digital intake.',
                'red_flags' => $redFlags,
                'reason_codes' => [],
                'deterministic_red_flag_match' => $severityEnum === SeverityLevel::High,
                'ai_schema_version' => '1.0',
                'question_count' => 4,
            ])->save();

            $consent = new ConsentEvent;
            $consent->forceFill([
                'id' => sprintf('80000000-0000-4000-8000-%012d', $number),
                'patient_id' => $patient->id,
                'visit_id' => $visit->id,
                'hospital_id' => $hospital->id,
                'consent_type' => ConsentType::HospitalShare,
                'consent_version' => '1.0',
                'granted' => true,
                'recorded_at' => now()->subMinutes($minutesAgo + 1),
            ])->save();

            $submission = new HospitalSubmission;
            $submission->forceFill([
                'id' => sprintf('90000000-0000-4000-8000-%012d', $number),
                'visit_id' => $visit->id,
                'hospital_id' => $hospital->id,
                'consent_event_id' => $consent->id,
                'status' => SubmissionStatus::from($status),
                'arrival_type' => $number % 2 === 0 ? ArrivalType::WalkInQr : ArrivalType::PreArrival,
                'severity' => $severityEnum,
                'recommended_action' => $action,
                'submission_snapshot' => [
                    'patient' => ['name' => "$firstName $lastName", 'age' => $age, 'sex_at_birth' => $sex, 'date_of_birth' => $patient->date_of_birth?->toDateString()],
                    'main_concern' => $concern,
                    'symptom_summary' => $assessment->symptom_summary,
                    'red_flags' => $redFlags,
                    'conditions' => $conditions,
                    'allergies' => $allergies,
                    'medications' => $medications,
                    'vitals' => ['manual' => $manualVitals, 'wearable' => null],
                    'consent_recorded_at' => $consent->recorded_at?->toIso8601String(),
                ],
                'received_at' => now()->subMinutes($minutesAgo),
                'acknowledged_at' => in_array($status, ['ACKNOWLEDGED', 'IN_TRIAGE', 'CLOSED'], true) ? now()->subMinutes(max(1, $minutesAgo - 4)) : null,
                'closed_at' => $status === 'CLOSED' ? now()->subMinutes(15) : null,
            ])->save();
        });
    }
}
