<?php

namespace App\Http\Controllers;

use App\Models\ConsultantRequest;
use App\Models\StaffUser;
use App\Services\Audit\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ConsultantRequestQueueController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);
        $status = strtoupper((string) $request->query('status', ''));

        $requests = ConsultantRequest::query()
            ->when(in_array($status, ['NEW', 'CONTACTED', 'CLOSED'], true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ConsultantRequest $consultantRequest): array => [
                'id' => $consultantRequest->getKey(),
                'name' => $consultantRequest->name,
                'phone' => $consultantRequest->normalized_phone,
                'concern' => $consultantRequest->concern,
                'status' => $consultantRequest->status,
                'phoneVerifiedAt' => $consultantRequest->phone_verified_at?->toIso8601String(),
                'createdAt' => $consultantRequest->created_at?->toIso8601String(),
                'updatedAt' => $consultantRequest->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('platform/ConsultantRequests', [
            'requests' => $requests,
            'filter' => in_array($status, ['NEW', 'CONTACTED', 'CLOSED'], true) ? $status : null,
            'counts' => [
                'new' => ConsultantRequest::query()->where('status', 'NEW')->count(),
                'contacted' => ConsultantRequest::query()->where('status', 'CONTACTED')->count(),
                'closed' => ConsultantRequest::query()->where('status', 'CLOSED')->count(),
            ],
        ]);
    }

    public function update(Request $request, ConsultantRequest $consultantRequest, AuditRecorder $audit): RedirectResponse
    {
        $actor = $this->authorizeSuperAdmin($request);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['NEW', 'CONTACTED', 'CLOSED'])],
        ]);
        $previousStatus = $consultantRequest->status;
        $consultantRequest->update(['status' => $validated['status']]);

        $audit->record($actor, 'consultant.request.status.updated', $consultantRequest, [
            'from' => $previousStatus,
            'to' => $validated['status'],
        ]);

        return back()->with('status', 'consultant-request-updated');
    }

    private function authorizeSuperAdmin(Request $request): StaffUser
    {
        $actor = $request->user();
        abort_unless($actor instanceof StaffUser && $actor->is_active && $actor->isSuperAdmin(), 403);

        return $actor;
    }
}
