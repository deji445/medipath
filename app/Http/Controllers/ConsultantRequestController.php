<?php

namespace App\Http\Controllers;

use App\Http\Requests\Intake\VerifyPhoneRequest;
use App\Models\ConsultantRequest;
use App\Services\Assessment\DeterministicEmergencyScreener;
use App\Services\Verification\PhoneVerificationService;
use App\Support\PhoneNumberNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConsultantRequestController extends Controller
{
    private const PENDING_REQUEST = 'consultant_callback.pending_request';

    private const PENDING_VERIFICATION = 'consultant_callback.pending_phone_verification';

    private const DEMO_CODE = 'consultant_callback.demo_verification_code';

    public function store(
        Request $request,
        PhoneNumberNormalizer $normalizer,
        PhoneVerificationService $verification,
        DeterministicEmergencyScreener $emergencyScreener,
    ): RedirectResponse {
        $request->merge([
            'phone_number' => $normalizer->normalize((string) $request->input('phone_number')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone_number' => ['required', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'concern' => ['nullable', 'string', 'max:1000'],
        ], [
            'phone_number.regex' => 'Enter a valid phone number, for example 024 123 4567.',
        ]);
        $concern = trim((string) ($validated['concern'] ?? ''));

        if ($concern !== '' && $emergencyScreener->screenText($concern) !== []) {
            $this->clearPendingRequest($request);

            return to_route('emergency');
        }

        $issued = $verification->issue($validated['phone_number']);
        $request->session()->put(self::PENDING_REQUEST, [
            'name' => trim($validated['name']),
            'normalized_phone' => $validated['phone_number'],
            'concern' => $concern !== '' ? $concern : null,
        ]);
        $request->session()->put(self::PENDING_VERIFICATION, $issued['challenge']);

        if (is_string($issued['demo_code'])) {
            $request->session()->put(self::DEMO_CODE, $issued['demo_code']);
        }

        return to_route('home', ['consult' => 'human']);
    }

    public function confirm(
        VerifyPhoneRequest $request,
        PhoneVerificationService $verification,
    ): RedirectResponse {
        $challenge = $request->session()->get(self::PENDING_VERIFICATION);
        $pendingRequest = $request->session()->get(self::PENDING_REQUEST);

        if (! is_array($challenge)
            || ! is_array($pendingRequest)
            || ! is_string($challenge['phone'] ?? null)
            || ! is_string($pendingRequest['name'] ?? null)
            || ! is_string($pendingRequest['normalized_phone'] ?? null)
            || (! is_null($pendingRequest['concern'] ?? null)
                && ! is_string($pendingRequest['concern']))
            || $pendingRequest['normalized_phone'] !== $challenge['phone']) {
            $this->clearPendingRequest($request);

            return to_route('home', ['consult' => 'human']);
        }

        if ($verification->isExpired($challenge)) {
            throw ValidationException::withMessages([
                'code' => 'This code has expired. Request a new code.',
            ]);
        }

        $attempts = (int) ($challenge['attempts'] ?? 0);
        $maxAttempts = (int) config('medipath.phone_verification.max_attempts', 5);

        if ($attempts >= $maxAttempts) {
            throw ValidationException::withMessages([
                'code' => 'Too many incorrect attempts. Request a new code.',
            ]);
        }

        if (! $verification->matches($challenge, (string) $request->validated('code'))) {
            $challenge['attempts'] = $attempts + 1;
            $request->session()->put(self::PENDING_VERIFICATION, $challenge);

            throw ValidationException::withMessages([
                'code' => $challenge['attempts'] >= $maxAttempts
                    ? 'Too many incorrect attempts. Request a new code.'
                    : 'That code is not correct. Check the message and try again.',
            ]);
        }

        $consultantRequest = ConsultantRequest::query()->create([
            'name' => $pendingRequest['name'],
            'normalized_phone' => $pendingRequest['normalized_phone'],
            'concern' => $pendingRequest['concern'] ?? null,
            'status' => 'NEW',
            'phone_verified_at' => now(),
        ]);

        $this->clearPendingRequest($request);
        $request->session()->regenerate();
        $request->session()->flash('consultant_request_reference', $consultantRequest->getKey());

        return to_route('home', ['consult' => 'human']);
    }

    public function resend(
        Request $request,
        PhoneVerificationService $verification,
    ): RedirectResponse {
        $challenge = $request->session()->get(self::PENDING_VERIFICATION);
        $pendingRequest = $request->session()->get(self::PENDING_REQUEST);

        if (! is_array($challenge)
            || ! is_array($pendingRequest)
            || ! is_string($challenge['phone'] ?? null)) {
            return to_route('home', ['consult' => 'human']);
        }

        $sentAt = isset($challenge['sent_at']) && is_string($challenge['sent_at'])
            ? CarbonImmutable::parse($challenge['sent_at'])
            : null;
        $cooldown = (int) config('medipath.phone_verification.resend_cooldown_seconds', 60);

        if ($sentAt?->addSeconds($cooldown)->isFuture()) {
            throw ValidationException::withMessages([
                'resend' => 'Please wait a moment before requesting another code.',
            ]);
        }

        $issued = $verification->issue($challenge['phone']);
        $request->session()->put(self::PENDING_VERIFICATION, $issued['challenge']);

        if (is_string($issued['demo_code'])) {
            $request->session()->put(self::DEMO_CODE, $issued['demo_code']);
        }

        return to_route('home', ['consult' => 'human'])
            ->with('consultant_callback_status', 'A new verification code is ready.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->clearPendingRequest($request);

        return to_route('home', ['consult' => 'human']);
    }

    private function clearPendingRequest(Request $request): void
    {
        $request->session()->forget([
            self::PENDING_REQUEST,
            self::PENDING_VERIFICATION,
            self::DEMO_CODE,
        ]);
    }
}
