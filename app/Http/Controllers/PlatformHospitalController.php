<?php

namespace App\Http\Controllers;

use App\Enums\StaffRole;
use App\Http\Requests\Platform\StoreHospitalRequest;
use App\Http\Requests\Platform\UpdateHospitalRequest;
use App\Models\Hospital;
use App\Models\StaffUser;
use App\Services\Audit\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PlatformHospitalController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $this->superAdmin($request);

        $hospitals = Hospital::query()
            ->withCount(['staffUsers', 'submissions'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Hospital $hospital): array => $this->hospitalData($hospital));

        return Inertia::render('platform/Index', [
            'hospitals' => $hospitals,
            'counts' => [
                'hospitals' => $hospitals->count(),
                'active' => $hospitals->where('isActive', true)->count(),
                'accepting' => $hospitals->where('acceptingSubmissions', true)->where('isActive', true)->count(),
                'staff' => $hospitals->sum('staffCount'),
            ],
            'actor' => ['name' => $actor->name],
        ]);
    }

    public function store(StoreHospitalRequest $request, AuditRecorder $audit): RedirectResponse
    {
        /** @var StaffUser $actor */
        $actor = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($data, $actor, $audit): void {
            $hospital = Hospital::query()->create([
                'id' => (string) Str::uuid(),
                'name' => $data['name'],
                'slug' => $data['slug'],
                'brand_name' => $data['brand_name'],
                'brand_color' => $data['brand_color'],
                'address' => $data['address'],
                'capability' => $data['capability'],
                'routing_mode' => $data['routing_mode'],
                'accepting_submissions' => true,
                'is_active' => true,
            ]);

            $admin = StaffUser::query()->create([
                'id' => (string) Str::uuid(),
                'hospital_id' => $hospital->id,
                'email' => mb_strtolower($data['admin_email']),
                'password_hash' => $data['admin_password'],
                'first_name' => $data['admin_first_name'],
                'last_name' => $data['admin_last_name'],
                'role' => StaffRole::HospitalAdmin,
                'is_active' => true,
            ]);

            $audit->record($actor, 'platform.hospital.created', $hospital, ['initial_admin_id' => $admin->id]);
        });

        return back()->with('status', 'platform-hospital-created');
    }

    public function update(UpdateHospitalRequest $request, Hospital $hospital, AuditRecorder $audit): RedirectResponse
    {
        /** @var StaffUser $actor */
        $actor = $request->user();
        $hospital->fill($request->validated());
        $changed = array_keys($hospital->getDirty());
        $hospital->save();

        $audit->record($actor, 'platform.hospital.updated', $hospital, ['changed_fields' => $changed]);

        return back()->with('status', 'platform-hospital-updated');
    }

    private function superAdmin(Request $request): StaffUser
    {
        $user = $request->user();
        abort_unless($user instanceof StaffUser && $user->is_active && $user->isSuperAdmin(), 403);

        return $user;
    }

    /** @return array<string, mixed> */
    private function hospitalData(Hospital $hospital): array
    {
        return [
            'id' => $hospital->id,
            'name' => $hospital->name,
            'slug' => $hospital->slug,
            'brandName' => $hospital->brand_name,
            'brandColor' => $hospital->brand_color,
            'address' => $hospital->address,
            'capability' => $hospital->capability->value,
            'routingMode' => $hospital->routing_mode->value,
            'acceptingSubmissions' => $hospital->accepting_submissions,
            'isActive' => $hospital->is_active,
            'staffCount' => (int) $hospital->staff_users_count,
            'submissionCount' => (int) $hospital->submissions_count,
        ];
    }
}
