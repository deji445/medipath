<?php

namespace App\Http\Controllers;

use App\Enums\ArrivalType;
use App\Models\Hospital;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalkInIntakeController extends Controller
{
    public function show(Hospital $hospital): Response
    {
        abort_unless($hospital->is_active && $hospital->accepting_submissions, 404);

        return Inertia::render('intake/WalkIn', [
            'hospital' => [
                'name' => $hospital->brand_name ?: $hospital->name,
                'fullName' => $hospital->name,
                'address' => $hospital->address,
                'capability' => $hospital->capability->value,
            ],
            'startUrl' => route('walk-in.start', $hospital),
        ]);
    }

    public function start(Request $request, Hospital $hospital): RedirectResponse
    {
        abort_unless($hospital->is_active && $hospital->accepting_submissions, 404);

        $request->session()->forget(['intake', 'active_visit_reference', 'assessment_red_flags']);
        $request->session()->put([
            'intake.preselected_hospital_id' => $hospital->getKey(),
            'intake.arrival_type' => ArrivalType::WalkInQr->value,
        ]);

        return to_route('intake.start');
    }
}
