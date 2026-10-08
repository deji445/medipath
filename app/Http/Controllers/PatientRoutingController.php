<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentStatus;
use App\Http\Requests\Routing\StoreHospitalSelectionRequest;
use App\Models\Visit;
use App\Services\Routing\HospitalRoutingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PatientRoutingController extends Controller
{
    public function index(Request $request, HospitalRoutingService $routing): Response|RedirectResponse
    {
        $visit = $this->activeVisit($request);

        if (! $visit) {
            return to_route('intake.start');
        }

        if ($visit->assessment?->status !== AssessmentStatus::Complete) {
            return to_route('assessment.ready');
        }

        $selected = $visit->preselectedHospital;

        $recommendations = $routing->recommendations($visit);

        if (($visit->intake_answers['arrival_type'] ?? null) === 'WALK_IN_QR'
            && $selected
            && $routing->eligibleHospital($visit, $selected->getKey())) {
            return to_route('submission.review');
        }
        $suitabilityRanks = array_column($recommendations, 'suitabilityRank');
        $bestSuitabilityRank = $suitabilityRanks === [] ? 0 : min($suitabilityRanks);

        return Inertia::render('routing/Index', [
            'severity' => $visit->assessment->severity?->value,
            'guidance' => [
                'summary' => $visit->assessment->symptom_summary,
                'recommendedAction' => $visit->assessment->recommended_action?->value,
                'reasonCodes' => $visit->assessment->reason_codes,
            ],
            'location' => $visit->manual_location,
            'hospitals' => array_map(
                fn (array $hospital): array => [
                    ...$hospital,
                    'requiresOverrideConfirmation' => $hospital['suitabilityRank'] > $bestSuitabilityRank,
                ],
                $recommendations,
            ),
            'selectedHospital' => $selected ? [
                'id' => $selected->getKey(),
                'name' => $selected->brand_name ?: $selected->name,
            ] : null,
        ]);
    }

    public function store(
        StoreHospitalSelectionRequest $request,
        HospitalRoutingService $routing,
    ): RedirectResponse {
        $visit = $this->activeVisit($request);

        if (! $visit || $visit->assessment?->status !== AssessmentStatus::Complete) {
            return to_route('assessment.ready');
        }

        $hospitalId = (string) $request->validated('hospital_id');
        $recommendations = $routing->recommendations($visit);
        $hospital = $routing->eligibleHospital($visit, $hospitalId);

        if (! $hospital) {
            return back()->withErrors([
                'hospital_id' => 'That hospital cannot receive this assessment. Choose an eligible facility.',
            ]);
        }

        $suitabilityRanks = array_column($recommendations, 'suitabilityRank');
        $bestSuitabilityRank = $suitabilityRanks === [] ? 0 : min($suitabilityRanks);
        $selectedRecommendation = collect($recommendations)->firstWhere('id', $hospitalId);

        if (($selectedRecommendation['suitabilityRank'] ?? $bestSuitabilityRank) > $bestSuitabilityRank
            && ! $request->boolean('override_confirmation')) {
            return back()->withErrors([
                'override_confirmation' => 'Confirm that you understand this hospital is a less suitable option for this assessment.',
            ]);
        }

        DB::transaction(function () use ($visit, $hospital, $selectedRecommendation, $bestSuitabilityRank): void {
            $intakeAnswers = $visit->intake_answers ?? [];
            $intakeAnswers['routing'] = [
                'selected_hospital_id' => $hospital->getKey(),
                'selected_at' => now()->toIso8601String(),
                'override_confirmed_at' => ($selectedRecommendation['suitabilityRank'] ?? $bestSuitabilityRank) > $bestSuitabilityRank
                    ? now()->toIso8601String()
                    : null,
            ];

            $visit->update([
                'preselected_hospital_id' => $hospital->getKey(),
                'intake_answers' => $intakeAnswers,
            ]);
        });

        return $request->boolean('streamlined')
            ? to_route('submission.review')
            : to_route('routing.selected');
    }

    public function selected(Request $request): Response|RedirectResponse
    {
        $visit = $this->activeVisit($request);
        $hospital = $visit?->preselectedHospital;

        if (! $visit || ! $hospital) {
            return to_route('routing.index');
        }

        return Inertia::render('routing/Selected', [
            'hospital' => [
                'name' => $hospital->brand_name ?: $hospital->name,
                'fullName' => $hospital->name,
                'address' => $hospital->address,
            ],
            'severity' => $visit->assessment?->severity?->value,
        ]);
    }

    private function activeVisit(Request $request): ?Visit
    {
        $reference = $request->session()->get('active_visit_reference');

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        return Visit::query()
            ->with(['assessment', 'preselectedHospital'])
            ->find($reference);
    }
}
