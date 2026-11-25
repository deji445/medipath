<?php

namespace App\Http\Controllers;

use App\Enums\VisitStatus;
use App\Models\AmbulanceRequest;
use App\Models\ConsultantRequest;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediContextController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $reference = $request->session()->get('active_visit_reference');
        $visit = is_string($reference) ? Visit::with('hospitalSubmission')->find($reference) : null;
        $submission = $visit?->hospitalSubmission;
        $callbackReference = $request->session()->get('consultant_request_reference');
        $callback = is_string($callbackReference) ? ConsultantRequest::find($callbackReference) : null;
        $resume = $visit ? match ($visit->status) {
            VisitStatus::Submitted => '/intake/submission/complete',
            VisitStatus::AssessmentComplete => '/intake/routing',
            VisitStatus::Assessing => '/intake/assessment',
            default => '/intake/visit/current',
        } : ($request->session()->has('intake.pending_phone_verification') ? '/intake/verify-phone' : '/intake');

        return response()->json([
            'resumeUrl' => $resume,
            'hasIntake' => $visit !== null || $request->session()->has('intake'),
            'submission' => $submission ? ['status' => $submission->status->value, 'acknowledgedAt' => $submission->acknowledged_at?->toIso8601String(), 'isDemo' => ! $submission->wasSentToPartner()] : null,
            'transport' => $submission ? AmbulanceRequest::where('submission_id', $submission->id)->first()?->patientData() : null,
            'callbackStatus' => $callback?->status,
            'messages' => $request->session()->get('consultant_chat', []),
        ])->header('Cache-Control', 'private, no-store');
    }
}
