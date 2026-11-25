<?php

namespace App\Http\Controllers;

use App\Enums\StaffRole;
use App\Models\AmbulanceRequest;
use App\Models\HospitalSubmission;
use App\Models\StaffUser;
use App\Support\DemoHospital;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DispatchController extends Controller
{
    private function hospitalId(Request $request): string
    {
        $user = $request->user();
        abort_unless($user instanceof StaffUser && $user->is_active, 403);
        abort_unless(in_array($user->role, [StaffRole::SuperAdmin, StaffRole::HospitalAdmin, StaffRole::Triage], true), 403);

        return $user->isSuperAdmin() ? DemoHospital::ID : (string) $user->hospital_id;
    }

    public function index(Request $request): Response
    {
        $hospitalId = $this->hospitalId($request);
        $requests = AmbulanceRequest::where('hospital_id', $hospitalId)->latest()->paginate(20);
        $requests->through(function (AmbulanceRequest $item): array {
            $submission = HospitalSubmission::findOrFail($item->submission_id);

            return [...$item->patientData(),
                'submissionReference' => $submission->id,
                'patientName' => $submission->submission_snapshot['patient']['name'] ?? 'Patient',
                'location' => $submission->submission_snapshot['location']['manual'] ?? 'Location not supplied',
                'severity' => $submission->severity->value,
            ];
        });
        $busy = AmbulanceRequest::where('hospital_id', $hospitalId)
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->whereNotNull('vehicle_id')->pluck('vehicle_id');

        return Inertia::render('hospital/Dispatch', [
            'requests' => $requests,
            'vehicles' => DB::table('ambulance_vehicles')->where('hospital_id', $hospitalId)
                ->where('is_active', true)->get(['id', 'name'])->map(fn ($vehicle) => [
                    'id' => $vehicle->id, 'name' => $vehicle->name, 'available' => ! $busy->contains($vehicle->id),
                ]),
            'hospitalName' => DB::table('hospitals')->where('id', $hospitalId)->value('name'),
        ]);
    }

    public function update(Request $request, string $ambulanceRequest): RedirectResponse
    {
        $hospitalId = $this->hospitalId($request);
        $data = $request->validate([
            'status' => 'required|in:ASSIGNED,EN_ROUTE,AT_PATIENT,RETURNING,COMPLETED,CANCELLED',
            'vehicle_id' => 'required_if:status,ASSIGNED|nullable|uuid',
            'crew' => 'required_if:status,ASSIGNED|nullable|string|max:100',
            'eta_minutes' => 'nullable|integer|min:1|max:240',
        ]);
        DB::transaction(function () use ($ambulanceRequest, $hospitalId, $data, $request): void {
            $item = AmbulanceRequest::where('hospital_id', $hospitalId)->lockForUpdate()->findOrFail($ambulanceRequest);
            $next = ['REQUESTED' => 'ASSIGNED', 'ASSIGNED' => 'EN_ROUTE', 'EN_ROUTE' => 'AT_PATIENT', 'AT_PATIENT' => 'RETURNING', 'RETURNING' => 'COMPLETED'];
            if (($next[$item->status] ?? null) !== $data['status'] && ! ($data['status'] === 'CANCELLED' && isset($next[$item->status]))) {
                throw ValidationException::withMessages(['status' => 'This request has changed. Refresh and try again.']);
            }
            if ($data['status'] === 'ASSIGNED') {
                $vehicle = DB::table('ambulance_vehicles')->where('hospital_id', $hospitalId)->where('is_active', true)->where('id', $data['vehicle_id'])->lockForUpdate()->first();
                $busy = AmbulanceRequest::where('vehicle_id', $data['vehicle_id'])->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->exists();
                if (! $vehicle || $busy) {
                    throw ValidationException::withMessages(['vehicle_id' => 'Choose an available ambulance from this hospital.']);
                }
                $item->vehicle_id = $vehicle->id;
                $item->crew = $data['crew'];
                $item->assigned_by = $request->user()->getKey();
            }
            $item->status = $data['status'];
            $item->eta_minutes = in_array($data['status'], ['ASSIGNED', 'EN_ROUTE', 'RETURNING']) ? ($data['eta_minutes'] ?? null) : null;
            $item->events = [...$item->events, ['status' => $item->status, 'at' => now()->toIso8601String(), 'actor' => $request->user()->getKey()]];
            $item->save();
        });

        return back()->with('status', 'Dispatch updated.');
    }
}
