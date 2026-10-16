<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->session()->forget('consultant_chat');
        $challenge = $request->session()->get('consultant_callback.pending_phone_verification');
        $pendingRequest = $request->session()->get('consultant_callback.pending_request');
        $callbackVerification = is_array($challenge) && is_array($pendingRequest)
            ? [
                'phoneNumber' => $this->maskPhone((string) ($challenge['phone'] ?? '')),
                'expiresAt' => $challenge['expires_at'] ?? null,
                'demoCode' => $request->session()->get('consultant_callback.demo_verification_code'),
                'status' => $request->session()->get('consultant_callback_status'),
            ]
            : null;

        return Inertia::render('Welcome', [
            'chatMessages' => [],
            'emergencyContact' => [
                'label' => config('medipath.emergency.contact_label'),
                'phone' => config('medipath.emergency.phone'),
            ],
            'consultantRequestReference' => $request->session()->get('consultant_request_reference'),
            'callbackVerification' => $callbackVerification,
        ]);
    }

    private function maskPhone(string $phone): string
    {
        return str_repeat('•', max(0, mb_strlen($phone) - 4)).mb_substr($phone, -4);
    }
}
