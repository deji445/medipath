<?php

namespace App\Services\Verification;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class VerificationDeliveryRecorder
{
    public function recordDeferred(
        string $outcome,
        int $startedAtNanoseconds,
        int $attemptCount,
        ?string $providerReference = null,
        ?string $providerCode = null,
        ?int $httpStatus = null,
        ?string $errorCode = null,
    ): void {
        if (app()->runningUnitTests()) {
            $this->record(
                $outcome,
                $startedAtNanoseconds,
                $attemptCount,
                $providerReference,
                $providerCode,
                $httpStatus,
                $errorCode,
            );

            return;
        }

        defer(fn () => $this->record(
            $outcome,
            $startedAtNanoseconds,
            $attemptCount,
            $providerReference,
            $providerCode,
            $httpStatus,
            $errorCode,
        ))->always();
    }

    public function record(
        string $outcome,
        int $startedAtNanoseconds,
        int $attemptCount,
        ?string $providerReference = null,
        ?string $providerCode = null,
        ?int $httpStatus = null,
        ?string $errorCode = null,
    ): void {
        try {
            DB::table('verification_delivery_events')->insert([
                'provider' => 'bms',
                'purpose' => 'phone_verification',
                'outcome' => $outcome === 'ACCEPTED' ? 'ACCEPTED' : 'FAILED',
                'provider_reference' => $this->safeValue($providerReference, 100),
                'provider_code' => $this->safeValue($providerCode, 40),
                'http_status' => $httpStatus !== null && $httpStatus >= 100 && $httpStatus <= 599
                    ? $httpStatus
                    : null,
                'duration_ms' => max(0, (int) round((hrtime(true) - $startedAtNanoseconds) / 1_000_000)),
                'attempt_count' => max(0, min($attemptCount, 10)),
                'error_code' => $this->safeErrorCode($errorCode),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Verification delivery metric could not be recorded.', [
                'exception' => $exception::class,
            ]);
        }
    }

    private function safeValue(?string $value, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_-]{1,100}$/', $value) === 1
            ? mb_substr($value, 0, $maxLength)
            : null;
    }

    private function safeErrorCode(?string $errorCode): ?string
    {
        if ($errorCode === null || $errorCode === '') {
            return null;
        }

        return preg_match('/^[A-Z][A-Z0-9_]{0,99}$/', $errorCode) === 1
            ? $errorCode
            : 'UNCLASSIFIED_DELIVERY_FAILURE';
    }
}
