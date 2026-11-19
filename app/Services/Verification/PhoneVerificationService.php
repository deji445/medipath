<?php

namespace App\Services\Verification;

use App\Contracts\PhoneVerificationSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PhoneVerificationService
{
    public function __construct(private readonly PhoneVerificationSender $sender) {}

    /** @return array{challenge: array<string, int|string|bool>, demo_code: string|null} */
    public function issue(string $phoneNumber, bool $receiptLookup = false): array
    {
        $driver = (string) config('medipath.phone_verification.driver');
        $configuredDemoCode = config('medipath.phone_verification.demo_code');
        $demoDriver = in_array($driver, ['demo', 'local'], true);
        $code = $demoDriver && is_string($configuredDemoCode) && preg_match('/^\d{6}$/', $configuredDemoCode)
            ? $configuredDemoCode
            : (string) random_int(100000, 999999);
        $showCode = $driver === 'demo'
            || ($driver === 'local' && ! app()->isProduction())
            || (! $receiptLookup && config('medipath.phone_verification.show_code', false));
        $this->sender->send($phoneNumber, $code);
        $now = CarbonImmutable::now();

        return [
            'challenge' => [
                'id' => (string) Str::uuid(),
                'private_sms' => ! $demoDriver && ! $showCode,
                'phone' => $phoneNumber,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'sent_at' => $now->toIso8601String(),
                'expires_at' => $now->addMinutes(
                    (int) config('medipath.phone_verification.expiry_minutes', 10),
                )->toIso8601String(),
            ],
            'demo_code' => $showCode ? $code : null,
        ];
    }

    /** @param array<string, mixed> $challenge */
    public function isExpired(array $challenge): bool
    {
        $expiresAt = $challenge['expires_at'] ?? null;

        return ! is_string($expiresAt) || CarbonImmutable::parse($expiresAt)->isPast();
    }

    /** @param array<string, mixed> $challenge */
    public function matches(array $challenge, string $code): bool
    {
        $hash = $challenge['code_hash'] ?? null;

        return is_string($hash) && Hash::check($code, $hash);
    }
}
