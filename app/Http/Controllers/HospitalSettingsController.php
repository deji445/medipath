<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\UpdateHospitalSettingsRequest;
use App\Models\Hospital;
use App\Models\StaffUser;
use App\Services\Audit\AuditRecorder;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HospitalSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $hospital = $this->hospital($request->user());
        Gate::forUser($request->user())->authorize('update', $hospital);

        return Inertia::render('hospital/Settings', [
            'hospital' => [
                ...$this->hospitalData($hospital),
                'walkInUrl' => route('walk-in.show', $hospital),
                'walkInQr' => $this->walkInQr(route('walk-in.show', $hospital)),
            ],
        ]);
    }

    public function update(UpdateHospitalSettingsRequest $request, AuditRecorder $audit): RedirectResponse
    {
        /** @var StaffUser $actor */
        $actor = $request->user();
        $hospital = $this->hospital($actor);
        Gate::forUser($actor)->authorize('update', $hospital);

        $hospital->fill($request->validated());
        $changed = array_keys($hospital->getDirty());
        $hospital->save();

        $audit->record($actor, 'hospital.settings.updated', $hospital, ['changed_fields' => $changed]);

        return back()->with('status', 'hospital-settings-updated');
    }

    private function hospital(?StaffUser $user): Hospital
    {
        abort_unless($user instanceof StaffUser && $user->hospital_id !== null, 403);

        return Hospital::query()->findOrFail($user->hospital_id);
    }

    /** @return array<string, mixed> */
    private function hospitalData(Hospital $hospital): array
    {
        return [
            'id' => $hospital->id,
            'name' => $hospital->name,
            'brandName' => $hospital->brand_name,
            'address' => $hospital->address,
            'brandColor' => $hospital->brand_color,
            'capability' => $hospital->capability->value,
            'routingMode' => $hospital->routing_mode->value,
            'acceptingSubmissions' => $hospital->accepting_submissions,
            'isActive' => $hospital->is_active,
        ];
    }

    private function walkInQr(string $url): string
    {
        return Builder::create()
            ->writer(new SvgWriter)
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size(260)
            ->margin(12)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build()
            ->getDataUri();
    }
}
