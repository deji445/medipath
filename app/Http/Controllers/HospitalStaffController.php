<?php

namespace App\Http\Controllers;

use App\Enums\StaffRole;
use App\Http\Requests\Admin\StoreHospitalStaffRequest;
use App\Http\Requests\Admin\UpdateHospitalStaffRequest;
use App\Models\Hospital;
use App\Models\StaffUser;
use App\Services\Audit\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HospitalStaffController extends Controller
{
    public function index(Request $request): Response
    {
        $hospital = $this->hospital($request->user());
        Gate::forUser($request->user())->authorize('manageStaff', $hospital);

        $staff = $hospital->staffUsers()
            ->orderByDesc('is_active')
            ->orderBy('first_name')
            ->get()
            ->map(fn (StaffUser $member): array => [
                'id' => $member->getKey(),
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role->value,
                'isActive' => $member->is_active,
                'isCurrentUser' => $member->is($request->user()),
            ]);

        return Inertia::render('hospital/Staff', [
            'hospital' => ['name' => $hospital->brand_name ?: $hospital->name],
            'staff' => $staff,
        ]);
    }

    public function store(StoreHospitalStaffRequest $request, AuditRecorder $audit): RedirectResponse
    {
        /** @var StaffUser $actor */
        $actor = $request->user();
        $hospital = $this->hospital($actor);
        Gate::forUser($actor)->authorize('manageStaff', $hospital);
        $data = $request->validated();

        $member = StaffUser::query()->create([
            'id' => (string) Str::uuid(),
            'hospital_id' => $hospital->id,
            'email' => mb_strtolower($data['email']),
            'password_hash' => $data['password'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $data['role'],
            'is_active' => true,
        ]);

        $audit->record($actor, 'hospital.staff.created', $member, ['role' => $member->role->value]);

        return back()->with('status', 'hospital-staff-created');
    }

    public function update(UpdateHospitalStaffRequest $request, StaffUser $staffUser, AuditRecorder $audit): RedirectResponse
    {
        /** @var StaffUser $actor */
        $actor = $request->user();
        $hospital = $this->hospital($actor);
        Gate::forUser($actor)->authorize('manageStaff', $hospital);
        abort_unless($staffUser->hospital_id === $hospital->id && ! $staffUser->isSuperAdmin(), 404);

        $data = $request->validated();
        if ($staffUser->is($actor) && (! $data['is_active'] || $data['role'] !== StaffRole::HospitalAdmin->value)) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate or demote your own administrator account.',
            ]);
        }

        $staffUser->fill(['role' => $data['role'], 'is_active' => $data['is_active']]);
        $changed = array_keys($staffUser->getDirty());
        $staffUser->save();

        $audit->record($actor, 'hospital.staff.updated', $staffUser, ['changed_fields' => $changed]);

        return back()->with('status', 'hospital-staff-updated');
    }

    private function hospital(?StaffUser $user): Hospital
    {
        abort_unless($user instanceof StaffUser && $user->hospital_id !== null, 403);

        return Hospital::query()->findOrFail($user->hospital_id);
    }
}
