<?php

namespace App\Http\Controllers;

use App\Enums\SeverityLevel;
use App\Enums\SubmissionStatus;
use App\Models\Hospital;
use App\Models\HospitalSubmission;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HospitalDashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenantContext): Response|RedirectResponse
    {
        if ($tenantContext->isSuperAdmin()) {
            return to_route('platform.index');
        }

        $hospitalId = $tenantContext->hospitalId();
        abort_if($hospitalId === null, 403);

        $hospital = Hospital::query()->findOrFail($hospitalId);
        $search = trim((string) $request->string('search'));
        $severity = strtoupper((string) $request->string('severity', 'ALL'));
        $status = strtoupper((string) $request->string('status', 'ACTIVE'));

        $activeStatuses = [
            SubmissionStatus::Received->value,
            SubmissionStatus::Acknowledged->value,
            SubmissionStatus::InTriage->value,
        ];

        $baseQuery = HospitalSubmission::query()->where('hospital_id', $hospitalId);

        $queueQuery = (clone $baseQuery)
            ->when($status === 'ACTIVE', fn (Builder $query) => $query->whereIn('status', $activeStatuses))
            ->when($status !== 'ACTIVE' && $status !== 'ALL', fn (Builder $query) => $query->where('status', $status))
            ->when($severity !== 'ALL', fn (Builder $query) => $query->where('severity', $severity))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereRaw(
                    'LOWER(CAST(submission_snapshot AS TEXT)) LIKE ?',
                    ['%'.mb_strtolower($search).'%'],
                );
            })
            ->orderByRaw("CASE severity WHEN 'HIGH' THEN 1 WHEN 'MEDIUM' THEN 2 ELSE 3 END")
            ->orderBy('received_at');

        $submissions = $queueQuery->paginate(12)->withQueryString()->through(
            fn (HospitalSubmission $submission): array => $this->submissionData($submission),
        );

        return Inertia::render('Dashboard', [
            'hospital' => [
                'name' => $hospital->brand_name ?: $hospital->name,
                'fullName' => $hospital->name,
                'acceptingSubmissions' => $hospital->accepting_submissions,
            ],
            'counts' => [
                'highPriority' => (clone $baseQuery)->whereIn('status', $activeStatuses)->where('severity', SeverityLevel::High)->count(),
                'waiting' => (clone $baseQuery)->where('status', SubmissionStatus::Received)->count(),
                'inTriage' => (clone $baseQuery)->where('status', SubmissionStatus::InTriage)->count(),
            ],
            'submissions' => $submissions,
            'filters' => compact('search', 'severity', 'status'),
        ]);
    }

    /** @return array<string, mixed> */
    private function submissionData(HospitalSubmission $submission): array
    {
        $snapshot = $submission->submission_snapshot;
        $patient = is_array($snapshot['patient'] ?? null) ? $snapshot['patient'] : [];
        $vitals = is_array($snapshot['vitals'] ?? null) ? $snapshot['vitals'] : [];
        $location = is_array($snapshot['location'] ?? null) ? $snapshot['location'] : [];
        $respondent = is_array($snapshot['respondent'] ?? null) ? $snapshot['respondent'] : [];

        return [
            'id' => $submission->id,
            'patient' => [
                'name' => trim((string) ($patient['name'] ?? (($patient['first_name'] ?? 'Unknown').' '.($patient['last_name'] ?? 'patient')))),
                'age' => $patient['age'] ?? $snapshot['age_years'] ?? null,
                'sexAtBirth' => $patient['sex_at_birth'] ?? $snapshot['sex_at_birth'] ?? null,
                'dateOfBirth' => $patient['date_of_birth'] ?? null,
                'phone' => $patient['phone'] ?? null,
            ],
            'severity' => $submission->severity->value,
            'recommendedAction' => $submission->recommended_action->value,
            'arrivalType' => $submission->arrival_type->value,
            'status' => $submission->status->value,
            'receivedAt' => $submission->received_at?->toIso8601String(),
            'acknowledgedAt' => $submission->acknowledged_at?->toIso8601String(),
            'mainConcern' => $snapshot['main_concern'] ?? $snapshot['main_complaint'] ?? 'No concern recorded',
            'symptomSummary' => $snapshot['symptom_summary'] ?? 'No summary available.',
            'redFlags' => array_values((array) ($snapshot['red_flags'] ?? [])),
            'conditions' => array_values((array) ($snapshot['conditions'] ?? [])),
            'allergies' => array_values((array) ($snapshot['allergies'] ?? [])),
            'medications' => array_values((array) ($snapshot['medications'] ?? [])),
            'vitals' => [
                'manual' => $vitals['manual'] ?? $snapshot['manual_vitals'] ?? null,
                'wearable' => $vitals['wearable'] ?? $snapshot['wearable_vitals'] ?? null,
            ],
            'location' => [
                'manual' => $location['manual'] ?? null,
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
            ],
            'respondent' => [
                'fillingAs' => $respondent['filling_as'] ?? null,
                'name' => $respondent['name'] ?? null,
                'relationship' => $respondent['relationship'] ?? null,
            ],
            'consentRecordedAt' => $snapshot['consent_recorded_at'] ?? null,
        ];
    }
}
