<?php

namespace App\Http\Controllers;

use App\Enums\HospitalCapability;
use App\Models\Hospital;
use Inertia\Inertia;
use Inertia\Response;

final class EmergencyHelpController extends Controller
{
    public function __invoke(): Response
    {
        $hospitals = Hospital::query()
            ->where('is_active', true)
            ->where('accepting_submissions', true)
            ->where('capability', HospitalCapability::EmergencyCapable)
            ->orderBy('name')
            ->get()
            ->map(fn (Hospital $hospital): array => [
                'id' => $hospital->getKey(),
                'name' => $hospital->brand_name ?: $hospital->name,
                'fullName' => $hospital->name,
                'address' => $hospital->address,
                'latitude' => $hospital->latitude !== null ? (float) $hospital->latitude : null,
                'longitude' => $hospital->longitude !== null ? (float) $hospital->longitude : null,
            ])
            ->values();

        return Inertia::render('emergency/Index', [
            'emergencyContact' => [
                'label' => config('medipath.emergency.contact_label'),
                'phone' => config('medipath.emergency.phone'),
            ],
            'hospitals' => $hospitals,
        ]);
    }
}
