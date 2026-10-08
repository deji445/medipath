<?php

namespace App\Http\Controllers;

use App\Enums\ArrivalType;
use App\Enums\AssessmentStatus;
use App\Enums\ConsentType;
use App\Enums\SubmissionStatus;
use App\Enums\VisitStatus;
use App\Http\Requests\Submission\StoreHospitalSubmissionRequest;
use App\Models\ConsentEvent;
use App\Models\HospitalSubmission;
use App\Models\Visit;
use App\Services\Routing\HospitalRoutingService;
use App\Services\Submission\HospitalSubmissionSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PatientSubmissionController extends Controller
{
    public function review(Request $request, HospitalRoutingService $routing): Response|RedirectResponse
    {
        $visit = $this->activeVisit($request);

        if (! $visit) {
            return to_route('intake.start');
        }

        if ($visit->hospitalSubmission) {
            return to_route('submission.complete');
        }

        if ($visit->assessment?->status !== AssessmentStatus::Complete) {
            return to_route('assessment.ready');
        }

        $hospital = $visit->preselectedHospital;

        if (! $hospital) {
            return to_route('routing.index');
        }

        if (! $routing->eligibleHospital($visit, $hospital->getKey())) {
            return to_route('routing.index')->withErrors([
                'hospital_id' => 'The selected hospital is no longer available. Choose another facility.',
            ]);
        }

        return Inertia::render('submission/Review', [
            'hospital' => [
                'name' => $hospital->brand_name ?: $hospital->name,
                'fullName' => $hospital->name,
                'address' => $hospital->address,
            ],
            'patient' => [
                'name' => trim($visit->patient->first_name.' '.$visit->patient->last_name),
                'age' => $visit->age_years,
            ],
            'assessment' => [
                'severity' => $visit->assessment->severity?->value,
                'summary' => $visit->assessment->symptom_summary,
            ],
            'sharedGroups' => [
                'Patient identity and contact details',
                'Current concern and assessment summary',
                'Medical conditions, allergies, and medicines',
                'Recorded vitals and supplied location',
            ],
        ]);
    }

    public function store(
        StoreHospitalSubmissionRequest $request,
        HospitalRoutingService $routing,
        HospitalSubmissionSnapshot $snapshot,
    ): RedirectResponse {
        $reference = $this->activeVisitReference($request);

        if (! $reference) {
            return to_route('intake.start');
        }

        return DB::transaction(function () use ($reference, $routing, $snapshot): RedirectResponse {
            $visit = Visit::query()->lockForUpdate()->find($reference);

            if (! $visit) {
                return to_route('intake.start');
            }

            $visit->load(['assessment', 'patient', 'preselectedHospital', 'hospitalSubmission']);

            if ($visit->hospitalSubmission) {
                return to_route('submission.complete');
            }

            if ($visit->assessment?->status !== AssessmentStatus::Complete) {
                return to_route('assessment.ready');
            }

            $hospital = $visit->preselectedHospital;

            if (! $hospital || ! $routing->eligibleHospital($visit, $hospital->getKey())) {
                throw ValidationException::withMessages([
                    'share_consent' => 'The selected hospital is no longer available. Choose another facility before sharing.',
                ]);
            }

            $consent = ConsentEvent::query()->create([
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->getKey(),
                'hospital_id' => $hospital->getKey(),
                'consent_type' => ConsentType::HospitalShare,
                'consent_version' => 'hospital-share-v1',
                'granted' => true,
                'recorded_at' => now(),
            ]);

            HospitalSubmission::query()->create([
                'visit_id' => $visit->getKey(),
                'hospital_id' => $hospital->getKey(),
                'consent_event_id' => $consent->getKey(),
                'status' => SubmissionStatus::Received,
                'arrival_type' => ArrivalType::tryFrom(
                    (string) ($visit->intake_answers['arrival_type'] ?? ''),
                ) ?? ArrivalType::PreArrival,
                'severity' => $visit->assessment->severity,
                'recommended_action' => $visit->assessment->recommended_action,
                'submission_snapshot' => $snapshot->build($visit, $consent),
            ]);

            $visit->update([
                'status' => VisitStatus::Submitted,
                'completed_at' => $visit->completed_at ?? now(),
            ]);

            return to_route('submission.complete');
        });
    }

    public function complete(Request $request): Response|RedirectResponse
    {
        $visit = $this->activeVisit($request);
        $submission = $visit?->hospitalSubmission;

        if (! $visit || ! $submission) {
            return to_route('submission.review');
        }

        $hospital = $submission->hospital;

        return Inertia::render('submission/Complete', [
            'submission' => [
                'reference' => $submission->getKey(),
                'receivedAt' => $submission->received_at?->toIso8601String(),
                'status' => $submission->status->value,
            ],
            'hospital' => [
                'name' => $hospital->brand_name ?: $hospital->name,
                'fullName' => $hospital->name,
                'address' => $hospital->address,
            ],
        ]);
    }

    private function activeVisit(Request $request): ?Visit
    {
        $reference = $this->activeVisitReference($request);

        if (! $reference) {
            return null;
        }

        return Visit::query()
            ->with(['assessment', 'patient', 'preselectedHospital', 'hospitalSubmission.hospital'])
            ->find($reference);
    }

    private function activeVisitReference(Request $request): ?string
    {
        $reference = $request->session()->get('active_visit_reference');

        return is_string($reference) && $reference !== '' ? $reference : null;
    }
}
