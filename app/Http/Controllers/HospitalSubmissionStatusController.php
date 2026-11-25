<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Http\Requests\UpdateHospitalSubmissionStatusRequest;
use App\Models\HospitalSubmission;
use App\Support\DemoHospital;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class HospitalSubmissionStatusController extends Controller
{
    public function __invoke(
        UpdateHospitalSubmissionStatusRequest $request,
        HospitalSubmission $submission,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $hospitalId = $tenantContext->isSuperAdmin() ? DemoHospital::ID : $tenantContext->hospitalId();
        abort_if($hospitalId === null || ! hash_equals($hospitalId, $submission->hospital_id), 404);

        $nextStatus = SubmissionStatus::from($request->string('status')->toString());
        $allowedTransition = match ($submission->status) {
            SubmissionStatus::Received => SubmissionStatus::Acknowledged,
            SubmissionStatus::Acknowledged => SubmissionStatus::InTriage,
            SubmissionStatus::InTriage => SubmissionStatus::Closed,
            SubmissionStatus::Closed => null,
        };

        if ($allowedTransition !== $nextStatus) {
            throw ValidationException::withMessages([
                'status' => 'This case has changed. Refresh the queue and try again.',
            ]);
        }

        $updates = ['status' => $nextStatus];

        if ($nextStatus === SubmissionStatus::Acknowledged) {
            $updates['acknowledged_at'] = now();
        }

        if ($nextStatus === SubmissionStatus::Closed) {
            $updates['closed_at'] = now();
        }

        $changed = HospitalSubmission::whereKey($submission->getKey())
            ->where('hospital_id', $hospitalId)
            ->where('status', $submission->status->value)
            ->update($updates);

        if (! $changed) {
            throw ValidationException::withMessages(['status' => 'This case has changed. Refresh the queue and try again.']);
        }

        $message = match ($nextStatus) {
            SubmissionStatus::Acknowledged => 'Case acknowledged.',
            SubmissionStatus::InTriage => 'Triage started.',
            SubmissionStatus::Closed => 'Case closed and removed from the active queue.',
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
